<?php

namespace App\Console;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Impede comandos do artisan que apagam tabelas ou desfazem migrations fora de um banco de testes.
 */
class BloqueioDeComandosDestrutivos
{
    private const COMANDOS_DESTRUTIVOS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
    ];

    private const SUFIXO_DE_BANCO_DE_TESTES = '_test';

    public function handle(CommandStarting $evento): void
    {
        $banco = (string) DB::connection()->getDatabaseName();

        if (!self::deveBloquear((string) $evento->command, $banco)) {
            return;
        }

        throw new RuntimeException(
            "O comando '{$evento->command}' apaga dados e so pode rodar em banco terminado em '"
            .self::SUFIXO_DE_BANCO_DE_TESTES."'. Banco atual: '{$banco}'."
        );
    }

    public static function deveBloquear(string $comando, string $banco): bool
    {
        if (!in_array($comando, self::COMANDOS_DESTRUTIVOS, true)) {
            return false;
        }

        return substr($banco, -strlen(self::SUFIXO_DE_BANCO_DE_TESTES)) !== self::SUFIXO_DE_BANCO_DE_TESTES;
    }
}
