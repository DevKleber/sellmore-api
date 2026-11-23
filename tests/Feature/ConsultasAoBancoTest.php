<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

/**
 * Garante que o numero de consultas ao banco nao cresce com a quantidade de clientes.
 */
class ConsultasAoBancoTest extends ApiTestCase
{
    private const POUCOS = 2;
    private const MUITOS = 15;

    private const TODOS_OS_STATUS = 'boShowAberto=true&boShowLigarDepois=true&boShowProblemasCartao=true&boShowNaotemInteresse=true&boShowComprou=true';

    /**
     * A primeira chamada de cada vendedor fica de fora da contagem, para a medida nao depender
     * do que a aplicacao carrega uma unica vez.
     */
    private function consultasFeitasPor(string $uri, string $token): int
    {
        $this->chamarApi('GET', $uri, [], $token)->assertStatus(200);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->chamarApi('GET', $uri, [], $token)->assertStatus(200);
        $quantidade = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $quantidade;
    }

    /**
     * Leads com telefone, cada um com um referido "ligar depois" com telefone e um compromisso.
     */
    private function criarCarteira(int $idUsuario, int $quantidade): void
    {
        for ($numero = 1; $numero <= $quantidade; ++$numero) {
            $lead = $this->criarCliente($idUsuario, ['name' => "Carteira {$quantidade} lead {$numero}"]);
            $this->criarTelefone($lead['id'], "62{$quantidade}0000{$numero}");
            $referido = $this->criarCliente($idUsuario, [
                'name' => "Carteira {$quantidade} referido {$numero}",
                'id_parent' => $lead['id'],
                'status' => 'ld',
            ]);
            $this->criarTelefone($referido['id'], "62{$quantidade}1111{$numero}");
            $this->criarCompromisso($idUsuario, $referido['id']);
        }
    }

    /**
     * @dataProvider rotasQueListamClientes
     */
    public function test_quantidade_de_consultas_nao_depende_do_tamanho_da_carteira(string $uri)
    {
        [$vendedorPequeno, $tokenPequeno] = $this->criarUsuarioAutenticado();
        [$vendedorGrande, $tokenGrande] = $this->criarUsuarioAutenticado();
        $this->criarCarteira($vendedorPequeno['id'], self::POUCOS);
        $this->criarCarteira($vendedorGrande['id'], self::MUITOS);

        $comPoucos = $this->consultasFeitasPor($uri, $tokenPequeno);
        $comMuitos = $this->consultasFeitasPor($uri, $tokenGrande);

        $this->assertSame($comPoucos, $comMuitos);
    }

    public function rotasQueListamClientes(): array
    {
        return [
            'listagem completa' => ['/api/customers?'.self::TODOS_OS_STATUS],
            'listagem paginada' => ['/api/customers?'.self::TODOS_OS_STATUS.'&page=1&perPage=50'],
            'busca' => ['/api/search?q=Carteira'],
            'ligar depois' => ['/api/getCustomersLd'],
            'agenda' => ['/api/calendar'],
        ];
    }
}
