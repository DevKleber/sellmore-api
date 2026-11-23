<?php

namespace Tests;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Support\BancoDeTestes;
use Tests\Support\ChamaApi;
use Tests\Support\CriaDados;

/**
 * Base dos testes que exercitam a API por HTTP contra um MySQL com o esquema real de producao.
 * Usa apenas chamadas HTTP e SQL direto, para continuar valendo depois de atualizar o framework.
 */
abstract class ApiTestCase extends TestCase
{
    use BancoDeTestes;
    use ChamaApi;
    use CriaDados;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->prepararBancoDeTestes();
    }
}
