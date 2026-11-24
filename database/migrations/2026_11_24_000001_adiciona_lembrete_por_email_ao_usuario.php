<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('usuario', 'bo_lembrete_email')) {
            return;
        }

        Schema::table('usuario', function (Blueprint $tabela) {
            $tabela->boolean('bo_lembrete_email')->default(true);
        });
    }

    public function down()
    {
        Schema::table('usuario', function (Blueprint $tabela) {
            $tabela->dropColumn('bo_lembrete_email');
        });
    }
};
