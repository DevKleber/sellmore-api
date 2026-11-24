<?php

namespace Tests\Feature;

use App\LembreteDeLigacoes;
use App\Mail\LembreteDasLigacoesDoDia;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;

class LembreteDasLigacoesTest extends ApiTestCase
{
    private const AGORA = '2026-11-18 07:00:00';
    private const HOJE_CEDO = '2026-11-18 09:30:00';
    private const HOJE_TARDE = '2026-11-18 15:00:00';
    private const AMANHA = '2026-11-19 09:30:00';
    private const ONTEM = '2026-11-17 09:30:00';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(self::AGORA);
    }

    private function agendar(array $usuario, string $nomeDoCliente, string $data, array $compromisso = [], array $cliente = []): void
    {
        $criado = $this->criarCliente($usuario['id'], $cliente + ['name' => $nomeDoCliente]);
        $this->criarCompromisso($usuario['id'], $criado['id'], $compromisso + ['date' => $data]);
    }

    private function enviarLembretes(): void
    {
        $this->assertSame(0, Artisan::call('lembretes:ligacoes-do-dia'));
    }

    public function test_vendedor_recebe_um_email_com_as_ligacoes_de_hoje_em_ordem_de_horario()
    {
        $vendedor = $this->criarUsuario(['nome' => 'Marcos Vendedor']);
        $this->agendar($vendedor, 'Cliente da Tarde', self::HOJE_TARDE);
        $this->agendar($vendedor, 'Cliente da Manha', self::HOJE_CEDO);

        $this->enviarLembretes();

        Mail::assertSent(LembreteDasLigacoesDoDia::class, 1);
        Mail::assertSent(LembreteDasLigacoesDoDia::class, function ($email) use ($vendedor) {
            $ligacoesEsperadas = [
                ['hora' => '09:30', 'cliente' => 'Cliente da Manha'],
                ['hora' => '15:00', 'cliente' => 'Cliente da Tarde'],
            ];

            return $email->hasTo($vendedor['email']) && $email->vendedor['ligacoes'] === $ligacoesEsperadas;
        });
    }

    public function test_email_traz_o_endereco_da_plataforma_e_o_link_para_deixar_de_receber()
    {
        $vendedor = $this->criarUsuario();
        $this->agendar($vendedor, 'Cliente', self::HOJE_CEDO);

        $this->enviarLembretes();

        $email = Mail::sent(LembreteDasLigacoesDoDia::class)->first();
        $corpo = $email->render();
        $this->assertStringContainsString('https://app.wiseller.com.br', $corpo);
        $this->assertStringContainsString(LembreteDeLigacoes::enderecoDoCancelamento($vendedor['id'], $vendedor['email']), $corpo);
        $this->assertStringContainsString('09:30', $corpo);
    }

    public function test_ligacoes_de_outros_dias_canceladas_ou_de_cliente_arquivado_nao_geram_email()
    {
        $vendedor = $this->criarUsuario();
        $this->agendar($vendedor, 'Amanha', self::AMANHA);
        $this->agendar($vendedor, 'Ontem', self::ONTEM);
        $this->agendar($vendedor, 'Cancelada', self::HOJE_CEDO, ['bo_ativo' => 0]);
        $this->agendar($vendedor, 'Arquivado', self::HOJE_CEDO, [], ['bo_ativo' => 0]);

        $this->enviarLembretes();

        Mail::assertNothingSent();
    }

    public function test_quem_desligou_o_lembrete_ou_esta_inativo_nao_recebe()
    {
        $this->agendar($this->criarUsuario(['bo_lembrete_email' => 0]), 'Cliente', self::HOJE_CEDO);
        $this->agendar($this->criarUsuario(['bo_ativo' => 0]), 'Cliente', self::HOJE_CEDO);

        $this->enviarLembretes();

        Mail::assertNothingSent();
    }

    public function test_cada_vendedor_recebe_so_as_proprias_ligacoes()
    {
        $primeiro = $this->criarUsuario();
        $segundo = $this->criarUsuario();
        $this->agendar($primeiro, 'Do Primeiro', self::HOJE_CEDO);
        $this->agendar($segundo, 'Do Segundo', self::HOJE_TARDE);

        $this->enviarLembretes();

        Mail::assertSent(LembreteDasLigacoesDoDia::class, 2);
        Mail::assertSent(LembreteDasLigacoesDoDia::class, function ($email) use ($segundo) {
            return $email->hasTo($segundo['email']) && ['Do Segundo'] === array_column($email->vendedor['ligacoes'], 'cliente');
        });
    }

    public function test_simulacao_informa_quantos_seriam_enviados_sem_enviar_nada()
    {
        $this->agendar($this->criarUsuario(), 'Cliente', self::HOJE_CEDO);
        $this->agendar($this->criarUsuario(), 'Cliente', self::HOJE_TARDE);

        $this->assertSame(0, Artisan::call('lembretes:ligacoes-do-dia', ['--simular' => true]));

        $this->assertStringContainsString('Lembretes que seriam enviados: 2', Artisan::output());
        Mail::assertNothingSent();
    }

    public function test_link_do_email_desliga_o_lembrete_sem_login()
    {
        $vendedor = $this->criarUsuario();
        $endereco = LembreteDeLigacoes::enderecoDoCancelamento($vendedor['id'], $vendedor['email']);

        $resposta = $this->get(parse_url($endereco, PHP_URL_PATH));

        $resposta->assertStatus(200)->assertSee('não vai mais receber');
        $this->assertEquals(0, $this->registro('usuario', $vendedor['id'])['bo_lembrete_email']);
    }

    public function test_link_com_assinatura_de_outro_usuario_nao_desliga_nada()
    {
        $vendedor = $this->criarUsuario();
        $outro = $this->criarUsuario();
        $assinaturaDoOutro = LembreteDeLigacoes::assinaturaDoCancelamento($outro['id'], $outro['email']);

        $resposta = $this->get("/api/lembretes/cancelar/{$vendedor['id']}/{$assinaturaDoOutro}");

        $resposta->assertStatus(400)->assertSee('não é válido');
        $this->assertEquals(1, $this->registro('usuario', $vendedor['id'])['bo_lembrete_email']);
    }

    public function test_link_de_usuario_inexistente_e_recusado()
    {
        $this->get('/api/lembretes/cancelar/999/abc')->assertStatus(400);
    }
}
