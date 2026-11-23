<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class EstrategiaTest extends ApiTestCase
{
    public function test_consulta_devolve_a_estrategia_do_usuario_e_o_texto_quebrado_em_linhas()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarEstrategia($usuario['id'], ['strategy' => "Apresentacao\nConexao\nFechamento", 'url_sale' => 'https://exemplo.test/v']);

        $resposta = $this->chamarApi('GET', '/api/strategy', [], $token);

        $resposta->assertStatus(200)->assertJson([
            'dados' => ['id_usuario' => $usuario['id'], 'strategy' => "Apresentacao\nConexao\nFechamento", 'url_sale' => 'https://exemplo.test/v'],
            'nl2br' => ['Apresentacao', 'Conexao', 'Fechamento'],
        ]);
    }

    public function test_consulta_nao_devolve_a_estrategia_de_outro_usuario()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $this->criarEstrategia($outro['id']);

        $resposta = $this->chamarApi('GET', '/api/strategy', [], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Não existe Strategy']);
    }

    public function test_alteracao_atualiza_texto_e_url_da_estrategia_existente()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $estrategia = $this->criarEstrategia($usuario['id']);

        $resposta = $this->chamarApi('PUT', '/api/strategy', ['strategy' => 'Novo roteiro', 'url_sale' => 'https://exemplo.test/novo'], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Atualizado com sucesso']);
        $gravada = $this->registro('strategy', $estrategia['id']);
        $this->assertSame('Novo roteiro', $gravada['strategy']);
        $this->assertSame('https://exemplo.test/novo', $gravada['url_sale']);
    }

    public function test_alteracao_cria_a_estrategia_quando_o_usuario_ainda_nao_tem()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('PUT', '/api/strategy', ['strategy' => 'Primeiro roteiro', 'url_sale' => 'https://exemplo.test/1'], $token);

        $resposta->assertStatus(200)->assertJson([
            'response' => 'Salvo com sucesso',
            'dados' => ['strategy' => 'Primeiro roteiro', 'id_usuario' => $usuario['id']],
        ]);
        $this->assertSame(1, DB::table('strategy')->where('id_usuario', $usuario['id'])->count());
    }

    public function test_alteracao_nao_mexe_na_estrategia_de_outro_usuario()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarEstrategia($usuario['id']);
        $outro = $this->criarUsuario();
        $estrategiaDoOutro = $this->criarEstrategia($outro['id'], ['strategy' => 'Roteiro do outro']);

        $this->chamarApi('PUT', '/api/strategy', ['strategy' => 'Meu roteiro'], $token)->assertStatus(200);

        $this->assertSame('Roteiro do outro', $this->registro('strategy', $estrategiaDoOutro['id'])['strategy']);
    }
}
