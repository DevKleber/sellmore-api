<?php

namespace Tests\Feature;

use App\Mail\SendMailRecover;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\ApiTestCase;

class RecuperacaoDeSenhaTest extends ApiTestCase
{
    private const CRIADO_EM = '2024-01-10 08:00:00';
    private const NOVA_SENHA = 'nova-senha-forte';
    private const ID_FIXO_NO_LINK = '1';
    private const ENDERECO_DO_FRONT = 'https://wiseller.com.br/request-recover-password';
    private const VALIDADE_DO_LINK_EM_SEGUNDOS = 86400;
    private const TOLERANCIA_EM_SEGUNDOS = 120;

    private function linkDeRecuperacao(array $usuario, string $validade = '+1 day', string $id = self::ID_FIXO_NO_LINK): array
    {
        $conteudo = ['email' => $usuario['email'], 'id' => $id, 'expired' => date('Y-m-d H:i:s', strtotime($validade))];

        return ['url' => base64_encode(json_encode($conteudo)), 'token' => base64_encode(self::CRIADO_EM)];
    }

    private function trocarSenha(array $link, string $nova = self::NOVA_SENHA, ?string $confirmacao = null)
    {
        return $this->chamarApi('PUT', '/api/auth/changePassoword', $link + [
            'newPassword' => $nova,
            'confirmPassword' => $confirmacao ?? $nova,
        ]);
    }

    public function test_pedido_para_email_desconhecido_e_recusado_sem_enviar_email()
    {
        Mail::fake();

        $resposta = $this->chamarApi('POST', '/api/auth/recoverPassword', ['email' => 'ninguem@wiseller.test']);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Não encontrado']);
        Mail::assertNothingSent();
    }

    public function test_pedido_para_email_cadastrado_envia_email_com_link_para_o_front()
    {
        Mail::fake();
        $usuario = $this->criarUsuario(['nome' => 'Maria Vendedora']);

        $resposta = $this->chamarApi('POST', '/api/auth/recoverPassword', ['email' => $usuario['email']]);

        $resposta->assertStatus(200)
            ->assertExactJson(['response' => 'Enviamos um e-mail com o link para alteração de senha.']);
        Mail::assertSent(SendMailRecover::class, function ($email) use ($usuario) {
            return $email->hasTo($usuario['email']);
        });
    }

    public function test_link_do_email_aponta_para_o_front_e_carrega_email_id_fixo_e_validade_de_um_dia()
    {
        Mail::fake();
        $usuario = $this->criarUsuario(['nome' => 'Maria Vendedora']);
        $antesDoPedido = time();

        $this->chamarApi('POST', '/api/auth/recoverPassword', ['email' => $usuario['email']])->assertStatus(200);

        $dadosDoEmail = $this->dadosDoEmailEnviado();
        $this->assertSame('Maria Vendedora', $dadosDoEmail['no_pessoa']);
        parse_str(parse_url($dadosDoEmail['link'], PHP_URL_QUERY), $parametros);
        $conteudo = json_decode(base64_decode($parametros['url']), true);
        $this->assertStringStartsWith(self::ENDERECO_DO_FRONT.'?url=', $dadosDoEmail['link']);
        $this->assertSame($usuario['email'], $conteudo['email']);
        $this->assertSame(self::ID_FIXO_NO_LINK, $conteudo['id']);
        $this->assertEqualsWithDelta($antesDoPedido + self::VALIDADE_DO_LINK_EM_SEGUNDOS, strtotime($conteudo['expired']), self::TOLERANCIA_EM_SEGUNDOS);
        $this->assertSame(self::CRIADO_EM, base64_decode($parametros['token']));
    }

    private function dadosDoEmailEnviado(): array
    {
        $email = Mail::sent(SendMailRecover::class)->first();
        $dados = new ReflectionProperty(SendMailRecover::class, 'user');
        $dados->setAccessible(true);

        return $dados->getValue($email);
    }

    public function test_link_valido_troca_a_senha_e_ja_devolve_token()
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->trocarSenha($this->linkDeRecuperacao($usuario));

        $resposta->assertStatus(200)->assertJsonStructure(['access_token', 'me'])->assertJson(['me' => ['id' => $usuario['id']]]);
        $this->chamarApi('POST', '/api/auth/login', ['email' => $usuario['email'], 'password' => self::NOVA_SENHA])
            ->assertStatus(200);
        $this->chamarApi('POST', '/api/auth/login', ['email' => $usuario['email'], 'password' => $usuario['senha']])
            ->assertStatus(401);
    }

    public function test_link_tambem_e_aceito_por_get()
    {
        $usuario = $this->criarUsuario();
        $parametros = $this->linkDeRecuperacao($usuario) + ['newPassword' => self::NOVA_SENHA, 'confirmPassword' => self::NOVA_SENHA];

        $resposta = $this->chamarApi('GET', '/api/auth/recoverPassword?'.http_build_query($parametros));

        $resposta->assertStatus(200)->assertJsonStructure(['access_token']);
    }

    public function test_link_expirado_e_recusado()
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->trocarSenha($this->linkDeRecuperacao($usuario, '-1 minute'));

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Link expirado']);
    }

    public function test_token_que_nao_confere_com_a_data_de_criacao_e_recusado()
    {
        $usuario = $this->criarUsuario();
        $link = ['token' => base64_encode('2020-01-01 00:00:00')] + $this->linkDeRecuperacao($usuario);

        $resposta = $this->trocarSenha($link);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Error']);
    }

    public function test_confirmacao_diferente_da_senha_e_recusada()
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->trocarSenha($this->linkDeRecuperacao($usuario), self::NOVA_SENHA, 'outra-senha');

        $resposta->assertStatus(400)->assertExactJson(['response' => 'As senhas não conferem']);
    }

    #[DataProvider('senhasFracas')]
    public function test_senha_da_lista_de_senhas_fracas_e_recusada(string $senhaFraca)
    {
        $usuario = $this->criarUsuario();

        $resposta = $this->trocarSenha($this->linkDeRecuperacao($usuario), $senhaFraca);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'A senha informada é muito fraca']);
    }

    public static function senhasFracas(): array
    {
        return [['123456'], ['password'], ['qwerty'], ['iloveyou']];
    }

    /**
     * O link enviado por e-mail leva sempre o id "1", entao so o usuario de id 1 consegue concluir a troca.
     */
    public function test_link_gerado_pela_api_so_funciona_para_o_usuario_de_id_1()
    {
        $this->criarUsuario();
        $segundoUsuario = $this->criarUsuario();

        $resposta = $this->trocarSenha($this->linkDeRecuperacao($segundoUsuario));

        $resposta->assertStatus(500);
    }
}
