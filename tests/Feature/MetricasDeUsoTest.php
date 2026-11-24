<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class MetricasDeUsoTest extends ApiTestCase
{
    private const AGORA = '2026-11-18 10:00:00';
    private const SEMANA_ATUAL = '2026-11-16';
    private const SEMANA_ANTERIOR = '2026-11-09';
    private const SEMANAS_ACOMPANHADAS = 8;

    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::AGORA);
        [, $this->token] = $this->criarUsuarioAutenticado(['created_at' => '2020-01-01 08:00:00']);
    }

    private function metricas(): array
    {
        $resposta = $this->chamarApi('GET', '/api/metricas-de-uso', [], $this->token);
        $resposta->assertStatus(200);

        return $resposta->json('dados');
    }

    private function semana(string $inicio): array
    {
        $semanas = array_column($this->metricas()['semanas'], null, 'inicio');

        return $semanas[$inicio];
    }

    public function test_so_o_administrador_ve_as_metricas()
    {
        [, $tokenComum] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('GET', '/api/metricas-de-uso', [], $tokenComum);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Sem permissão']);
    }

    public function test_conta_as_contas_criadas_no_total_e_nos_ultimos_dias()
    {
        $this->criarUsuario(['created_at' => '2026-11-17 08:00:00']);
        $this->criarUsuario(['created_at' => '2026-10-25 08:00:00']);

        $this->assertSame(['total' => 3, 'ultimos7Dias' => 1, 'ultimos30Dias' => 2], $this->metricas()['contas']);
    }

    public function test_ativacao_conta_quem_cadastrou_cliente_e_quem_recebeu_indicacao()
    {
        $comLead = $this->criarUsuario();
        $comIndicacao = $this->criarUsuario();
        $this->criarUsuario();
        $this->criarCliente($comLead['id']);
        $lead = $this->criarCliente($comIndicacao['id']);
        $this->criarCliente($comIndicacao['id'], ['id_parent' => $lead['id']]);

        $this->assertSame(['comCliente' => 2, 'comIndicacao' => 1], $this->metricas()['ativacao']);
    }

    public function test_ativos_conta_cada_conta_uma_vez_pelos_acessos_recentes()
    {
        $recente = $this->criarUsuario();
        $doMes = $this->criarUsuario();
        $this->criarLogDeAcesso($recente['id'], ['created_at' => '2026-11-17 08:00:00']);
        $this->criarLogDeAcesso($recente['id'], ['created_at' => '2026-11-18 08:00:00']);
        $this->criarLogDeAcesso($doMes['id'], ['created_at' => '2026-10-30 08:00:00']);

        $this->assertSame(['ultimos7Dias' => 1, 'ultimos30Dias' => 2], $this->metricas()['ativos']);
    }

    public function test_funil_traz_oito_semanas_terminando_na_atual()
    {
        $semanas = $this->metricas()['semanas'];

        $this->assertCount(self::SEMANAS_ACOMPANHADAS, $semanas);
        $this->assertSame(self::SEMANA_ATUAL, end($semanas)['inicio']);
    }

    public function test_funil_mostra_quem_criou_conta_cadastrou_cliente_e_voltou_depois_de_sete_dias()
    {
        $voltou = $this->criarUsuario(['created_at' => '2026-11-09 08:00:00']);
        $soCadastrou = $this->criarUsuario(['created_at' => '2026-11-10 08:00:00']);
        $this->criarUsuario(['created_at' => '2026-11-11 08:00:00']);
        $this->criarCliente($voltou['id']);
        $this->criarCliente($soCadastrou['id']);
        $this->criarLogDeAcesso($voltou['id'], ['created_at' => '2026-11-17 08:00:00']);
        $this->criarLogDeAcesso($soCadastrou['id'], ['created_at' => '2026-11-12 08:00:00']);

        $this->assertSame(
            ['inicio' => self::SEMANA_ANTERIOR, 'criaram' => 3, 'cadastraramCliente' => 2, 'voltaram' => 1],
            $this->semana(self::SEMANA_ANTERIOR)
        );
    }

    public function test_hoje_resume_acessos_contas_novas_clientes_e_vendas_do_dia()
    {
        $vendedor = $this->criarUsuario(['created_at' => '2026-11-18 08:00:00']);
        $this->criarLogDeAcesso($vendedor['id'], ['created_at' => '2026-11-18 08:30:00']);
        $this->criarLogDeAcesso($vendedor['id'], ['created_at' => '2026-11-17 08:30:00']);
        $this->criarCliente($vendedor['id'], ['created_at' => '2026-11-18 09:00:00', 'status' => 'c', 'status_alterado_em' => '2026-11-18 09:30:00']);
        $this->criarCliente($vendedor['id'], ['created_at' => '2026-11-10 09:00:00', 'status' => 'c', 'status_alterado_em' => '2026-11-10 09:30:00']);

        $this->assertSame(
            ['contasQueAcessaram' => 1, 'contasNovas' => 1, 'clientesCadastrados' => 1, 'vendas' => 1],
            $this->metricas()['hoje']
        );
    }

    public function test_acessos_por_dia_traz_catorze_dias_contando_cada_conta_uma_vez()
    {
        $vendedor = $this->criarUsuario();
        $outro = $this->criarUsuario();
        $this->criarLogDeAcesso($vendedor['id'], ['created_at' => '2026-11-17 08:00:00']);
        $this->criarLogDeAcesso($vendedor['id'], ['created_at' => '2026-11-17 18:00:00']);
        $this->criarLogDeAcesso($outro['id'], ['created_at' => '2026-11-17 09:00:00']);

        $dias = $this->metricas()['acessosPorDia'];

        $this->assertCount(14, $dias);
        $this->assertSame(['dia' => '2026-11-05', 'contas' => 0], $dias[0]);
        $this->assertSame(['dia' => '2026-11-17', 'contas' => 2], $dias[12]);
        $this->assertSame(['dia' => '2026-11-18', 'contas' => 0], $dias[13]);
    }

    public function test_contas_recentes_vem_da_mais_nova_para_a_mais_antiga_com_clientes_e_ultimo_acesso()
    {
        $antiga = $this->criarUsuario(['nome' => 'Conta Antiga']);
        $nova = $this->criarUsuario(['nome' => 'Conta Nova']);
        $this->criarCliente($antiga['id']);
        $this->criarCliente($antiga['id']);
        $this->criarLogDeAcesso($antiga['id'], ['created_at' => '2026-11-16 08:00:00']);
        $this->criarLogDeAcesso($antiga['id'], ['created_at' => '2026-11-17 08:00:00']);

        $contas = $this->metricas()['contasRecentes'];

        $this->assertSame(['Conta Nova', 'Conta Antiga'], array_slice(array_column($contas, 'nome'), 0, 2));
        $this->assertSame(0, $contas[0]['clientes']);
        $this->assertNull($contas[0]['ultimoAcesso']);
        $this->assertSame(2, $contas[1]['clientes']);
        $this->assertSame('2026-11-17 08:00:00', $contas[1]['ultimoAcesso']);
        $this->assertArrayNotHasKey('password', $contas[0]);
    }
}
