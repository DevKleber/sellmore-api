<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endereco que o servidor de e-mail recusa (caixa que nao existe mais) fica registrado
 * com o motivo, para o envio em lotes seguir para o proximo em vez de travar nele.
 */
return new class extends Migration
{
    private const TABELA = 'envio_de_campanha';
    private const COLUNA = 'motivo_da_recusa';

    public function up()
    {
        if (Schema::hasColumn(self::TABELA, self::COLUNA)) {
            return;
        }

        Schema::table(self::TABELA, function (Blueprint $tabela) {
            $tabela->string(self::COLUNA, 255)->nullable();
        });
    }

    public function down()
    {
        Schema::table(self::TABELA, function (Blueprint $tabela) {
            $tabela->dropColumn(self::COLUNA);
        });
    }
};
