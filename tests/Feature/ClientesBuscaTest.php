<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ClientesBuscaTest extends ApiTestCase
{
    private function buscar(string $token, string $termo)
    {
        return $this->chamarApi('GET', '/api/search?'.http_build_query(['q' => $termo]), [], $token);
    }

    private function clienteComTelefone(int $idUsuario, string $nome, string $numero, array $atributos = []): array
    {
        $cliente = $this->criarCliente($idUsuario, ['name' => $nome] + $atributos);
        $this->criarTelefone($cliente['id'], $numero);

        return $cliente;
    }

    public function test_busca_por_parte_do_nome()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->clienteComTelefone($usuario['id'], 'Fernanda Souza', '62999990001');
        $this->clienteComTelefone($usuario['id'], 'Ricardo Lima', '62999990002');

        $resposta = $this->buscar($token, 'nanda');

        $resposta->assertStatus(200);
        $this->assertSame(['Fernanda Souza'], array_column($resposta->json(), 'name'));
        $this->assertSame(['62999990001'], array_column($resposta->json('0.phones'), 'phone'));
    }

    public function test_busca_por_telefone_ignora_a_mascara_digitada()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->clienteComTelefone($usuario['id'], 'Fernanda Souza', '62999990001');

        $resposta = $this->buscar($token, '(62)99999-0001');

        $this->assertSame(['Fernanda Souza'], array_column($resposta->json(), 'name'));
    }

    public function test_busca_por_observacao()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->clienteComTelefone($usuario['id'], 'Fernanda Souza', '62999990001', ['observation' => 'Prefere contato a noite']);

        $resposta = $this->buscar($token, 'contato a noite');

        $this->assertSame(['Fernanda Souza'], array_column($resposta->json(), 'name'));
    }

    public function test_busca_vem_em_ordem_alfabetica_e_sem_repetir_cliente_com_varios_telefones()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $silvia = $this->clienteComTelefone($usuario['id'], 'Silvia Prado', '62999990001');
        $this->criarTelefone($silvia['id'], '62999990002');
        $this->clienteComTelefone($usuario['id'], 'Marcos Prado', '62999990003');

        $resposta = $this->buscar($token, 'Prado');

        $this->assertSame(['Marcos Prado', 'Silvia Prado'], array_column($resposta->json(), 'name'));
        $this->assertCount(2, $resposta->json('1.phones'));
    }

    public function test_busca_vazia_devolve_lista_vazia()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->clienteComTelefone($usuario['id'], 'Fernanda Souza', '62999990001');

        $resposta = $this->buscar($token, '');

        $resposta->assertStatus(200)->assertExactJson([]);
    }

    public function test_busca_nao_encontra_cliente_de_outro_usuario()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $this->clienteComTelefone($outro['id'], 'Fernanda Souza', '62999990001');

        $resposta = $this->buscar($token, 'Fernanda');

        $resposta->assertStatus(200)->assertExactJson([]);
    }

    /**
     * A busca junta clientes com telefones, entao quem nao tem telefone nunca aparece.
     */
    public function test_busca_nao_encontra_cliente_sem_telefone()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarCliente($usuario['id'], ['name' => 'Sem Telefone']);

        $resposta = $this->buscar($token, 'Sem Telefone');

        $resposta->assertStatus(200)->assertExactJson([]);
    }

    public function test_busca_inclui_clientes_arquivados()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->clienteComTelefone($usuario['id'], 'Arquivada Silva', '62999990001', ['bo_ativo' => 0]);

        $resposta = $this->buscar($token, 'Arquivada');

        $this->assertSame(['Arquivada Silva'], array_column($resposta->json(), 'name'));
    }
}
