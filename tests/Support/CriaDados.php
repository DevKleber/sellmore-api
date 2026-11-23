<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Insere registros direto nas tabelas, sem depender de factories nem dos models da aplicacao.
 */
trait CriaDados
{
    private static $senhaPadrao = 'senha-de-teste';

    private static $sequenciaDeUsuarios = 0;

    protected function criarUsuario(array $atributos = []): array
    {
        $sequencia = ++self::$sequenciaDeUsuarios;
        $senha = $atributos['senha'] ?? self::$senhaPadrao;
        unset($atributos['senha']);

        $usuario = $this->inserir('usuario', $atributos + [
            'nome' => "Vendedor {$sequencia}",
            'email' => "vendedor{$sequencia}@wiseller.test",
            'usuario' => "vendedor{$sequencia}",
            'password' => Hash::make($senha),
            'bo_ativo' => 1,
            'created_at' => '2024-01-10 08:00:00',
            'updated_at' => '2024-01-10 08:00:00',
        ]);

        return $usuario + ['senha' => $senha];
    }

    protected function criarCliente(int $idUsuario, array $atributos = []): array
    {
        return $this->inserir('customers', $atributos + [
            'name' => 'Cliente de Teste',
            'status' => 'a',
            'id_usuario' => $idUsuario,
            'id_parent' => null,
            'observation' => null,
            'bo_ativo' => 1,
            'bo_preference' => 0,
            'created_at' => '2024-01-10 09:00:00',
            'updated_at' => '2024-01-10 09:00:00',
        ]);
    }

    protected function criarTelefone(int $idCliente, string $numero, array $atributos = []): array
    {
        return $this->inserir('customers_phone', $atributos + [
            'phone' => $numero,
            'country_code' => '55',
            'bo_whatsapp' => 1,
            'bo_main' => 0,
            'bo_ativo' => 1,
            'id_customers' => $idCliente,
        ]);
    }

    protected function criarEstrategia(int $idUsuario, array $atributos = []): array
    {
        return $this->inserir('strategy', $atributos + [
            'strategy' => "Apresentacao\nFechamento",
            'url_sale' => 'https://exemplo.test/venda',
            'id_usuario' => $idUsuario,
        ]);
    }

    protected function criarCompromisso(int $idUsuario, int $idCliente, array $atributos = []): array
    {
        return $this->inserir('calendar', $atributos + [
            'date' => '2030-05-20 14:30:00',
            'date_end' => null,
            'bo_ativo' => 1,
            'id_usuario' => $idUsuario,
            'id_customers' => $idCliente,
        ]);
    }

    protected function criarLogDeAcesso(int $idUsuario, array $atributos = []): array
    {
        return $this->inserir('usuario_log', $atributos + [
            'display_resolution' => '1920x1080',
            'os' => 'MacIntel',
            'language' => 'pt-BR',
            'device' => 'Linux',
            'browser' => 'Chrome',
            'ip' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'id_user' => $idUsuario,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Telefone no formato que o front envia ao cadastrar ou alterar um cliente.
     */
    protected function telefoneParaEnvio(string $numero, bool $whatsapp = true): array
    {
        return ['phone' => $numero, 'country_code' => '55', 'bo_whatsapp' => $whatsapp];
    }

    protected function telefonesGravadosDe(int $idCliente): array
    {
        return DB::table('customers_phone')->where('id_customers', $idCliente)->orderBy('id')->pluck('phone')->all();
    }

    protected function registro(string $tabela, int $id): ?array
    {
        $linha = DB::table($tabela)->where('id', $id)->first();

        return null === $linha ? null : (array) $linha;
    }

    private function inserir(string $tabela, array $valores): array
    {
        $id = DB::table($tabela)->insertGetId($valores);

        return ['id' => $id] + $valores;
    }
}
