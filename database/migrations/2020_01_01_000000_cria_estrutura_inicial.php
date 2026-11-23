<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cria as tabelas exatamente como ja existem em producao (database/schema/estrutura-inicial.sql).
 * Em um banco que ja tem as tabelas ela nao executa nada: apenas fica registrada como aplicada.
 */
class CriaEstruturaInicial extends Migration
{
    private const TABELAS = ['usuario', 'customers', 'customers_phone', 'calendar', 'strategy', 'usuario_log'];

    private const ARQUIVO_DA_ESTRUTURA = __DIR__.'/../schema/estrutura-inicial.sql';

    public function up()
    {
        $existentes = array_filter(self::TABELAS, [Schema::class, 'hasTable']);

        if (count($existentes) === count(self::TABELAS)) {
            return;
        }

        if (count($existentes) > 0) {
            throw new RuntimeException(
                'Banco em estado inesperado: so parte das tabelas existe ('.implode(', ', $existentes).'). Nada foi alterado.'
            );
        }

        Schema::disableForeignKeyConstraints();
        DB::unprepared(file_get_contents(self::ARQUIVO_DA_ESTRUTURA));
        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        throw new RuntimeException('A estrutura inicial nao pode ser desfeita: isso apagaria todas as tabelas do sistema.');
    }
}
