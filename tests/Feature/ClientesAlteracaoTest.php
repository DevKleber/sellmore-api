<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ClientesAlteracaoTest extends ApiTestCase
{
    public function test_alteracao_atualiza_os_dados_e_substitui_os_telefones()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id'], ['name' => 'Nome Antigo']);
        $this->criarTelefone($cliente['id'], '62999990001');

        $resposta = $this->chamarApi('PUT', "/api/customers/{$cliente['id']}", [
            'name' => 'Nome Novo',
            'observation' => 'Ligar de manha',
            'status' => 'ld',
            'telefones' => [$this->telefoneParaEnvio('62999990002'), $this->telefoneParaEnvio('62999990003')],
        ], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Atualizado com sucesso']);
        $gravado = $this->registro('customers', $cliente['id']);
        $this->assertSame('Nome Novo', $gravado['name']);
        $this->assertSame('Ligar de manha', $gravado['observation']);
        $this->assertSame('ld', $gravado['status']);
        $this->assertSame(['62999990002', '62999990003'], $this->telefonesGravadosDe($cliente['id']));
    }

    public function test_alteracao_pode_manter_o_proprio_telefone()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);
        $this->criarTelefone($cliente['id'], '62999990001');

        $resposta = $this->chamarApi('PUT', "/api/customers/{$cliente['id']}", [
            'name' => 'Mesmo Telefone',
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ], $token);

        $resposta->assertStatus(200);
        $this->assertSame(['62999990001'], $this->telefonesGravadosDe($cliente['id']));
    }

    public function test_alteracao_sem_status_volta_o_cliente_para_aberto()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id'], ['status' => 'c']);

        $this->chamarApi('PUT', "/api/customers/{$cliente['id']}", [
            'name' => 'Sem Status',
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ], $token)->assertStatus(200);

        $this->assertSame('a', $this->registro('customers', $cliente['id'])['status']);
    }

    public function test_alteracao_com_telefone_de_outro_cliente_e_recusada_e_nada_muda()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $dono = $this->criarCliente($usuario['id'], ['name' => 'Dona do Numero']);
        $this->criarTelefone($dono['id'], '62999990009');
        $cliente = $this->criarCliente($usuario['id'], ['name' => 'Nome Antigo']);
        $this->criarTelefone($cliente['id'], '62999990001');

        $resposta = $this->chamarApi('PUT', "/api/customers/{$cliente['id']}", [
            'name' => 'Nome Novo',
            'telefones' => [$this->telefoneParaEnvio('62999990009')],
        ], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Número de telefone já existe (Dona do Numero)']);
        $this->assertSame('Nome Antigo', $this->registro('customers', $cliente['id'])['name']);
        $this->assertSame(['62999990001'], $this->telefonesGravadosDe($cliente['id']));
    }

    /**
     * @dataProvider statusPossiveis
     */
    public function test_troca_de_status_grava_o_novo_status(string $status)
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id'], ['status' => 'a', 'name' => 'Inalterado']);

        $resposta = $this->chamarApi('PUT', "/api/status/customers/{$cliente['id']}", ['status' => $status, 'name' => 'Ignorado'], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Atualizado com sucesso']);
        $gravado = $this->registro('customers', $cliente['id']);
        $this->assertSame($status, $gravado['status']);
        $this->assertSame('Inalterado', $gravado['name']);
    }

    public function statusPossiveis(): array
    {
        return [
            'ligar depois' => ['ld'],
            'problemas com cartao' => ['pc'],
            'nao tem interesse' => ['n'],
            'comprou' => ['c'],
        ];
    }

    public function test_troca_de_status_de_cliente_inexistente_e_recusada()
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('PUT', '/api/status/customers/999', ['status' => 'c'], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Customers Não encontrado']);
    }

    public function test_arquivar_desativa_o_cliente_sem_apagar()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);

        $resposta = $this->chamarApi('DELETE', "/api/customers/{$cliente['id']}", [], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Referido arquivado']);
        $this->assertEquals(0, $this->registro('customers', $cliente['id'])['bo_ativo']);
    }

    public function test_arquivar_cliente_de_outro_usuario_e_recusado()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $clienteDoOutro = $this->criarCliente($outro['id']);

        $resposta = $this->chamarApi('DELETE', "/api/customers/{$clienteDoOutro['id']}", [], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
        $this->assertEquals(1, $this->registro('customers', $clienteDoOutro['id'])['bo_ativo']);
    }

    public function test_reativar_volta_o_cliente_arquivado()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id'], ['bo_ativo' => 0]);

        $resposta = $this->chamarApi('PUT', "/api/customersActivate/{$cliente['id']}", [], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Referido ativado']);
        $this->assertEquals(1, $this->registro('customers', $cliente['id'])['bo_ativo']);
    }

    public function test_reativar_cliente_de_outro_usuario_e_recusado()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $clienteDoOutro = $this->criarCliente($outro['id'], ['bo_ativo' => 0]);

        $resposta = $this->chamarApi('PUT', "/api/customersActivate/{$clienteDoOutro['id']}", [], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
    }

    public function test_marcar_e_desmarcar_preferencia()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);

        $marcar = $this->chamarApi('POST', '/api/preference', ['id' => $cliente['id'], 'bo_preference' => true], $token);
        $marcado = $this->registro('customers', $cliente['id'])['bo_preference'];
        $desmarcar = $this->chamarApi('POST', '/api/preference', ['id' => $cliente['id'], 'bo_preference' => false], $token);
        $desmarcado = $this->registro('customers', $cliente['id'])['bo_preference'];

        $marcar->assertStatus(200)->assertExactJson(['response' => 'Referido arquivado']);
        $desmarcar->assertStatus(200);
        $this->assertEquals(1, $marcado);
        $this->assertEquals(0, $desmarcado);
    }

    public function test_preferencia_em_cliente_de_outro_usuario_e_recusada()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $clienteDoOutro = $this->criarCliente($outro['id']);

        $resposta = $this->chamarApi('POST', '/api/preference', ['id' => $clienteDoOutro['id'], 'bo_preference' => true], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
    }
}
