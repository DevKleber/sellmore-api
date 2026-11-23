<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

trait BancoDeTestes
{
    private static $esquemaCarregado = false;

    private static $sufixoObrigatorio = '_test';

    private static $tabelaDeControleDasMigrations = 'migrations';

    protected function prepararBancoDeTestes(): void
    {
        $this->recusarBancoQueNaoSejaDeTestes();

        if (!self::$esquemaCarregado) {
            $this->recriarTabelasPelasMigrations();
            self::$esquemaCarregado = true;
        }

        $this->esvaziarTabelas();
    }

    private function recusarBancoQueNaoSejaDeTestes(): void
    {
        $banco = DB::connection()->getDatabaseName();
        $terminaComSufixo = substr($banco, -strlen(self::$sufixoObrigatorio)) === self::$sufixoObrigatorio;

        if (!$terminaComSufixo) {
            throw new RuntimeException(
                "Os testes apagam todas as tabelas e so rodam em banco terminado em '"
                .self::$sufixoObrigatorio."'. Banco atual: '{$banco}'."
            );
        }
    }

    /**
     * O banco de testes e montado pelas mesmas migrations que rodam em producao.
     */
    private function recriarTabelasPelasMigrations(): void
    {
        $this->semChecarChavesEstrangeiras(function () {
            foreach ($this->tabelasDoBanco() as $tabela) {
                DB::statement("DROP TABLE `{$tabela}`");
            }
        });

        Artisan::call('migrate', ['--force' => true]);
    }

    private function esvaziarTabelas(): void
    {
        $this->semChecarChavesEstrangeiras(function () {
            foreach ($this->tabelasDoBanco() as $tabela) {
                if ($tabela === self::$tabelaDeControleDasMigrations) {
                    continue;
                }
                DB::statement("TRUNCATE TABLE `{$tabela}`");
            }
        });
    }

    private function tabelasDoBanco(): array
    {
        $linhas = DB::select('SELECT table_name AS nome FROM information_schema.tables WHERE table_schema = DATABASE()');

        return array_map(function ($linha) {
            return $linha->nome;
        }, $linhas);
    }

    private function semChecarChavesEstrangeiras(callable $operacao): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $operacao();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
