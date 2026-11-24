<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ResultadosTest extends ApiTestCase
{
    private const AGORA = '2026-11-18 10:00:00';
    private const NESTA_SEMANA = '2026-11-17 09:00:00';
    private const NESTE_MES = '2026-11-03 09:00:00';
    private const MES_PASSADO = '2026-10-10 09:00:00';

    private $usuario;
    private $token;
    private $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::AGORA);
        [$this->usuario, $this->token] = $this->criarUsuarioAutenticado();
        $this->lead = $this->criarCliente($this->usuario['id'], ['created_at' => self::MES_PASSADO]);
    }

    private function criarReferido(string $status, string $criadoEm, ?string $statusAlteradoEm = null, ?int $idUsuario = null): array
    {
        return $this->criarCliente($idUsuario ?? $this->usuario['id'], [
            'id_parent' => $this->lead['id'],
            'status' => $status,
            'created_at' => $criadoEm,
            'status_alterado_em' => $statusAlteradoEm ?? $criadoEm,
        ]);
    }

    private function resultados(): array
    {
        $resposta = $this->chamarApi('GET', '/api/resultados', [], $this->token);
        $resposta->assertStatus(200);

        return $resposta->json('dados');
    }

    public function test_conta_sem_clientes_tem_tudo_zerado()
    {
        [, $tokenNovo] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('GET', '/api/resultados', [], $tokenNovo);

        $zerado = ['indicacoes' => 0, 'atendidos' => 0, 'vendas' => 0, 'conversao' => 0];
        $resposta->assertStatus(200)->assertJson(['dados' => ['semana' => $zerado, 'mes' => $zerado, 'total' => $zerado]]);
    }

    public function test_separa_indicacoes_atendidos_e_vendas_por_semana_mes_e_total()
    {
        $this->criarReferido('a', self::NESTA_SEMANA);
        $this->criarReferido('c', self::NESTE_MES, self::NESTA_SEMANA);
        $this->criarReferido('n', self::NESTE_MES);
        $this->criarReferido('c', self::MES_PASSADO);

        $resultados = $this->resultados();

        $this->assertSame(['indicacoes' => 1, 'atendidos' => 1, 'vendas' => 1, 'conversao' => 100], $resultados['semana']);
        $this->assertSame(['indicacoes' => 3, 'atendidos' => 2, 'vendas' => 1, 'conversao' => 50], $resultados['mes']);
        $this->assertSame(['indicacoes' => 4, 'atendidos' => 3, 'vendas' => 2, 'conversao' => 67], $resultados['total']);
    }

    public function test_lead_sem_quem_indicou_nao_conta_como_indicacao()
    {
        $this->assertSame(1, $this->resultados()['clientes']);
        $this->assertSame(0, $this->resultados()['total']['indicacoes']);
    }

    public function test_nao_soma_clientes_de_outro_usuario()
    {
        $outro = $this->criarUsuario();
        $this->criarReferido('c', self::NESTA_SEMANA, null, $outro['id']);

        $this->assertSame(['indicacoes' => 0, 'atendidos' => 0, 'vendas' => 0, 'conversao' => 0], $this->resultados()['total']);
    }

    public function test_historico_traz_os_ultimos_seis_meses_do_mais_antigo_para_o_atual()
    {
        $this->criarReferido('c', self::NESTE_MES);
        $this->criarReferido('a', self::MES_PASSADO);
        $this->criarReferido('c', '2025-01-01 09:00:00');

        $historico = $this->resultados()['historico'];

        $this->assertSame(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'], array_column($historico, 'mes'));
        $this->assertSame(['mes' => '2026-10', 'indicacoes' => 1, 'vendas' => 0], $historico[4]);
        $this->assertSame(['mes' => '2026-11', 'indicacoes' => 1, 'vendas' => 1], $historico[5]);
    }

    public function test_mudar_o_status_registra_o_momento_da_mudanca()
    {
        $referido = $this->criarReferido('a', self::MES_PASSADO);

        $this->chamarApi('PUT', "/api/status/customers/{$referido['id']}", ['status' => 'c'], $this->token)->assertStatus(200);

        $this->assertSame(self::AGORA, $this->registro('customers', $referido['id'])['status_alterado_em']);
        $this->assertSame(1, $this->resultados()['semana']['vendas']);
    }

    public function test_salvar_sem_mudar_o_status_mantem_o_momento_da_ultima_mudanca()
    {
        $referido = $this->criarReferido('c', self::MES_PASSADO);

        $this->chamarApi('PUT', "/api/customers/{$referido['id']}", [
            'name' => 'Nome Corrigido',
            'status' => 'c',
            'id_parent' => $this->lead['id'],
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ], $this->token)->assertStatus(200);

        $this->assertSame(self::MES_PASSADO, $this->registro('customers', $referido['id'])['status_alterado_em']);
    }

    public function test_cliente_novo_ja_nasce_com_o_momento_do_status()
    {
        $resposta = $this->chamarApi('POST', '/api/customers', [
            'name' => 'Novo',
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ], $this->token);

        $this->assertSame(self::AGORA, $this->registro('customers', $resposta->json('dados.id'))['status_alterado_em']);
    }
}
