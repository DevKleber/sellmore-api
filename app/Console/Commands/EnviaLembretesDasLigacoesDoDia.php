<?php

namespace App\Console\Commands;

use App\LembreteDeLigacoes;
use App\Mail\LembreteDasLigacoesDoDia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviaLembretesDasLigacoesDoDia extends Command
{
    protected $signature = 'lembretes:ligacoes-do-dia {--simular : So informa quantos e-mails seriam enviados}';

    protected $description = 'Envia a cada vendedor um e-mail com as ligações agendadas para hoje';

    public function handle()
    {
        $vendedores = LembreteDeLigacoes::deHojePorVendedor();
        if ($this->option('simular')) {
            $this->info('Lembretes que seriam enviados: '.count($vendedores));

            return self::SUCCESS;
        }

        $enviados = 0;
        foreach ($vendedores as $vendedor) {
            $enviados += (int) $this->enviar($vendedor);
        }

        $this->info("Lembretes enviados: {$enviados}");

        return self::SUCCESS;
    }

    /**
     * A falha no envio para um vendedor nao pode impedir o envio para os demais.
     */
    private function enviar(array $vendedor)
    {
        $mensagem = new LembreteDasLigacoesDoDia(
            $vendedor,
            config('wiseller.endereco_da_plataforma'),
            LembreteDeLigacoes::enderecoDoCancelamento($vendedor['id'], $vendedor['email'])
        );

        try {
            Mail::to($vendedor['email'])->send($mensagem);
        } catch (\Throwable $falha) {
            Log::error("Lembrete nao enviado para o usuario {$vendedor['id']}: {$falha->getMessage()}");

            return false;
        }

        return true;
    }
}
