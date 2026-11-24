<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class VersaoTest extends ApiTestCase
{
    private const FORMATO_DE_VERSAO = '/^\d+\.\d+\.\d+$/';

    public function test_versao_atual_e_publica_e_traz_numero_resumo_e_link_das_novidades()
    {
        $resposta = $this->chamarApi('GET', '/api/currentVersion');

        $resposta->assertStatus(200)->assertJsonStructure(['version', 'resume', 'link']);
        $this->assertNoFormato(self::FORMATO_DE_VERSAO, $resposta->json('version'));
        $this->assertNotEmpty($resposta->json('resume'));
        $this->assertStringStartsWith('https://', $resposta->json('link'));
    }

    /**
     * O assert de expressao regular mudou de nome entre versoes do PHPUnit; este nao depende disso.
     */
    private function assertNoFormato(string $padrao, string $valor): void
    {
        $this->assertSame(1, preg_match($padrao, $valor), "'{$valor}' nao esta no formato esperado.");
    }
}
