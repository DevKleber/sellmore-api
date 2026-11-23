<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ClientesPaginacaoTest extends ApiTestCase
{
    private const TODOS_OS_STATUS = [
        'boShowAberto' => 'true',
        'boShowLigarDepois' => 'true',
        'boShowProblemasCartao' => 'true',
        'boShowNaotemInteresse' => 'true',
        'boShowComprou' => 'true',
    ];

    private const LEADS_POR_PAGINA_PADRAO = 8;
    private const MAXIMO_DE_LEADS_POR_PAGINA = 50;

    private function listar(string $token, array $parametros = [])
    {
        return $this->chamarApi('GET', '/api/customers?'.http_build_query(self::TODOS_OS_STATUS + $parametros), [], $token);
    }

    private function criarLeads(int $idUsuario, array $nomes): void
    {
        foreach ($nomes as $nome) {
            $this->criarCliente($idUsuario, ['name' => $nome]);
        }
    }

    private function nomes($resposta): array
    {
        return array_column($resposta->json('arCustomers'), 'name');
    }

    public function test_sem_pagina_a_listagem_devolve_todos_os_leads_e_nao_informa_total()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], ['Ana', 'Bia', 'Caio']);

        $resposta = $this->listar($token);

        $this->assertSame(['Ana', 'Bia', 'Caio'], $this->nomes($resposta));
        $this->assertArrayNotHasKey('total', $resposta->json());
    }

    public function test_cada_pagina_traz_a_sua_fatia_e_o_total_de_leads()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], ['Ana', 'Bia', 'Caio', 'Davi', 'Eva']);

        $primeira = $this->listar($token, ['page' => 1, 'perPage' => 2]);
        $segunda = $this->listar($token, ['page' => 2, 'perPage' => 2]);
        $terceira = $this->listar($token, ['page' => 3, 'perPage' => 2]);

        $this->assertSame(['Ana', 'Bia'], $this->nomes($primeira));
        $this->assertSame(['Caio', 'Davi'], $this->nomes($segunda));
        $this->assertSame(['Eva'], $this->nomes($terceira));
        $this->assertSame([5, 5, 5], [$primeira->json('total'), $segunda->json('total'), $terceira->json('total')]);
    }

    public function test_pagina_alem_do_fim_vem_vazia_com_o_total()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], ['Ana', 'Bia']);

        $resposta = $this->listar($token, ['page' => 5, 'perPage' => 2]);

        $resposta->assertStatus(200);
        $this->assertSame([], $this->nomes($resposta));
        $this->assertSame(2, $resposta->json('total'));
    }

    public function test_pagina_respeita_a_ordem_escolhida()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], ['Ana', 'Bia', 'Caio']);
        $ordem = ['orderBy' => json_encode(['column' => 'name', 'type' => 'desc'])];

        $resposta = $this->listar($token, $ordem + ['page' => 1, 'perPage' => 2]);

        $this->assertSame(['Caio', 'Bia'], $this->nomes($resposta));
    }

    public function test_leads_com_o_mesmo_nome_nao_se_repetem_nem_somem_entre_paginas()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], ['Igual', 'Igual', 'Igual', 'Igual']);

        $primeira = $this->listar($token, ['page' => 1, 'perPage' => 2]);
        $segunda = $this->listar($token, ['page' => 2, 'perPage' => 2]);

        $ids = array_merge(array_column($primeira->json('arCustomers'), 'id'), array_column($segunda->json('arCustomers'), 'id'));
        $this->assertCount(4, array_unique($ids));
    }

    public function test_lead_da_pagina_vem_com_seus_telefones_e_referidos()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], ['Ana']);
        $bia = $this->criarCliente($usuario['id'], ['name' => 'Bia']);
        $this->criarTelefone($bia['id'], '62999990001');
        $referido = $this->criarCliente($usuario['id'], ['name' => 'Referido da Bia', 'id_parent' => $bia['id']]);
        $this->criarTelefone($referido['id'], '62999990002');

        $resposta = $this->listar($token, ['page' => 2, 'perPage' => 1]);

        $lead = $resposta->json('arCustomers.0');
        $this->assertSame('Bia', $lead['name']);
        $this->assertSame(['62999990001'], array_column($lead['phones'], 'phone'));
        $this->assertSame(['Referido da Bia'], array_column($lead['referidos'], 'name'));
        $this->assertSame(['62999990002'], array_column($lead['referidos'][0]['phones'], 'phone'));
    }

    public function test_estatisticas_contam_todos_os_clientes_e_nao_so_os_da_pagina()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], ['Ana', 'Bia', 'Caio']);

        $resposta = $this->listar($token, ['page' => 1, 'perPage' => 1]);

        $this->assertSame(3, $resposta->json('statistics.a'));
    }

    public function test_sem_tamanho_informado_a_pagina_tem_oito_leads()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], array_map('strval', range(10, 10 + self::LEADS_POR_PAGINA_PADRAO)));

        $resposta = $this->listar($token, ['page' => 1]);

        $this->assertCount(self::LEADS_POR_PAGINA_PADRAO, $resposta->json('arCustomers'));
    }

    public function test_tamanho_da_pagina_tem_limite()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarLeads($usuario['id'], array_map('strval', range(100, 100 + self::MAXIMO_DE_LEADS_POR_PAGINA)));

        $resposta = $this->listar($token, ['page' => 1, 'perPage' => 1000]);

        $this->assertCount(self::MAXIMO_DE_LEADS_POR_PAGINA, $resposta->json('arCustomers'));
    }
}
