<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TAMANHO_DO_CODIGO = 40;

    public function up()
    {
        if (Schema::hasColumn('customers', 'codigo_do_link_de_indicacao')) {
            return;
        }

        Schema::table('customers', function (Blueprint $tabela) {
            $tabela->string('codigo_do_link_de_indicacao', self::TAMANHO_DO_CODIGO)->nullable();
            $tabela->unique('codigo_do_link_de_indicacao', 'uq_customers_codigo_do_link');
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $tabela) {
            $tabela->dropUnique('uq_customers_codigo_do_link');
            $tabela->dropColumn('codigo_do_link_de_indicacao');
        });
    }
};
