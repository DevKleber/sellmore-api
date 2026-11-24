<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda quando o status do cliente mudou pela ultima vez, para o painel de resultados.
 * Para os clientes antigos vale a data da ultima alteracao do cadastro.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('customers', 'status_alterado_em')) {
            return;
        }

        Schema::table('customers', function (Blueprint $tabela) {
            $tabela->timestamp('status_alterado_em')->nullable();
            $tabela->index(['id_usuario', 'status_alterado_em'], 'idx_customers_usuario_status_alterado');
        });

        DB::statement('UPDATE customers SET status_alterado_em = COALESCE(updated_at, created_at)');
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $tabela) {
            $tabela->dropIndex('idx_customers_usuario_status_alterado');
            $tabela->dropColumn('status_alterado_em');
        });
    }
};
