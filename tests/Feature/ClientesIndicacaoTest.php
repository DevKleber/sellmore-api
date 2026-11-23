<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ClientesIndicacaoTest extends ApiTestCase
{
    private const LIMITE_DE_NIVEIS = 5;

    private function criarCadeiaDeIndicacao(int $idUsuario, array $nomesDaRaizAteOUltimo): array
    {
        $cadeia = [];
        $idDeQuemIndicou = null;
        foreach ($nomesDaRaizAteOUltimo as $nome) {
            $cliente = $this->criarCliente($idUsuario, ['name' => $nome, 'id_parent' => $idDeQuemIndicou]);
            $idDeQuemIndicou = $cliente['id'];
            $cadeia[] = $cliente;
        }

        return $cadeia;
    }

    public function test_arvore_de_indicacao_vai_do_cliente_ate_a_raiz_com_telefones()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        [$avo, , $neto] = $this->criarCadeiaDeIndicacao($usuario['id'], ['Avo', 'Pai', 'Neto']);
        $this->criarTelefone($avo['id'], '62999990001');

        $resposta = $this->chamarApi('GET', "/api/parents/customers/{$neto['id']}", [], $token);

        $resposta->assertStatus(200);
        $this->assertSame(['Neto', 'Pai', 'Avo'], array_column($resposta->json(), 'name'));
        $this->assertSame(['62999990001'], array_column($resposta->json('2.phones'), 'phone'));
        $this->assertSame([], $resposta->json('0.phones'));
    }

    public function test_arvore_de_cliente_raiz_tem_so_ele_mesmo()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $raiz = $this->criarCliente($usuario['id'], ['name' => 'Raiz']);

        $resposta = $this->chamarApi('GET', "/api/parents/customers/{$raiz['id']}", [], $token);

        $this->assertSame(['Raiz'], array_column($resposta->json(), 'name'));
    }

    public function test_arvore_de_indicacao_para_no_quinto_nivel()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cadeia = $this->criarCadeiaDeIndicacao($usuario['id'], ['N1', 'N2', 'N3', 'N4', 'N5', 'N6', 'N7']);
        $ultimo = end($cadeia);

        $resposta = $this->chamarApi('GET', "/api/parents/customers/{$ultimo['id']}", [], $token);

        $this->assertCount(self::LIMITE_DE_NIVEIS, $resposta->json());
        $this->assertSame(['N7', 'N6', 'N5', 'N4', 'N3'], array_column($resposta->json(), 'name'));
    }
}
