<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class AgendaTest extends ApiTestCase
{
    private function clienteComTelefones(int $idUsuario, string $nome, array $numeros): array
    {
        $cliente = $this->criarCliente($idUsuario, ['name' => $nome]);
        foreach ($numeros as $numero) {
            $this->criarTelefone($cliente['id'], $numero);
        }

        return $cliente;
    }

    public function test_agendar_grava_o_compromisso_e_devolve_o_evento_com_meia_hora_de_duracao()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->clienteComTelefones($usuario['id'], 'Paula Agenda', ['62999990001', '62999990002']);

        $resposta = $this->chamarApi('POST', '/api/calendar', [
            'date' => '2030-05-20',
            'hour' => '14:30',
            'id_customers' => $cliente['id'],
        ], $token);

        $resposta->assertStatus(200)->assertJson([
            'response' => 'Salvo com sucesso',
            'dados' => [
                'start' => '2030-05-20 14:30',
                'end' => '2030-05-20 15:00:00',
                'phones' => ['62999990001', '62999990002'],
                'color' => '#fff',
                'allDay' => false,
            ],
        ]);
        $this->assertStringContainsString('Paula Agenda 62999990001,62999990002', $resposta->json('dados.title'));
        $this->assertStringContainsString('Ligar às 14:30:00', $resposta->json('dados.title'));
        $gravado = $this->registro('calendar', $resposta->json('dados.id'));
        $this->assertSame('2030-05-20 14:30:00', $gravado['date']);
        $this->assertSame($usuario['id'], $gravado['id_usuario']);
        $this->assertEquals(1, $gravado['bo_ativo']);
    }

    /**
     * @dataProvider formatosDeData
     */
    public function test_agendar_aceita_data_em_varios_formatos(string $dataInformada)
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);

        $resposta = $this->chamarApi('POST', '/api/calendar', [
            'date' => $dataInformada,
            'hour' => '09:15',
            'id_customers' => $cliente['id'],
        ], $token);

        $resposta->assertStatus(200);
        $this->assertSame('2030-05-20 09:15:00', $this->registro('calendar', $resposta->json('dados.id'))['date']);
    }

    public function formatosDeData(): array
    {
        return [
            'com tracos' => ['2030-05-20'],
            'com barras no padrao brasileiro' => ['20/05/2030'],
            'so digitos no padrao brasileiro' => ['20052030'],
        ];
    }

    public function test_agenda_lista_os_compromissos_ativos_do_usuario_no_formato_do_calendario()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->clienteComTelefones($usuario['id'], 'Paula Agenda', ['62999990001']);
        $compromisso = $this->criarCompromisso($usuario['id'], $cliente['id'], ['date' => '2030-05-20 14:30:00']);

        $resposta = $this->chamarApi('GET', '/api/calendar', [], $token);

        $resposta->assertStatus(200)->assertExactJson(['dados' => [[
            'id' => $compromisso['id'],
            'start' => '2030/05/20 14:30:00',
            'end' => '2030/05/20 15:00:00',
            'title' => "<small class='displayNone'>{$cliente['id']}:|:;</small> Paula Agenda 62999990001 <br />Ligar às 14:30:00",
            'phones' => '62999990001',
            'color' => '#fff',
            'allDay' => false,
        ]]]);
    }

    public function test_agenda_usa_o_fim_gravado_quando_o_compromisso_foi_remarcado()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);
        $this->criarCompromisso($usuario['id'], $cliente['id'], ['date' => '2030-05-20 14:30:00', 'date_end' => '2030-05-20 16:00:00']);

        $resposta = $this->chamarApi('GET', '/api/calendar', [], $token);

        $this->assertSame('2030-05-20 16:00:00', $resposta->json('dados.0.end'));
    }

    public function test_agenda_nao_lista_inativos_nem_compromissos_de_outro_usuario()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);
        $this->criarCompromisso($usuario['id'], $cliente['id'], ['bo_ativo' => 0]);
        $outro = $this->criarUsuario();
        $clienteDoOutro = $this->criarCliente($outro['id']);
        $this->criarCompromisso($outro['id'], $clienteDoOutro['id']);

        $resposta = $this->chamarApi('GET', '/api/calendar', [], $token);

        $resposta->assertStatus(200)->assertExactJson(['dados' => []]);
    }

    public function test_remarcar_grava_novo_inicio_e_fim()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);
        $compromisso = $this->criarCompromisso($usuario['id'], $cliente['id']);

        $resposta = $this->chamarApi('PUT', "/api/calendar/{$compromisso['id']}", [
            'newStart' => '2030-06-01 10:00:00',
            'newEnd' => '2030-06-01 11:30:00',
        ], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Atualizado com sucesso']);
        $gravado = $this->registro('calendar', $compromisso['id']);
        $this->assertSame('2030-06-01 10:00:00', $gravado['date']);
        $this->assertSame('2030-06-01 11:30:00', $gravado['date_end']);
    }

    public function test_remarcar_compromisso_inexistente_e_recusado()
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('PUT', '/api/calendar/999', ['newStart' => '2030-06-01 10:00:00', 'newEnd' => '2030-06-01 11:00:00'], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Calendar Não encontrado']);
    }

    public function test_excluir_apaga_o_compromisso_do_proprio_usuario()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);
        $compromisso = $this->criarCompromisso($usuario['id'], $cliente['id']);

        $resposta = $this->chamarApi('DELETE', "/api/calendar/{$compromisso['id']}", [], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Deletado com sucesso!']);
        $this->assertSame(0, DB::table('calendar')->count());
    }

    public function test_excluir_compromisso_de_outro_usuario_e_recusado()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $outro = $this->criarUsuario();
        $clienteDoOutro = $this->criarCliente($outro['id']);
        $compromissoDoOutro = $this->criarCompromisso($outro['id'], $clienteDoOutro['id']);

        $resposta = $this->chamarApi('DELETE', "/api/calendar/{$compromissoDoOutro['id']}", [], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Sem permissão para deletar esse contato.']);
        $this->assertSame(1, DB::table('calendar')->count());
    }

    public function test_consulta_por_id_devolve_o_compromisso()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $cliente = $this->criarCliente($usuario['id']);
        $compromisso = $this->criarCompromisso($usuario['id'], $cliente['id']);

        $resposta = $this->chamarApi('GET', "/api/calendar/{$compromisso['id']}", [], $token);

        $resposta->assertStatus(200)->assertJson(['id' => $compromisso['id'], 'id_customers' => $cliente['id']]);
    }

    public function test_consulta_por_id_inexistente_e_recusada()
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->chamarApi('GET', '/api/calendar/999', [], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Não existe Calendar']);
    }
}
