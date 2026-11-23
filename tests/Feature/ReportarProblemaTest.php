<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ReportarProblemaTest extends ApiTestCase
{
    private const ENDERECO_QUE_NAO_RESPONDE = 'http://127.0.0.1:9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(
            self::ENDERECO_QUE_NAO_RESPONDE,
            env('API_TRELLO_URL'),
            'Os testes nao podem chamar o Trello de verdade: confira o API_TRELLO_URL no phpunit.xml.'
        );
    }

    public function test_reportar_sem_estar_logado_responde_erro()
    {
        $resposta = $this->chamarApi('POST', '/api/issue', ['name' => 'Titulo', 'desc' => 'Descricao']);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Erro ao reportar.']);
    }

    public function test_reportar_com_o_trello_fora_do_ar_responde_erro()
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('POST', '/api/issue', ['name' => 'Titulo', 'desc' => 'Descricao'], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Erro ao reportar.']);
    }
}
