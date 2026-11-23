<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class AutenticacaoTest extends ApiTestCase
{
    private const SEGUNDOS_POR_MINUTO = 60;
    private const VALIDADE_PADRAO_DO_TOKEN_EM_MINUTOS = 43200;

    public function test_login_com_credenciais_validas_devolve_token_e_dados_do_usuario()
    {
        $usuario = $this->criarUsuario(['nome' => 'Maria Vendedora']);

        $resposta = $this->chamarApi('POST', '/api/auth/login', [
            'email' => $usuario['email'],
            'password' => $usuario['senha'],
        ]);

        $resposta->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'me' => ['id', 'nome', 'email', 'usuario', 'bo_ativo']])
            ->assertJson([
                'token_type' => 'bearer',
                'expires_in' => self::VALIDADE_PADRAO_DO_TOKEN_EM_MINUTOS * self::SEGUNDOS_POR_MINUTO,
                'me' => ['id' => $usuario['id'], 'nome' => 'Maria Vendedora', 'email' => $usuario['email']],
            ]);
        $this->assertArrayNotHasKey('password', $resposta->json('me'));
    }

    public function test_login_com_senha_errada_e_recusado()
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->chamarApi('POST', '/api/auth/login', [
            'email' => $usuario['email'],
            'password' => 'senha-errada',
        ]);

        $resposta->assertStatus(401)->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_login_com_email_desconhecido_e_recusado()
    {
        $resposta = $this->chamarApi('POST', '/api/auth/login', [
            'email' => 'ninguem@wiseller.test',
            'password' => 'qualquer',
        ]);

        $resposta->assertStatus(401)->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_login_sem_email_e_senha_devolve_erros_de_validacao()
    {
        $resposta = $this->chamarApi('POST', '/api/auth/login', []);

        $resposta->assertStatus(422)->assertJsonStructure(['email', 'password']);
    }

    public function test_me_devolve_o_usuario_dono_do_token()
    {
        $usuario = $this->criarUsuario();
        $token = $this->entrarNaApi($usuario);

        $resposta = $this->chamarApi('GET', '/api/auth/me', [], $token);

        $resposta->assertStatus(200)->assertJson(['id' => $usuario['id'], 'email' => $usuario['email']]);
        $this->assertArrayNotHasKey('password', $resposta->json());
    }

    public function test_rota_protegida_sem_token_e_recusada()
    {
        $resposta = $this->chamarApi('GET', '/api/auth/me');

        $resposta->assertStatus(400)->assertExactJson(['error' => 'Authorization Token not found']);
    }

    public function test_rota_protegida_com_token_invalido_e_recusada()
    {
        $resposta = $this->chamarApi('GET', '/api/auth/me', [], 'token.que.nao.existe');

        $resposta->assertStatus(400)->assertExactJson(['error' => 'Token is Invalid']);
    }

    public function test_token_de_uma_requisicao_nao_vale_para_a_seguinte_sem_cabecalho()
    {
        $token = $this->entrarNaApi($this->criarUsuario());
        $this->chamarApi('GET', '/api/auth/me', [], $token)->assertStatus(200);

        $resposta = $this->chamarApi('GET', '/api/auth/me');

        $resposta->assertStatus(400);
    }

    public function test_refresh_devolve_um_token_novo_que_funciona()
    {
        $usuario = $this->criarUsuario();
        $token = $this->entrarNaApi($usuario);

        $resposta = $this->chamarApi('POST', '/api/auth/refresh', [], $token);

        $resposta->assertStatus(200)->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
        $tokenNovo = $resposta->json('access_token');
        $this->assertNotSame($token, $tokenNovo);
        $this->chamarApi('GET', '/api/auth/me', [], $tokenNovo)->assertStatus(200)->assertJson(['id' => $usuario['id']]);
    }

    public function test_logout_invalida_o_token()
    {
        $token = $this->entrarNaApi($this->criarUsuario());

        $resposta = $this->chamarApi('POST', '/api/auth/logout', [], $token);

        $resposta->assertStatus(200)->assertExactJson(['message' => 'Successfully logged out']);
        $this->chamarApi('GET', '/api/auth/me', [], $token)->assertStatus(400);
    }

    public function test_usuario_inativo_ainda_consegue_entrar()
    {
        $usuario = $this->criarUsuario(['bo_ativo' => 0]);

        $resposta = $this->chamarApi('POST', '/api/auth/login', [
            'email' => $usuario['email'],
            'password' => $usuario['senha'],
        ]);

        $resposta->assertStatus(200);
    }
}
