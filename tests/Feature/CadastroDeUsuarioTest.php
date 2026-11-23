<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class CadastroDeUsuarioTest extends ApiTestCase
{
    private const ESTRATEGIA_PADRAO = "∘ Apresentação\n\n∘ Conexão\n\n∘ DI\n\n∘ Speech\n\n∘ Fechamento\n\n∘ Referidos\n\n∘ Validaçao";
    private const URL_DE_VENDA_PADRAO = 'https://wup.onl/';

    private function dadosDoNovoUsuario(array $sobrescrever = []): array
    {
        return $sobrescrever + [
            'nome' => 'Novo Vendedor',
            'email' => 'novo@wiseller.test',
            'usuario' => 'novovendedor',
            'password' => 'senha-forte-123',
        ];
    }

    public function test_cadastro_publico_cria_o_usuario_e_devolve_os_dados()
    {
        $resposta = $this->chamarApi('POST', '/api/user/new', $this->dadosDoNovoUsuario());

        $resposta->assertStatus(200)->assertJson([
            'response' => 'Salvo com sucesso',
            'dados' => ['nome' => 'Novo Vendedor', 'email' => 'novo@wiseller.test', 'usuario' => 'novovendedor'],
        ]);
        $this->assertSame(1, DB::table('usuario')->where('email', 'novo@wiseller.test')->count());
    }

    public function test_senha_e_gravada_com_hash_e_permite_login()
    {
        $this->chamarApi('POST', '/api/user/new', $this->dadosDoNovoUsuario())->assertStatus(200);

        $senhaGravada = DB::table('usuario')->where('email', 'novo@wiseller.test')->value('password');
        $this->assertNotSame('senha-forte-123', $senhaGravada);
        $this->chamarApi('POST', '/api/auth/login', ['email' => 'novo@wiseller.test', 'password' => 'senha-forte-123'])
            ->assertStatus(200);
    }

    public function test_cadastro_cria_a_estrategia_padrao_do_usuario()
    {
        $resposta = $this->chamarApi('POST', '/api/user/new', $this->dadosDoNovoUsuario());

        $estrategia = DB::table('strategy')->where('id_usuario', $resposta->json('dados.id'))->first();
        $this->assertSame(self::ESTRATEGIA_PADRAO, $estrategia->strategy);
        $this->assertSame(self::URL_DE_VENDA_PADRAO, $estrategia->url_sale);
    }

    public function test_email_ja_cadastrado_e_recusado()
    {
        $existente = $this->criarUsuario();

        $resposta = $this->chamarApi('POST', '/api/user/new', $this->dadosDoNovoUsuario(['email' => $existente['email']]));

        $resposta->assertStatus(400)->assertExactJson(['response' => 'E-mail indisponível']);
        $this->assertSame(1, DB::table('usuario')->count());
    }
}
