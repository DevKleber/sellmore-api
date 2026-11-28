<?php

namespace App;

use App\Mail\VerificadorDeDominio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quem recebe o convite para voltar ao Wiseller: conta ativa, com e-mail em formato valido
 * e dominio que recebe e-mail, sem acesso nos ultimos dias e que ainda nao recebeu.
 */
class CampanhaDeRetorno
{
    public const NOME = 'volta-ao-wiseller-2026-11';

    private const TABELA_DE_ENVIOS = 'envio_de_campanha';
    private const DIAS_SEM_ACESSO = 30;
    private const FORMATO_DO_EMAIL = '^[^@[:space:]]+@[^@[:space:]]+\\.[A-Za-z]{2,}$';

    private $verificadorDeDominio;

    public function __construct(VerificadorDeDominio $verificadorDeDominio)
    {
        $this->verificadorDeDominio = $verificadorDeDominio;
    }

    public function proximosDestinatarios(int $quantidade): Collection
    {
        return $this->pendentes()->take($quantidade)->values();
    }

    public function quantidadeDePendentes(): int
    {
        return $this->pendentes()->count();
    }

    public function enviadosHoje(): int
    {
        return DB::table(self::TABELA_DE_ENVIOS)
            ->where('campanha', self::NOME)
            ->where('enviado_em', '>=', now()->startOfDay())
            ->count();
    }

    public function registrarEnvio(int $idUsuario): void
    {
        DB::table(self::TABELA_DE_ENVIOS)->insert([
            'id_usuario' => $idUsuario,
            'campanha' => self::NOME,
            'enviado_em' => now(),
        ]);
    }

    private function pendentes(): Collection
    {
        return $this->contasQueAindaNaoReceberam()
            ->filter(fn ($conta) => $this->verificadorDeDominio->recebeEmail(self::dominio($conta->email)));
    }

    private function contasQueAindaNaoReceberam(): Collection
    {
        $limiteDoAcesso = now()->subDays(self::DIAS_SEM_ACESSO);

        return DB::table('usuario')
            ->select('id', 'nome', DB::raw('TRIM(email) as email'))
            ->where('bo_ativo', 1)
            ->whereRaw('TRIM(email) REGEXP ?', [self::FORMATO_DO_EMAIL])
            ->whereNotExists(function ($consulta) use ($limiteDoAcesso) {
                $consulta->select(DB::raw(1))->from('usuario_log')
                    ->whereColumn('usuario_log.id_user', 'usuario.id')
                    ->where('usuario_log.created_at', '>=', $limiteDoAcesso);
            })
            ->whereNotExists(function ($consulta) {
                $consulta->select(DB::raw(1))->from(self::TABELA_DE_ENVIOS)
                    ->whereColumn(self::TABELA_DE_ENVIOS.'.id_usuario', 'usuario.id')
                    ->where(self::TABELA_DE_ENVIOS.'.campanha', self::NOME);
            })
            ->orderBy('id')
            ->get();
    }

    private static function dominio(string $email): string
    {
        return substr(strrchr($email, '@'), 1);
    }
}
