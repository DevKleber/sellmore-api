<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class ClientesCadastroTest extends ApiTestCase
{
    private function cadastrar(string $token, array $dados)
    {
        return $this->chamarApi('POST', '/api/customers', $dados, $token);
    }

    public function test_cadastro_grava_o_cliente_para_o_usuario_logado_com_os_telefones()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->cadastrar($token, [
            'name' => 'Carlos Lead',
            'observation' => 'Indicado na feira',
            'telefones' => [$this->telefoneParaEnvio('62999990001'), $this->telefoneParaEnvio('6232220002', false)],
        ]);

        $resposta->assertStatus(200)->assertJson([
            'response' => 'Salvo com sucesso',
            'dados' => ['name' => 'Carlos Lead', 'observation' => 'Indicado na feira', 'id_usuario' => $usuario['id']],
        ]);
        $idCliente = $resposta->json('dados.id');
        $this->assertSame(['62999990001', '6232220002'], $this->telefonesGravadosDe($idCliente));
        $whatsapp = DB::table('customers_phone')->where('id_customers', $idCliente)->orderBy('id')->pluck('bo_whatsapp')->all();
        $this->assertEquals([1, 0], $whatsapp);
    }

    public function test_cadastro_sem_status_comeca_como_aberto()
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->cadastrar($token, ['name' => 'Sem Status', 'telefones' => [$this->telefoneParaEnvio('62999990001')]]);

        $this->assertSame('a', $this->registro('customers', $resposta->json('dados.id'))['status']);
    }

    public function test_cadastro_respeita_o_status_informado()
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->cadastrar($token, ['name' => 'Ja Comprou', 'status' => 'c', 'telefones' => [$this->telefoneParaEnvio('62999990001')]]);

        $this->assertSame('c', $this->registro('customers', $resposta->json('dados.id'))['status']);
    }

    public function test_cadastro_de_referido_guarda_quem_indicou()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $quemIndicou = $this->criarCliente($usuario['id'], ['name' => 'Quem Indicou']);

        $resposta = $this->cadastrar($token, [
            'name' => 'Indicado',
            'id_parent' => $quemIndicou['id'],
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ]);

        $resposta->assertStatus(200);
        $this->assertSame($quemIndicou['id'], $this->registro('customers', $resposta->json('dados.id'))['id_parent']);
    }

    public function test_cadastro_ignora_o_dono_enviado_e_usa_o_usuario_logado()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();

        $resposta = $this->cadastrar($token, [
            'name' => 'Tentativa',
            'id_usuario' => $outro['id'],
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ]);

        $this->assertSame($usuario['id'], $this->registro('customers', $resposta->json('dados.id'))['id_usuario']);
    }

    public function test_telefone_ja_usado_por_cliente_raiz_e_recusado_com_o_nome_dele()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $existente = $this->criarCliente($usuario['id'], ['name' => 'Dona do Numero']);
        $this->criarTelefone($existente['id'], '62999990001');

        $resposta = $this->cadastrar($token, ['name' => 'Repetido', 'telefones' => [$this->telefoneParaEnvio('62999990001')]]);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Número de telefone já existe (Dona do Numero)']);
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_telefone_ja_usado_por_referido_e_recusado_com_o_nome_de_quem_indicou()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $quemIndicou = $this->criarCliente($usuario['id'], ['name' => 'Quem Indicou']);
        $referido = $this->criarCliente($usuario['id'], ['name' => 'Referido', 'id_parent' => $quemIndicou['id']]);
        $this->criarTelefone($referido['id'], '62999990001');

        $resposta = $this->cadastrar($token, ['name' => 'Repetido', 'telefones' => [$this->telefoneParaEnvio('62999990001')]]);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido já indicado pelo(a) Quem Indicou']);
    }

    public function test_telefone_de_cliente_de_outro_usuario_pode_ser_reutilizado()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $clienteDoOutro = $this->criarCliente($outro['id']);
        $this->criarTelefone($clienteDoOutro['id'], '62999990001');

        $resposta = $this->cadastrar($token, ['name' => 'Mesmo Numero', 'telefones' => [$this->telefoneParaEnvio('62999990001')]]);

        $resposta->assertStatus(200);
    }

    /**
     * A verificacao de duplicidade para no primeiro telefone preenchido.
     */
    public function test_so_o_primeiro_telefone_e_conferido_contra_duplicidade()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $existente = $this->criarCliente($usuario['id']);
        $this->criarTelefone($existente['id'], '62999990002');

        $resposta = $this->cadastrar($token, [
            'name' => 'Segundo Repetido',
            'telefones' => [$this->telefoneParaEnvio('62999990001'), $this->telefoneParaEnvio('62999990002')],
        ]);

        $resposta->assertStatus(200);
    }

    public function test_consulta_por_id_devolve_o_cliente()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id'], ['name' => 'Consultado']);

        $resposta = $this->chamarApi('GET', "/api/customers/{$cliente['id']}", [], $token);

        $resposta->assertStatus(200)->assertJson(['id' => $cliente['id'], 'name' => 'Consultado']);
    }

    public function test_consulta_por_id_inexistente_e_recusada()
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('GET', '/api/customers/999', [], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Não existe Customers']);
    }

    public function test_consulta_por_id_nao_devolve_cliente_de_outro_usuario()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $clienteDoOutro = $this->criarCliente($outro['id'], ['name' => 'Do Outro']);

        $resposta = $this->chamarApi('GET', "/api/customers/{$clienteDoOutro['id']}", [], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Não existe Customers']);
    }
}
