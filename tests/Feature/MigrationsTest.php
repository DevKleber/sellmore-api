<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\ApiTestCase;

class MigrationsTest extends ApiTestCase
{
    private const TABELAS_DO_SISTEMA = ['calendar', 'customers', 'customers_phone', 'envio_de_campanha', 'strategy', 'usuario', 'usuario_log'];

    private function tabelasDoSistemaNoBanco(): array
    {
        $linhas = DB::select(
            "SELECT table_name AS nome FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name <> 'migrations' ORDER BY table_name"
        );

        return array_map(function ($linha) {
            return $linha->nome;
        }, $linhas);
    }

    public function test_migrations_criam_todas_as_tabelas_do_sistema()
    {
        $this->assertSame(self::TABELAS_DO_SISTEMA, $this->tabelasDoSistemaNoBanco());
    }

    public function test_rodar_as_migrations_de_novo_nao_altera_os_dados()
    {
        $usuario = $this->criarUsuario();
        $cliente = $this->criarCliente($usuario['id'], ['name' => 'Cliente Preservado']);
        $this->criarTelefone($cliente['id'], '62999990001');

        $codigoDeSaida = Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, $codigoDeSaida);
        $this->assertSame('Cliente Preservado', $this->registro('customers', $cliente['id'])['name']);
        $this->assertSame(1, DB::table('usuario')->count());
        $this->assertSame(1, DB::table('customers_phone')->count());
    }

    /**
     * Simula a primeira execucao em producao: as tabelas ja existem, mas a migration ainda nao esta registrada.
     */
    public function test_em_banco_que_ja_tem_as_tabelas_a_estrutura_inicial_so_fica_registrada()
    {
        $usuario = $this->criarUsuario(['nome' => 'Usuario de Producao']);
        DB::table('migrations')->delete();

        $codigoDeSaida = Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, $codigoDeSaida);
        $this->assertSame(1, DB::table('migrations')->where('migration', 'like', '%cria_estrutura_inicial')->count());
        $this->assertSame('Usuario de Producao', $this->registro('usuario', $usuario['id'])['nome']);
        $this->assertSame(self::TABELAS_DO_SISTEMA, $this->tabelasDoSistemaNoBanco());
    }

    public function test_estrutura_inicial_recusa_ser_desfeita()
    {
        require_once database_path('migrations/2020_01_01_000000_cria_estrutura_inicial.php');

        $this->expectException(RuntimeException::class);

        (new \CriaEstruturaInicial())->down();
    }
}
