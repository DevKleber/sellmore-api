<?php

namespace Tests\Feature;

use App\Mail\SendMailRecover;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\ApiTestCase;

class RecuperacaoDeSenhaTest extends ApiTestCase
{
    private const NOVA_SENHA = 'nova-senha-forte';
    private const LINK_INVALIDO = 'Link inválido ou já utilizado. Peça um novo.';
    private const ENDERECO_DO_FRONT = 'https://app.wiseller.com.br/request-recover-password';
    private const VALIDADE_DO_LINK_EM_SEGUNDOS = 86400;
    private const TOLERANCIA_EM_SEGUNDOS = 120;

    /**
     * Pede a recuperacao e devolve os parametros "url" e "token" do link que iria no e-mail.
     */
    private function linkDeRecuperacao(array $usuario): array
    {
        Mail::fake();
        $this->chamarApi('POST', '/api/auth/recoverPassword', ['email' => $usuario['email']])->assertStatus(200);
        parse_str(parse_url($this->dadosDoEmailEnviado()['link'], PHP_URL_QUERY), $parametros);

        return ['url' => $parametros['url'], 'token' => $parametros['token']];
    }

    private function conteudoDoLink(array $link): array
    {
        return json_decode(base64_decode($link['url']), true);
    }

    private function linkComConteudoTrocado(array $link, array $troca): array
    {
        return ['url' => base64_encode(json_encode($troca + $this->conteudoDoLink($link)))] + $link;
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

    public function test_link_do_email_aponta_para_o_front_e_carrega_email_id_do_usuario_e_validade_de_um_dia()
    {
        $this->criarUsuario();
        $usuario = $this->criarUsuario(['nome' => 'Maria Vendedora']);
        $antesDoPedido = time();

        $link = $this->linkDeRecuperacao($usuario);

        $dadosDoEmail = $this->dadosDoEmailEnviado();
        $conteudo = $this->conteudoDoLink($link);
        $this->assertSame('Maria Vendedora', $dadosDoEmail['no_pessoa']);
        $this->assertStringStartsWith(self::ENDERECO_DO_FRONT.'?url=', $dadosDoEmail['link']);
        $this->assertSame($usuario['email'], $conteudo['email']);
        $this->assertSame($usuario['id'], $conteudo['id']);
        $this->assertEqualsWithDelta($antesDoPedido + self::VALIDADE_DO_LINK_EM_SEGUNDOS, strtotime($conteudo['expired']), self::TOLERANCIA_EM_SEGUNDOS);
    }

    public function test_email_traz_o_nome_e_o_botao_com_o_link_de_troca()
    {
        $usuario = $this->criarUsuario(['nome' => 'Maria Vendedora']);

        $this->linkDeRecuperacao($usuario);

        $corpo = Mail::sent(SendMailRecover::class)->first()->render();
        $this->assertStringContainsString('Olá, Maria Vendedora', $corpo);
        $this->assertStringContainsString('Criar uma senha nova', $corpo);
        $this->assertStringContainsString(e($this->dadosDoEmailEnviado()['link']), $corpo);
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

    public function test_link_funciona_para_qualquer_usuario_e_nao_so_para_o_primeiro()
    {
        $this->criarUsuario();
        $segundoUsuario = $this->criarUsuario();

        $resposta = $this->trocarSenha($this->linkDeRecuperacao($segundoUsuario));

        $resposta->assertStatus(200)->assertJson(['me' => ['id' => $segundoUsuario['id']]]);
    }

    public function test_link_tambem_e_aceito_por_get()
    {
        $usuario = $this->criarUsuario();
        $parametros = $this->linkDeRecuperacao($usuario) + ['newPassword' => self::NOVA_SENHA, 'confirmPassword' => self::NOVA_SENHA];

        $resposta = $this->chamarApi('GET', '/api/auth/recoverPassword?'.http_build_query($parametros));

        $resposta->assertStatus(200)->assertJsonStructure(['access_token']);
    }

    public function test_link_deixa_de_valer_depois_de_usado()
    {
        $usuario = $this->criarUsuario();
        $link = $this->linkDeRecuperacao($usuario);
        $this->trocarSenha($link)->assertStatus(200);

        $resposta = $this->trocarSenha($link, 'outra-senha-forte');

        $resposta->assertStatus(400)->assertExactJson(['response' => self::LINK_INVALIDO]);
    }

    public function test_link_expirado_e_recusado()
    {
        $usuario = $this->criarUsuario();
        $link = $this->linkDeRecuperacao($usuario);

        $this->travel(2)->days();
        $resposta = $this->trocarSenha($link);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Link expirado']);
    }

    public function test_validade_alterada_no_link_e_recusada()
    {
        $usuario = $this->criarUsuario();
        $link = $this->linkComConteudoTrocado($this->linkDeRecuperacao($usuario), ['expired' => '2099-01-01 00:00:00']);

        $resposta = $this->trocarSenha($link);

        $resposta->assertStatus(400)->assertExactJson(['response' => self::LINK_INVALIDO]);
    }

    /**
     * Antes o link nao era assinado: quem soubesse o e-mail e a data de cadastro montava um link valido.
     */
    public function test_link_montado_por_terceiro_com_os_dados_de_outro_usuario_e_recusado()
    {
        $vitima = $this->criarUsuario();
        $conteudo = ['email' => $vitima['email'], 'id' => $vitima['id'], 'expired' => '2099-01-01 00:00:00'];
        $linkForjado = ['url' => base64_encode(json_encode($conteudo)), 'token' => base64_encode($vitima['created_at'])];

        $resposta = $this->trocarSenha($linkForjado);

        $resposta->assertStatus(400)->assertExactJson(['response' => self::LINK_INVALIDO]);
        $this->chamarApi('POST', '/api/auth/login', ['email' => $vitima['email'], 'password' => $vitima['senha']])
            ->assertStatus(200);
    }

    public function test_link_de_um_usuario_nao_troca_a_senha_de_outro()
    {
        $dono = $this->criarUsuario();
        $vitima = $this->criarUsuario();
        $link = $this->linkComConteudoTrocado($this->linkDeRecuperacao($dono), ['email' => $vitima['email'], 'id' => $vitima['id']]);

        $resposta = $this->trocarSenha($link);

        $resposta->assertStatus(400)->assertExactJson(['response' => self::LINK_INVALIDO]);
    }

    public function test_link_sem_conteudo_e_recusado()
    {
        $resposta = $this->trocarSenha(['url' => 'nao-e-um-link', 'token' => 'x']);

        $resposta->assertStatus(400)->assertExactJson(['response' => self::LINK_INVALIDO]);
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
        return [['123456'], ['password'], ['qwerty'], ['iloveyou'], ['abc'], ['']];
    }
}
