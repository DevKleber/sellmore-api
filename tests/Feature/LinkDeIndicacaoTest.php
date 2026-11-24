<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class LinkDeIndicacaoTest extends ApiTestCase
{
    private const TAMANHO_DO_CODIGO = 40;
    private const MAXIMO_POR_ENVIO = 10;
    private const LINK_INVALIDO = 'Este link de indicação não está mais disponível';

    private $usuario;
    private $token;
    private $lead;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->usuario, $this->token] = $this->criarUsuarioAutenticado(['nome' => 'Marcos Vendedor']);
        $this->lead = $this->criarCliente($this->usuario['id'], ['name' => 'Carla Cliente']);
    }

    private function codigoDoLead(): string
    {
        $resposta = $this->chamarApi('POST', "/api/link-indicacao/customers/{$this->lead['id']}", [], $this->token);
        $resposta->assertStatus(200);

        return $resposta->json('dados.codigo');
    }

    private function indicar(string $codigo, array $indicados)
    {
        return $this->chamarApi('POST', "/api/indicacao/{$codigo}", ['indicados' => $indicados]);
    }

    private function referidosDoLead(): array
    {
        return DB::table('customers')->where('id_parent', $this->lead['id'])->orderBy('id')->get()->map(function ($linha) {
            return (array) $linha;
        })->all();
    }

    public function test_vendedor_gera_o_link_do_cliente_e_recebe_sempre_o_mesmo()
    {
        $resposta = $this->chamarApi('POST', "/api/link-indicacao/customers/{$this->lead['id']}", [], $this->token);

        $codigo = $resposta->json('dados.codigo');
        $this->assertSame(self::TAMANHO_DO_CODIGO, strlen($codigo));
        $this->assertSame("https://app.wiseller.com.br/indicar/{$codigo}", $resposta->json('dados.link'));
        $this->assertSame($codigo, $this->codigoDoLead());
    }

    public function test_gerar_link_de_cliente_de_outro_usuario_e_recusado()
    {
        $outro = $this->criarUsuario();
        $clienteDoOutro = $this->criarCliente($outro['id']);

        $resposta = $this->chamarApi('POST', "/api/link-indicacao/customers/{$clienteDoOutro['id']}", [], $this->token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Referido não encontrado']);
        $this->assertNull($this->registro('customers', $clienteDoOutro['id'])['codigo_do_link_de_indicacao']);
    }

    public function test_gerar_link_exige_login()
    {
        $this->chamarApi('POST', "/api/link-indicacao/customers/{$this->lead['id']}")->assertStatus(400);
    }

    public function test_pagina_publica_mostra_so_o_primeiro_nome_do_vendedor_e_do_cliente()
    {
        $resposta = $this->chamarApi('GET', '/api/indicacao/'.$this->codigoDoLead());

        $resposta->assertStatus(200)->assertExactJson(['dados' => [
            'vendedor' => 'Marcos',
            'cliente' => 'Carla',
            'maximoPorEnvio' => self::MAXIMO_POR_ENVIO,
        ]]);
    }

    public function test_codigo_desconhecido_e_recusado()
    {
        $codigoInexistente = str_repeat('x', self::TAMANHO_DO_CODIGO);

        $this->chamarApi('GET', "/api/indicacao/{$codigoInexistente}")->assertStatus(400)->assertExactJson(['response' => self::LINK_INVALIDO]);
        $this->indicar($codigoInexistente, [['nome' => 'Alguem', 'telefone' => '62999990001']])->assertStatus(400);
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_link_de_cliente_arquivado_deixa_de_funcionar()
    {
        $codigo = $this->codigoDoLead();
        DB::table('customers')->where('id', $this->lead['id'])->update(['bo_ativo' => 0]);

        $this->chamarApi('GET', "/api/indicacao/{$codigo}")->assertStatus(400)->assertExactJson(['response' => self::LINK_INVALIDO]);
    }

    public function test_indicados_entram_como_referidos_abertos_do_cliente_com_whatsapp()
    {
        $resposta = $this->indicar($this->codigoDoLead(), [
            ['nome' => 'Paulo Indicado', 'telefone' => '(62) 99999-0001'],
            ['nome' => 'Rita Indicada', 'telefone' => '+55 62 99999-0002'],
        ]);

        $resposta->assertStatus(200)->assertJson(['dados' => ['recebidos' => 2]]);
        $referidos = $this->referidosDoLead();
        $this->assertSame(['Paulo Indicado', 'Rita Indicada'], array_column($referidos, 'name'));
        $this->assertSame([$this->usuario['id'], $this->usuario['id']], array_column($referidos, 'id_usuario'));
        $this->assertSame(['a', 'a'], array_column($referidos, 'status'));
        $this->assertSame('Indicado pelo link de indicação', $referidos[0]['observation']);
        $this->assertSame(['62999990001'], $this->telefonesGravadosDe($referidos[0]['id']));
        $this->assertSame(['62999990002'], $this->telefonesGravadosDe($referidos[1]['id']));
        $telefone = DB::table('customers_phone')->where('id_customers', $referidos[0]['id'])->first();
        $this->assertEquals(1, $telefone->bo_whatsapp);
        $this->assertSame('55', $telefone->country_code);
    }

    public function test_envio_sem_ninguem_valido_e_recusado()
    {
        $resposta = $this->indicar($this->codigoDoLead(), [
            ['nome' => 'A', 'telefone' => '62999990001'],
            ['nome' => 'Sem Telefone', 'telefone' => '123'],
            'texto solto',
        ]);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Informe o nome e o telefone com DDD de pelo menos uma pessoa']);
        $this->assertSame([], $this->referidosDoLead());
    }

    public function test_envio_acima_do_limite_e_recusado_por_inteiro()
    {
        $indicados = [];
        for ($numero = 0; $numero <= self::MAXIMO_POR_ENVIO; ++$numero) {
            $indicados[] = ['nome' => "Pessoa {$numero}", 'telefone' => '629999900'.str_pad((string) $numero, 2, '0', STR_PAD_LEFT)];
        }

        $resposta = $this->indicar($this->codigoDoLead(), $indicados);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Envie no máximo 10 pessoas por vez']);
        $this->assertSame([], $this->referidosDoLead());
    }

    /**
     * A resposta e a mesma com ou sem repetido, para a pagina publica nao revelar a carteira do vendedor.
     */
    public function test_telefone_que_o_vendedor_ja_tem_e_ignorado_sem_avisar()
    {
        $existente = $this->criarCliente($this->usuario['id'], ['name' => 'Ja Cadastrado']);
        $this->criarTelefone($existente['id'], '62999990001');

        $resposta = $this->indicar($this->codigoDoLead(), [
            ['nome' => 'Repetido', 'telefone' => '62999990001'],
            ['nome' => 'Novo', 'telefone' => '62999990002'],
            ['nome' => 'Novo De Novo', 'telefone' => '62 99999 0002'],
        ]);

        $resposta->assertStatus(200)->assertJson(['dados' => ['recebidos' => 2]]);
        $this->assertSame(['Novo De Novo'], array_column($this->referidosDoLead(), 'name'));
    }

    public function test_nome_recebido_perde_marcacao_e_emoji()
    {
        $this->indicar($this->codigoDoLead(), [['nome' => '<b>João</b> "Silva" 😀', 'telefone' => '62999990001']])->assertStatus(200);

        $this->assertSame('bJoão/b Silva', $this->referidosDoLead()[0]['name']);
    }

    public function test_indicacao_recebida_aparece_no_card_do_cliente_para_o_vendedor()
    {
        $this->indicar($this->codigoDoLead(), [['nome' => 'Paulo Indicado', 'telefone' => '62999990001']])->assertStatus(200);

        $resposta = $this->chamarApi('GET', '/api/customers?boShowAberto=true&page=1', [], $this->token);

        $this->assertSame('Paulo Indicado', $resposta->json('arCustomers.0.referidos.0.name'));
    }
}
