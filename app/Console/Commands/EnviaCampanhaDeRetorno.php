<?php

namespace App\Console\Commands;

use App\CampanhaDeRetorno;
use App\Mail\VoltaAoWiseller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envia o convite em lotes pequenos para respeitar o limite de envio da caixa de e-mail.
 * Pensado para rodar por cron de hora em hora ate acabarem os destinatarios.
 */
class EnviaCampanhaDeRetorno extends Command
{
    private const LOTE_PADRAO = 10;
    private const LIMITE_DIARIO_PADRAO = 90;

    protected $signature = 'campanha:volta-ao-wiseller
        {--lote='.self::LOTE_PADRAO.' : Quantos e-mails enviar nesta execucao}
        {--limite-diario='.self::LIMITE_DIARIO_PADRAO.' : Maximo de e-mails da campanha por dia}
        {--para= : Envia so para a conta com este e-mail, sem registrar o envio (teste)}
        {--simular : So informa quantos e-mails sairiam}';

    protected $description = 'Envia em lotes o convite para quem deixou de usar o Wiseller voltar';

    public function handle(CampanhaDeRetorno $campanha)
    {
        if ($this->option('para')) {
            return $this->enviarTeste((string) $this->option('para'));
        }

        $vagas = $this->vagasNestaExecucao($campanha);
        $destinatarios = $campanha->proximosDestinatarios($vagas);

        if ($this->option('simular')) {
            $this->info("Pendentes: {$campanha->quantidadeDePendentes()}. Sairiam agora: {$destinatarios->count()}.");

            return self::SUCCESS;
        }

        $enviados = $this->enviarLote($campanha, $destinatarios);
        $this->info(now()->format('Y-m-d H:i')." Enviados: {$enviados}. Pendentes: {$campanha->quantidadeDePendentes()}.");

        return self::SUCCESS;
    }

    private function vagasNestaExecucao(CampanhaDeRetorno $campanha): int
    {
        $restanteDoDia = (int) $this->option('limite-diario') - $campanha->enviadosHoje();

        return max(0, min((int) $this->option('lote'), $restanteDoDia));
    }

    /**
     * Para no primeiro erro: quase sempre e o limite da caixa de e-mail, e insistir
     * so acumularia recusas. O proximo lote retoma de onde este parou.
     */
    private function enviarLote(CampanhaDeRetorno $campanha, $destinatarios): int
    {
        $enviados = 0;
        foreach ($destinatarios as $conta) {
            if (!$this->enviar($conta)) {
                return $enviados;
            }

            $campanha->registrarEnvio($conta->id);
            ++$enviados;
        }

        return $enviados;
    }

    private function enviar($conta): bool
    {
        try {
            Mail::to($conta->email)->send(new VoltaAoWiseller($conta->nome));
        } catch (\Throwable $falha) {
            Log::error("Convite de retorno nao enviado para o usuario {$conta->id}: {$falha->getMessage()}");
            $this->error("Falha no envio para o usuario {$conta->id}: {$falha->getMessage()}");

            return false;
        }

        return true;
    }

    private function enviarTeste(string $email)
    {
        $conta = DB::table('usuario')->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($email))])->first();
        if (!$conta) {
            $this->error('Nenhuma conta com esse e-mail.');

            return self::FAILURE;
        }

        if (!$this->enviar($conta)) {
            return self::FAILURE;
        }

        $this->info("Teste enviado para a conta {$conta->id}.");

        return self::SUCCESS;
    }
}
