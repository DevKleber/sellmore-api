<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ClientesListagemTest extends ApiTestCase
{
    private const TODOS_OS_STATUS = [
        'boShowAberto' => 'true',
        'boShowLigarDepois' => 'true',
        'boShowProblemasCartao' => 'true',
        'boShowNaotemInteresse' => 'true',
        'boShowComprou' => 'true',
    ];

    private function listar(string $token, array $filtros = self::TODOS_OS_STATUS)
    {
        return $this->chamarApi('GET', '/api/customers?'.http_build_query($filtros), [], $token);
    }

    private function nomes(array $clientes): array
    {
        return array_column($clientes, 'name');
    }

    public function test_sem_nenhum_filtro_de_status_a_listagem_vem_vazia()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id']);

        $resposta = $this->listar($token, []);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Não existe Customers']);
    }

    public function test_listagem_traz_clientes_raiz_com_telefones_referidos_e_estatisticas()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $raiz = $this->criarCliente($usuario['id'], ['name' => 'Ana Raiz']);
        $this->criarTelefone($raiz['id'], '62999990001');
        $referido = $this->criarCliente($usuario['id'], ['name' => 'Bruno Referido', 'id_parent' => $raiz['id']]);
        $this->criarTelefone($referido['id'], '62999990002');

        $resposta = $this->listar($token);

        $resposta->assertStatus(200)->assertJsonStructure([
            'arCustomers' => [['id', 'name', 'status', 'id_usuario', 'id_parent', 'observation', 'bo_ativo', 'bo_preference', 'phones', 'referidos']],
            'statistics' => ['a', 'pc', 'ld', 'n', 'c'],
        ]);
        $clientes = $resposta->json('arCustomers');
        $this->assertSame(['Ana Raiz'], $this->nomes($clientes));
        $this->assertSame(['62999990001'], array_column($clientes[0]['phones'], 'phone'));
        $this->assertSame(['Bruno Referido'], $this->nomes($clientes[0]['referidos']));
        $this->assertSame(['62999990002'], array_column($clientes[0]['referidos'][0]['phones'], 'phone'));
    }

    public function test_listagem_so_traz_clientes_do_usuario_logado()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id'], ['name' => 'Meu Cliente']);
        $outro = $this->criarUsuario();
        $this->criarCliente($outro['id'], ['name' => 'Cliente do Outro']);

        $resposta = $this->listar($token);

        $this->assertSame(['Meu Cliente'], $this->nomes($resposta->json('arCustomers')));
        $this->assertSame(1, $resposta->json('statistics.a'));
    }

    public function test_clientes_raiz_vem_em_ordem_alfabetica_por_padrao()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id'], ['name' => 'Carla']);
        $this->criarCliente($usuario['id'], ['name' => 'Alice']);
        $this->criarCliente($usuario['id'], ['name' => 'Beatriz']);

        $resposta = $this->listar($token);

        $this->assertSame(['Alice', 'Beatriz', 'Carla'], $this->nomes($resposta->json('arCustomers')));
    }

    public function test_ordenacao_pode_ser_escolhida_por_coluna_e_direcao()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id'], ['name' => 'Antigo', 'created_at' => '2024-01-01 10:00:00']);
        $this->criarCliente($usuario['id'], ['name' => 'Recente', 'created_at' => '2024-03-01 10:00:00']);
        $this->criarCliente($usuario['id'], ['name' => 'Do Meio', 'created_at' => '2024-02-01 10:00:00']);
        $ordenacao = ['orderBy' => json_encode(['column' => 'created_at', 'type' => 'desc'])];

        $resposta = $this->listar($token, self::TODOS_OS_STATUS + $ordenacao);

        $this->assertSame(['Recente', 'Do Meio', 'Antigo'], $this->nomes($resposta->json('arCustomers')));
    }

    public function test_filtros_de_status_valem_para_os_referidos()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $raiz = $this->criarCliente($usuario['id'], ['name' => 'Raiz']);
        $this->criarCliente($usuario['id'], ['name' => 'Aberto', 'status' => 'a', 'id_parent' => $raiz['id']]);
        $this->criarCliente($usuario['id'], ['name' => 'Ligar Depois', 'status' => 'ld', 'id_parent' => $raiz['id']]);
        $this->criarCliente($usuario['id'], ['name' => 'Comprou', 'status' => 'c', 'id_parent' => $raiz['id']]);

        $resposta = $this->listar($token, ['boShowLigarDepois' => 'true', 'boShowComprou' => 'true']);

        $this->assertSame(['Comprou', 'Ligar Depois'], $this->nomes($resposta->json('arCustomers.0.referidos')));
    }

    public function test_filtro_de_status_so_lista_o_lead_que_esta_no_status_ou_tem_referido_nele()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id'], ['name' => 'A Lead Sem Interesse', 'status' => 'n']);
        $this->criarCliente($usuario['id'], ['name' => 'B Lead Para Ligar', 'status' => 'ld']);
        $comReferido = $this->criarCliente($usuario['id'], ['name' => 'C Lead Com Referido Para Ligar', 'status' => 'c']);
        $this->criarCliente($usuario['id'], ['name' => 'Referido Para Ligar', 'status' => 'ld', 'id_parent' => $comReferido['id']]);
        $semNinguem = $this->criarCliente($usuario['id'], ['name' => 'D Lead Com Referido Aberto', 'status' => 'a']);
        $this->criarCliente($usuario['id'], ['name' => 'Referido Aberto', 'status' => 'a', 'id_parent' => $semNinguem['id']]);

        $resposta = $this->listar($token, ['boShowLigarDepois' => 'true', 'page' => 1]);

        $this->assertSame(['B Lead Para Ligar', 'C Lead Com Referido Para Ligar'], $this->nomes($resposta->json('arCustomers')));
        $this->assertSame(2, $resposta->json('total'));
    }

    public function test_referido_arquivado_no_status_nao_faz_o_lead_aparecer_no_filtro()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $lead = $this->criarCliente($usuario['id'], ['name' => 'Lead Aberto', 'status' => 'a']);
        $this->criarCliente($usuario['id'], ['name' => 'Arquivado Para Ligar', 'status' => 'ld', 'bo_ativo' => 0, 'id_parent' => $lead['id']]);

        $resposta = $this->listar($token, ['boShowLigarDepois' => 'true']);

        $this->assertSame([], $this->nomes($resposta->json('arCustomers')));
    }

    public function test_com_todos_os_status_ligados_todo_lead_aparece()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id'], ['name' => 'Raiz Sem Interesse', 'status' => 'n']);

        $resposta = $this->listar($token);

        $this->assertSame(['Raiz Sem Interesse'], $this->nomes($resposta->json('arCustomers')));
    }

    public function test_referidos_arquivados_ficam_fora_mas_raiz_arquivada_continua_listada()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $raiz = $this->criarCliente($usuario['id'], ['name' => 'Raiz Arquivada', 'bo_ativo' => 0]);
        $this->criarCliente($usuario['id'], ['name' => 'Referido Ativo', 'id_parent' => $raiz['id']]);
        $this->criarCliente($usuario['id'], ['name' => 'Referido Arquivado', 'id_parent' => $raiz['id'], 'bo_ativo' => 0]);

        $resposta = $this->listar($token);

        $this->assertSame(['Raiz Arquivada'], $this->nomes($resposta->json('arCustomers')));
        $this->assertSame(['Referido Ativo'], $this->nomes($resposta->json('arCustomers.0.referidos')));
    }

    public function test_referidos_vem_ordenados_por_status_preferencia_e_nome()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $raiz = $this->criarCliente($usuario['id'], ['name' => 'Raiz']);
        $filhoDe = ['id_parent' => $raiz['id']];
        $this->criarCliente($usuario['id'], $filhoDe + ['name' => 'Zeca Aberto', 'status' => 'a']);
        $this->criarCliente($usuario['id'], $filhoDe + ['name' => 'Bia Aberta', 'status' => 'a']);
        $this->criarCliente($usuario['id'], $filhoDe + ['name' => 'Zuleica Preferida', 'status' => 'a', 'bo_preference' => 1]);
        $this->criarCliente($usuario['id'], $filhoDe + ['name' => 'Ana Comprou', 'status' => 'c']);

        $resposta = $this->listar($token);

        $this->assertSame(
            ['Zuleica Preferida', 'Bia Aberta', 'Zeca Aberto', 'Ana Comprou'],
            $this->nomes($resposta->json('arCustomers.0.referidos'))
        );
    }

    public function test_quem_tem_referidos_aparece_como_raiz_mesmo_sendo_referido_de_outro()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $avo = $this->criarCliente($usuario['id'], ['name' => 'Avo']);
        $pai = $this->criarCliente($usuario['id'], ['name' => 'Pai', 'id_parent' => $avo['id']]);
        $this->criarCliente($usuario['id'], ['name' => 'Neto', 'id_parent' => $pai['id']]);

        $resposta = $this->listar($token);

        $this->assertSame(['Avo', 'Pai'], $this->nomes($resposta->json('arCustomers')));
        $this->assertSame(['Neto'], $this->nomes($resposta->json('arCustomers.1.referidos')));
    }

    public function test_estatisticas_contam_ativos_por_status_e_compras_mesmo_arquivadas()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id'], ['status' => 'a']);
        $this->criarCliente($usuario['id'], ['status' => 'a']);
        $this->criarCliente($usuario['id'], ['status' => 'a', 'bo_ativo' => 0]);
        $this->criarCliente($usuario['id'], ['status' => 'ld']);
        $this->criarCliente($usuario['id'], ['status' => 'pc']);
        $this->criarCliente($usuario['id'], ['status' => 'n', 'bo_ativo' => 0]);
        $this->criarCliente($usuario['id'], ['status' => 'c']);
        $this->criarCliente($usuario['id'], ['status' => 'c', 'bo_ativo' => 0]);

        $resposta = $this->listar($token);

        $this->assertSame(['a' => 2, 'pc' => 1, 'ld' => 1, 'n' => 0, 'c' => 2], $resposta->json('statistics'));
    }

    public function test_listagem_de_ligar_depois_traz_so_ativos_com_esse_status_em_ordem_alfabetica()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $zelia = $this->criarCliente($usuario['id'], ['name' => 'Zelia', 'status' => 'ld']);
        $this->criarTelefone($zelia['id'], '62999990009');
        $this->criarCliente($usuario['id'], ['name' => 'Amanda', 'status' => 'ld']);
        $this->criarCliente($usuario['id'], ['name' => 'Arquivada', 'status' => 'ld', 'bo_ativo' => 0]);
        $this->criarCliente($usuario['id'], ['name' => 'Aberta', 'status' => 'a']);
        $outro = $this->criarUsuario();
        $this->criarCliente($outro['id'], ['name' => 'Do Outro', 'status' => 'ld']);

        $resposta = $this->chamarApi('GET', '/api/getCustomersLd', [], $token);

        $resposta->assertStatus(200);
        $this->assertSame(['Amanda', 'Zelia'], $this->nomes($resposta->json()));
        $this->assertSame(['62999990009'], array_column($resposta->json('1.phones'), 'phone'));
    }
}
