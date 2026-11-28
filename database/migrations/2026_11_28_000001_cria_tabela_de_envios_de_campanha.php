<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra quem ja recebeu cada e-mail de campanha, para o envio em lotes nunca repetir
 * destinatario e para contar quantos sairam no dia.
 */
return new class extends Migration
{
    private const TABELA = 'envio_de_campanha';

    public function up()
    {
        if (Schema::hasTable(self::TABELA)) {
            return;
        }

        Schema::create(self::TABELA, function (Blueprint $tabela) {
            $tabela->increments('id');
            $tabela->integer('id_usuario');
            $tabela->string('campanha', 60);
            $tabela->timestamp('enviado_em');

            $tabela->foreign('id_usuario')->references('id')->on('usuario')->cascadeOnDelete();
            $tabela->unique(['id_usuario', 'campanha']);
            $tabela->index(['campanha', 'enviado_em']);
        });
    }

    public function down()
    {
        Schema::dropIfExists(self::TABELA);
    }
};
