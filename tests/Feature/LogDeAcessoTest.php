<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class LogDeAcessoTest extends ApiTestCase
{
    private const NAVEGADOR_CHROME_NO_ANDROID = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36';
    private const IP_DO_CLIENTE = '203.0.113.7';

    private $servidorOriginal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servidorOriginal = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->servidorOriginal;
        parent::tearDown();
    }

    /**
     * A API le navegador e IP direto das variaveis globais do PHP, que o cliente de testes nao preenche.
     */
    private function simularNavegador(string $userAgent, string $ip): void
    {
        $_SERVER['HTTP_USER_AGENT'] = $userAgent;
        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    private function criarAdministrador(): array
    {
        return $this->criarUsuarioAutenticado(['nome' => 'Administrador']);
    }

    public function test_registrar_acesso_grava_dados_do_navegador_do_usuario_logado()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->simularNavegador(self::NAVEGADOR_CHROME_NO_ANDROID, self::IP_DO_CLIENTE);

        $resposta = $this->chamarApi('POST', '/api/access_log', [
            'display_resolution' => '412x915',
            'os' => 'Linux armv8l',
            'language' => 'pt-BR',
            'user_agent' => self::NAVEGADOR_CHROME_NO_ANDROID,
        ], $token);

        $resposta->assertStatus(200)->assertJson(['response' => 'Salvo com sucesso']);
        $gravado = $this->registro('usuario_log', $resposta->json('dados.id'));
        $this->assertSame($usuario['id'], $gravado['id_user']);
        $this->assertSame('412x915', $gravado['display_resolution']);
        $this->assertSame('pt-BR', $gravado['language']);
        $this->assertSame('Android', $gravado['device']);
        $this->assertSame('Chrome', $gravado['browser']);
        $this->assertSame(self::IP_DO_CLIENTE, $gravado['ip']);
    }

    public function test_relatorio_so_pode_ser_visto_pelo_usuario_de_id_1()
    {
        $this->criarAdministrador();
        [, $tokenDeOutroUsuario] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('GET', '/api/access_log', [], $tokenDeOutroUsuario);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Sem permissão']);
    }

    public function test_relatorio_traz_todas_as_secoes()
    {
        [, $token] = $this->criarAdministrador();

        $resposta = $this->chamarApi('GET', '/api/access_log', [], $token);

        $resposta->assertStatus(200)->assertJsonStructure([
            'dados', 'logDay', 'totalCustomerActive', 'totalCustomerSeller', 'totalAccessByUser',
            'totalDisplay', 'totalBrowser', 'totalDevice', 'totalLanguage',
        ]);
    }

    public function test_relatorio_lista_acessos_dos_ultimos_sete_dias_do_mais_recente_para_o_mais_antigo()
    {
        [$admin, $token] = $this->criarAdministrador();
        $this->criarLogDeAcesso($admin['id'], ['browser' => 'Antigo', 'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))]);
        $this->criarLogDeAcesso($admin['id'], ['browser' => 'Ontem', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $this->criarLogDeAcesso($admin['id'], ['browser' => 'Agora']);

        $resposta = $this->chamarApi('GET', '/api/access_log', [], $token);

        $this->assertSame(['Agora', 'Ontem'], array_column($resposta->json('dados'), 'browser'));
        $this->assertSame('Administrador', $resposta->json('dados.0.nome'));
    }

    public function test_relatorio_lista_em_ordem_alfabetica_quem_acessou_hoje()
    {
        [$admin, $token] = $this->criarAdministrador();
        $zeca = $this->criarUsuario(['nome' => 'Zeca']);
        $bia = $this->criarUsuario(['nome' => 'Bia']);
        $ausente = $this->criarUsuario(['nome' => 'Ausente']);
        $this->criarLogDeAcesso($zeca['id']);
        $this->criarLogDeAcesso($zeca['id']);
        $this->criarLogDeAcesso($bia['id']);
        $this->criarLogDeAcesso($ausente['id'], ['created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]);

        $resposta = $this->chamarApi('GET', '/api/access_log', [], $token);

        $this->assertSame(['Bia', 'Zeca'], array_column($resposta->json('logDay'), 'nome'));
    }

    public function test_relatorio_soma_clientes_ativos_e_vendas_por_usuario_do_maior_para_o_menor()
    {
        [$admin, $token] = $this->criarAdministrador();
        $vendedor = $this->criarUsuario(['nome' => 'Vendedor Forte']);
        $this->criarCliente($admin['id'], ['status' => 'a']);
        $this->criarCliente($vendedor['id'], ['status' => 'c']);
        $this->criarCliente($vendedor['id'], ['status' => 'c']);
        $this->criarCliente($vendedor['id'], ['status' => 'a']);
        $this->criarCliente($vendedor['id'], ['status' => 'c', 'bo_ativo' => 0]);

        $resposta = $this->chamarApi('GET', '/api/access_log', [], $token);

        $this->assertSame(
            [['id_usuario' => $vendedor['id'], 'nome' => 'Vendedor Forte', 'total' => 3], ['id_usuario' => $admin['id'], 'nome' => 'Administrador', 'total' => 1]],
            $resposta->json('totalCustomerActive')
        );
        $this->assertSame(
            [['id_usuario' => $vendedor['id'], 'nome' => 'Vendedor Forte', 'total' => 3]],
            $resposta->json('totalCustomerSeller')
        );
    }

    public function test_relatorio_soma_acessos_por_usuario_resolucao_navegador_dispositivo_e_idioma()
    {
        [$admin, $token] = $this->criarAdministrador();
        $this->criarLogDeAcesso($admin['id'], ['display_resolution' => '1920x1080', 'browser' => 'Chrome', 'device' => 'Linux', 'language' => 'pt-BR']);
        $this->criarLogDeAcesso($admin['id'], ['display_resolution' => '1920x1080', 'browser' => 'Chrome', 'device' => 'Linux', 'language' => 'pt-BR']);
        $this->criarLogDeAcesso($admin['id'], ['display_resolution' => '412x915', 'browser' => 'Safari', 'device' => 'iPhone', 'language' => 'en-US']);

        $resposta = $this->chamarApi('GET', '/api/access_log', [], $token);

        $this->assertSame([['id_user' => $admin['id'], 'nome' => 'Administrador', 'total' => 3]], $resposta->json('totalAccessByUser'));
        $this->assertSame(
            [['display_resolution' => '1920x1080', 'total' => 2], ['display_resolution' => '412x915', 'total' => 1]],
            $resposta->json('totalDisplay')
        );
        $this->assertSame([['browser' => 'Chrome', 'total' => 2], ['browser' => 'Safari', 'total' => 1]], $resposta->json('totalBrowser'));
        $this->assertSame([['device' => 'Linux', 'total' => 2], ['device' => 'iPhone', 'total' => 1]], $resposta->json('totalDevice'));
        $this->assertSame([['language' => 'pt-BR', 'total' => 2], ['language' => 'en-US', 'total' => 1]], $resposta->json('totalLanguage'));
    }

    public function test_consulta_de_um_registro_de_acesso_por_id()
    {
        [$admin, $token] = $this->criarAdministrador();
        $log = $this->criarLogDeAcesso($admin['id'], ['browser' => 'Firefox']);

        $resposta = $this->chamarApi('GET', "/api/access_log/{$log['id']}", [], $token);

        $resposta->assertStatus(200)->assertJson(['id' => $log['id'], 'browser' => 'Firefox']);
        $this->assertSame(1, DB::table('usuario_log')->count());
    }
}
