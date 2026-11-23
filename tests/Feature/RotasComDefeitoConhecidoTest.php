<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

/**
 * Rotas que existem em routes/api.php mas hoje terminam em erro interno.
 * Ficam registradas para que uma mudanca de comportamento na atualizacao apareca aqui.
 */
class RotasComDefeitoConhecidoTest extends ApiTestCase
{
    private const ERRO_INTERNO = 500;

    /**
     * @dataProvider rotasQueHojeFalham
     */
    public function test_rota_responde_erro_interno(string $metodo, string $uri, array $dados)
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi($metodo, $uri, $dados, $token);

        $resposta->assertStatus(self::ERRO_INTERNO);
    }

    public function rotasQueHojeFalham(): array
    {
        return [
            'listar funcionarios chama metodo que nao existe' => ['GET', '/api/employee', []],
            'alterar funcionario usa classes que nao existem' => ['PUT', '/api/employee/1', ['documents' => []]],
            'inativar funcionario grava coluna que nao existe' => ['DELETE', '/api/employee/1', []],
            'trocar senha logado tem assinatura incompativel com a rota' => ['PUT', '/api/auth/changePassword', ['newPassword' => 'x', 'confirmPassword' => 'x']],
            'arvore de indicacao de cliente de outro usuario' => ['GET', '/api/parents/customers/999', []],
            'excluir compromisso inexistente' => ['DELETE', '/api/calendar/999', []],
        ];
    }

    /**
     * A consulta de um funcionario ignora o id e devolve todos os usuarios ativos.
     */
    public function test_consulta_de_funcionario_devolve_todos_os_usuarios_ativos()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $this->criarUsuario(['nome' => 'Outro Ativo']);
        $this->criarUsuario(['nome' => 'Inativo', 'bo_ativo' => 0]);

        $resposta = $this->chamarApi('GET', '/api/employee/999', [], $token);

        $resposta->assertStatus(200);
        $this->assertSame([$usuario['nome'], 'Outro Ativo'], array_column($resposta->json('dados'), 'nome'));
    }
}
