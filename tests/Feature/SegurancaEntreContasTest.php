<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ApiTestCase;

/**
 * Um vendedor nunca le nem altera dados de outro vendedor.
 */
class SegurancaEntreContasTest extends ApiTestCase
{
    private const NAO_ENCONTRADO = 404;

    private $token;
    private $usuario;
    private $clienteDoOutro;
    private $outro;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->usuario, $this->token] = $this->criarUsuarioAutenticado();
        $this->outro = $this->criarUsuario();
        $this->clienteDoOutro = $this->criarCliente($this->outro['id'], ['name' => 'Do Outro', 'status' => 'a']);
        $this->criarTelefone($this->clienteDoOutro['id'], '62999990009');
    }

    private function clienteDoOutroGravado(): array
    {
        return $this->registro('customers', $this->clienteDoOutro['id']);
    }

    public function test_alterar_cliente_de_outro_usuario_e_recusado_e_nada_muda()
    {
        $resposta = $this->chamarApi('PUT', "/api/customers/{$this->clienteDoOutro['id']}", [
            'name' => 'Invadido',
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
        $this->assertSame('Do Outro', $this->clienteDoOutroGravado()['name']);
        $this->assertSame(['62999990009'], $this->telefonesGravadosDe($this->clienteDoOutro['id']));
    }

    public function test_alteracao_nao_transfere_o_cliente_para_outro_usuario()
    {
        $meuCliente = $this->criarCliente($this->usuario['id']);

        $this->chamarApi('PUT', "/api/customers/{$meuCliente['id']}", [
            'name' => 'Continua Meu',
            'id_usuario' => $this->outro['id'],
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ], $this->token)->assertStatus(200);

        $this->assertSame($this->usuario['id'], $this->registro('customers', $meuCliente['id'])['id_usuario']);
    }

    public function test_mudar_status_de_cliente_de_outro_usuario_e_recusado()
    {
        $resposta = $this->chamarApi('PUT', "/api/status/customers/{$this->clienteDoOutro['id']}", ['status' => 'c'], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Customers Não encontrado']);
        $this->assertSame('a', $this->clienteDoOutroGravado()['status']);
    }

    public function test_cadastrar_referido_de_cliente_de_outro_usuario_e_recusado()
    {
        $resposta = $this->chamarApi('POST', '/api/customers', [
            'name' => 'Intruso',
            'id_parent' => $this->clienteDoOutro['id'],
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_mover_cliente_para_baixo_de_cliente_de_outro_usuario_e_recusado()
    {
        $meuCliente = $this->criarCliente($this->usuario['id']);

        $resposta = $this->chamarApi('PUT', "/api/customers/{$meuCliente['id']}", [
            'name' => 'Meu',
            'id_parent' => $this->clienteDoOutro['id'],
            'telefones' => [$this->telefoneParaEnvio('62999990001')],
        ], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
        $this->assertNull($this->registro('customers', $meuCliente['id'])['id_parent']);
    }

    public function test_importar_contatos_para_cliente_de_outro_usuario_e_recusado()
    {
        $arquivo = new UploadedFile(__DIR__.'/../fixtures/contatos.vcf', 'contatos.vcf', 'text/vcard', null, true);

        $resposta = $this->enviarArquivoParaApi("/api/import-contact/customers/{$this->clienteDoOutro['id']}", ['imagem' => $arquivo], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_agendar_ligacao_para_cliente_de_outro_usuario_e_recusado()
    {
        $resposta = $this->chamarApi('POST', '/api/calendar', [
            'date' => '2030-05-20',
            'hour' => '14:30',
            'id_customers' => $this->clienteDoOutro['id'],
        ], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
        $this->assertSame(0, DB::table('calendar')->count());
    }

    public function test_consultar_compromisso_de_outro_usuario_e_recusado()
    {
        $compromisso = $this->criarCompromisso($this->outro['id'], $this->clienteDoOutro['id']);

        $resposta = $this->chamarApi('GET', "/api/calendar/{$compromisso['id']}", [], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Não existe Calendar']);
    }

    public function test_remarcar_compromisso_de_outro_usuario_e_recusado()
    {
        $compromisso = $this->criarCompromisso($this->outro['id'], $this->clienteDoOutro['id']);

        $resposta = $this->chamarApi('PUT', "/api/calendar/{$compromisso['id']}", [
            'newStart' => '2030-06-01 10:00:00',
            'newEnd' => '2030-06-01 11:00:00',
        ], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Calendar Não encontrado']);
        $this->assertSame('2030-05-20 14:30:00', $this->registro('calendar', $compromisso['id'])['date']);
    }

    public function test_excluir_compromisso_inexistente_e_recusado_sem_erro_interno()
    {
        $resposta = $this->chamarApi('DELETE', '/api/calendar/999', [], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Calendar Não encontrado']);
    }

    /**
     * Essas rotas devolviam a lista de todos os usuarios, com e-mail e senha cifrada, ou davam erro interno.
     */
    #[DataProvider('rotasDeFuncionarioRemovidas')]
    public function test_rotas_de_funcionario_nao_existem_mais(string $metodo, string $uri)
    {
        $this->chamarApi($metodo, $uri, [], $this->token)->assertStatus(self::NAO_ENCONTRADO);
    }

    public static function rotasDeFuncionarioRemovidas(): array
    {
        return [
            'listar' => ['GET', '/api/employee'],
            'consultar' => ['GET', '/api/employee/1'],
            'alterar' => ['PUT', '/api/employee/1'],
            'inativar' => ['DELETE', '/api/employee/1'],
        ];
    }

    public function test_cadastro_de_usuario_nao_devolve_a_senha_cifrada()
    {
        $resposta = $this->chamarApi('POST', '/api/user/new', [
            'nome' => 'Novo Vendedor',
            'email' => 'novo@wiseller.test',
            'usuario' => 'novovendedor',
            'password' => 'senha-forte-123',
        ]);

        $resposta->assertStatus(200);
        $this->assertArrayNotHasKey('password', $resposta->json('dados'));
    }
}
