<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Facade;
use ReflectionProperty;

trait ChamaApi
{
    private static $servicosQueGuardamARequisicao = ['tymon.jwt', 'tymon.jwt.auth', 'tymon.jwt.parser'];

    protected function chamarApi(string $metodo, string $uri, array $dados = [], ?string $token = null)
    {
        $this->descartarEstadoDaRequisicaoAnterior();

        return $this->json($metodo, $uri, $dados, $this->cabecalhos($token));
    }

    protected function enviarArquivoParaApi(string $uri, array $dados, ?string $token = null)
    {
        $this->descartarEstadoDaRequisicaoAnterior();

        return $this->post($uri, $dados, $this->cabecalhos($token));
    }

    protected function entrarNaApi(array $usuario): string
    {
        $resposta = $this->chamarApi('POST', '/api/auth/login', [
            'email' => $usuario['email'],
            'password' => $usuario['senha'],
        ]);
        $resposta->assertStatus(200);

        return $resposta->json('access_token');
    }

    /**
     * @return array [usuario, token]
     */
    protected function criarUsuarioAutenticado(array $atributos = []): array
    {
        $usuario = $this->criarUsuario($atributos);

        return [$usuario, $this->entrarNaApi($usuario)];
    }

    /**
     * Um mesmo teste faz varias requisicoes na mesma instancia da aplicacao; sem isso o usuario e o token
     * da requisicao anterior continuariam valendo na seguinte, o que nao acontece em producao.
     */
    private function descartarEstadoDaRequisicaoAnterior(): void
    {
        $this->esquecerUsuarioAutenticado();

        foreach (self::$servicosQueGuardamARequisicao as $servico) {
            $this->app->forgetInstance($servico);
        }

        Facade::clearResolvedInstances();
    }

    /**
     * AuthManager::forgetGuards() so existe a partir do Laravel 8; antes disso a lista de guards e limpa direto.
     */
    private function esquecerUsuarioAutenticado(): void
    {
        $gerenciador = $this->app['auth'];

        if (method_exists($gerenciador, 'forgetGuards')) {
            $gerenciador->forgetGuards();

            return;
        }

        $guards = new ReflectionProperty($gerenciador, 'guards');
        $guards->setAccessible(true);
        $guards->setValue($gerenciador, []);
    }

    private function cabecalhos(?string $token): array
    {
        $cabecalhos = ['Accept' => 'application/json'];

        if (null === $token) {
            return $cabecalhos;
        }

        return $cabecalhos + ['Authorization' => "Bearer {$token}"];
    }
}
