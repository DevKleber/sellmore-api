<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class ContaTest extends ApiTestCase
{
    private const TABELAS_COM_DADOS_DO_VENDEDOR = ['customers', 'customers_phone', 'calendar', 'strategy', 'usuario_log'];
    private const MARCA_DE_UTF8 = "\xEF\xBB\xBF";

    private function linhasDaExportacao(string $token): array
    {
        $resposta = $this->chamarApi('GET', '/api/conta/exportar-clientes', [], $token);
        $resposta->assertStatus(200);
        $conteudo = $resposta->streamedContent();
        $this->assertStringStartsWith(self::MARCA_DE_UTF8, $conteudo);
        $linhas = explode("\n", trim(substr($conteudo, strlen(self::MARCA_DE_UTF8))));

        return array_map(function ($linha) {
            return str_getcsv($linha, ';', '"', '');
        }, $linhas);
    }

    private function criarContaComDados(array $usuario): void
    {
        $lead = $this->criarCliente($usuario['id']);
        $this->criarTelefone($lead['id'], '62999990001');
        $this->criarCompromisso($usuario['id'], $lead['id']);
        $this->criarEstrategia($usuario['id']);
        $this->criarLogDeAcesso($usuario['id']);
    }

    public function test_exportacao_traz_cabecalho_e_uma_linha_por_cliente_em_ordem_alfabetica()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $lead = $this->criarCliente($usuario['id'], ['name' => 'Bruno Lead', 'status' => 'c', 'created_at' => '2026-03-05 10:00:00']);
        $this->criarTelefone($lead['id'], '62999990001');
        $this->criarTelefone($lead['id'], '6232220002');
        $this->criarCliente($usuario['id'], ['name' => 'Ana Referida', 'status' => 'ld', 'id_parent' => $lead['id'], 'observation' => 'Ligar à tarde; prefere WhatsApp', 'bo_ativo' => 0, 'created_at' => '2026-04-01 10:00:00']);

        $linhas = $this->linhasDaExportacao($token);

        $this->assertSame(['Nome', 'Telefones', 'Status', 'Indicado por', 'Observação', 'Arquivado', 'Cadastrado em'], $linhas[0]);
        $this->assertSame(['Ana Referida', '', 'Ligar depois', 'Bruno Lead', 'Ligar à tarde; prefere WhatsApp', 'Sim', '01/04/2026'], $linhas[1]);
        $this->assertSame(['Bruno Lead', '62999990001, 6232220002', 'Comprou', '', '', 'Não', '05/03/2026'], $linhas[2]);
        $this->assertCount(3, $linhas);
    }

    public function test_exportacao_nao_traz_clientes_de_outro_usuario()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $this->criarCliente($outro['id'], ['name' => 'Do Outro']);

        $this->assertCount(1, $this->linhasDaExportacao($token));
    }

    public function test_exportacao_neutraliza_texto_que_a_planilha_executaria_como_formula()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id'], ['name' => '=SOMA(A1:A9)']);

        $this->assertSame("'=SOMA(A1:A9)", $this->linhasDaExportacao($token)[1][0]);
    }

    public function test_lembrete_por_email_comeca_ligado_e_pode_ser_desligado()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->chamarApi('GET', '/api/auth/me', [], $token)->assertJson(['bo_lembrete_email' => true]);

        $resposta = $this->chamarApi('PUT', '/api/conta/lembrete', ['bo_lembrete_email' => false], $token);

        $resposta->assertStatus(200)->assertJson(['dados' => ['bo_lembrete_email' => false]]);
        $this->assertEquals(0, $this->registro('usuario', $usuario['id'])['bo_lembrete_email']);
    }

    public function test_excluir_a_conta_exige_a_senha()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarContaComDados($usuario);

        $resposta = $this->chamarApi('DELETE', '/api/conta', ['password' => 'senha-errada'], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'A senha não confere']);
        $this->assertNotNull($this->registro('usuario', $usuario['id']));
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_excluir_a_conta_apaga_tudo_do_vendedor_e_o_login_deixa_de_funcionar()
    {
        $this->criarUsuario();
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarContaComDados($usuario);

        $resposta = $this->chamarApi('DELETE', '/api/conta', ['password' => $usuario['senha']], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Conta excluída']);
        $this->assertNull($this->registro('usuario', $usuario['id']));
        foreach (self::TABELAS_COM_DADOS_DO_VENDEDOR as $tabela) {
            $this->assertSame(0, DB::table($tabela)->count(), "Sobrou registro em {$tabela}");
        }
        $this->chamarApi('POST', '/api/auth/login', ['email' => $usuario['email'], 'password' => $usuario['senha']])->assertStatus(401);
        $this->chamarApi('GET', '/api/auth/me', [], $token)->assertStatus(400);
    }

    public function test_excluir_a_conta_nao_toca_nos_dados_de_outro_vendedor()
    {
        $this->criarUsuario();
        $outro = $this->criarUsuario();
        $this->criarContaComDados($outro);
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarContaComDados($usuario);

        $this->chamarApi('DELETE', '/api/conta', ['password' => $usuario['senha']], $token)->assertStatus(200);

        foreach (self::TABELAS_COM_DADOS_DO_VENDEDOR as $tabela) {
            $this->assertSame(1, DB::table($tabela)->count(), "O registro do outro vendedor sumiu de {$tabela}");
        }
    }

    public function test_conta_de_administrador_nao_pode_ser_excluida()
    {
        [$admin, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('DELETE', '/api/conta', ['password' => $admin['senha']], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'A conta de administrador não pode ser excluída']);
        $this->assertNotNull($this->registro('usuario', $admin['id']));
    }
}
