<?php

namespace Tests\Feature;

use App\CampanhaDeRetorno;
use App\Mail\VerificadorDeDominio;
use App\Mail\VoltaAoWiseller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;

class CampanhaDeRetornoTest extends ApiTestCase
{
    private const AGORA = '2026-11-28 10:00:00';
    private const ACESSO_ANTIGO = '2023-05-10 10:00:00';
    private const ACESSO_RECENTE = '2026-11-20 10:00:00';
    private const DOMINIO_QUE_NAO_RECEBE = 'semcorreio.test';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(self::AGORA);
        $this->app->instance(VerificadorDeDominio::class, new class (self::DOMINIO_QUE_NAO_RECEBE) implements VerificadorDeDominio {
            public function __construct(private string $dominioQueNaoRecebe)
            {
            }

            public function recebeEmail(string $dominio): bool
            {
                return $dominio !== $this->dominioQueNaoRecebe;
            }
        });
    }

    private function enviarCampanha(array $opcoes = []): int
    {
        return Artisan::call('campanha:volta-ao-wiseller', $opcoes);
    }

    private function contaSemAcessoRecente(array $atributos = []): array
    {
        $usuario = $this->criarUsuario($atributos);
        $this->criarLogDeAcesso($usuario['id'], ['created_at' => self::ACESSO_ANTIGO]);

        return $usuario;
    }

    private function registrarEnvioAnterior(int $idUsuario, string $quando): void
    {
        DB::table('envio_de_campanha')->insert([
            'id_usuario' => $idUsuario,
            'campanha' => CampanhaDeRetorno::NOME,
            'enviado_em' => $quando,
        ]);
    }

    public function test_conta_sem_acesso_recente_recebe_o_convite_e_o_envio_fica_registrado()
    {
        $usuario = $this->contaSemAcessoRecente();

        $this->assertSame(0, $this->enviarCampanha());

        Mail::assertSent(VoltaAoWiseller::class, fn ($email) => $email->hasTo($usuario['email']));
        $this->assertSame(1, DB::table('envio_de_campanha')->where('id_usuario', $usuario['id'])->count());
    }

    public function test_conta_que_nunca_acessou_tambem_recebe()
    {
        $usuario = $this->criarUsuario();

        $this->enviarCampanha();

        Mail::assertSent(VoltaAoWiseller::class, fn ($email) => $email->hasTo($usuario['email']));
    }

    public function test_conta_desativada_com_acesso_recente_ou_dominio_que_nao_recebe_fica_de_fora()
    {
        $this->contaSemAcessoRecente(['bo_ativo' => 0]);
        $recente = $this->criarUsuario();
        $this->criarLogDeAcesso($recente['id'], ['created_at' => self::ACESSO_RECENTE]);
        $this->contaSemAcessoRecente(['email' => 'alguem@'.self::DOMINIO_QUE_NAO_RECEBE]);
        $this->contaSemAcessoRecente(['email' => 'sem-arroba.wiseller.test']);

        $this->enviarCampanha();

        Mail::assertNothingSent();
    }

    public function test_quem_ja_recebeu_nao_recebe_de_novo()
    {
        $usuario = $this->contaSemAcessoRecente();

        $this->enviarCampanha();
        $this->enviarCampanha();

        Mail::assertSent(VoltaAoWiseller::class, 1);
        $this->assertSame(1, DB::table('envio_de_campanha')->where('id_usuario', $usuario['id'])->count());
    }

    public function test_cada_execucao_envia_no_maximo_um_lote()
    {
        foreach (range(1, 5) as $indice) {
            $this->contaSemAcessoRecente();
        }

        $this->enviarCampanha(['--lote' => 2]);

        Mail::assertSent(VoltaAoWiseller::class, 2);
    }

    public function test_limite_diario_conta_o_que_ja_saiu_hoje_e_nao_o_de_ontem()
    {
        $ontem = $this->contaSemAcessoRecente();
        $hoje = $this->contaSemAcessoRecente();
        $this->registrarEnvioAnterior($ontem['id'], '2026-11-27 15:00:00');
        $this->registrarEnvioAnterior($hoje['id'], '2026-11-28 09:00:00');
        foreach (range(1, 5) as $indice) {
            $this->contaSemAcessoRecente();
        }

        $this->enviarCampanha(['--lote' => 10, '--limite-diario' => 3]);

        Mail::assertSent(VoltaAoWiseller::class, 2);
    }

    public function test_simular_so_informa_quantos_sairiam()
    {
        foreach (range(1, 3) as $indice) {
            $this->contaSemAcessoRecente();
        }

        $this->enviarCampanha(['--simular' => true, '--lote' => 2]);

        Mail::assertNothingSent();
        $this->assertStringContainsString('Pendentes: 3. Sairiam agora: 2.', Artisan::output());
        $this->assertSame(0, DB::table('envio_de_campanha')->count());
    }

    public function test_envio_de_teste_vai_so_para_a_conta_pedida_e_nao_fica_registrado()
    {
        $this->contaSemAcessoRecente();
        $alvo = $this->criarUsuario(['email' => 'Dono@Wiseller.test']);
        $this->criarLogDeAcesso($alvo['id'], ['created_at' => self::ACESSO_RECENTE]);

        $this->assertSame(0, $this->enviarCampanha(['--para' => 'dono@wiseller.test']));

        Mail::assertSent(VoltaAoWiseller::class, 1);
        Mail::assertSent(VoltaAoWiseller::class, fn ($email) => $email->hasTo('Dono@Wiseller.test'));
        $this->assertSame(0, DB::table('envio_de_campanha')->count());
    }

    public function test_envio_de_teste_para_email_sem_conta_falha()
    {
        $this->assertSame(1, $this->enviarCampanha(['--para' => 'ninguem@wiseller.test']));

        Mail::assertNothingSent();
    }

    public function test_email_chama_pelo_primeiro_nome_e_traz_os_links()
    {
        $this->contaSemAcessoRecente(['nome' => '  JOÃO da silva ']);

        $this->enviarCampanha();

        $email = Mail::sent(VoltaAoWiseller::class)->first();
        $corpo = $email->render();
        $this->assertSame('João, o Wiseller mudou. Vem ver?', $email->build()->subject);
        $this->assertStringContainsString('João, o Wiseller está de cara nova', $corpo);
        $this->assertStringContainsString('https://app.wiseller.com.br/login', $corpo);
        $this->assertStringContainsString('https://app.wiseller.com.br/request-recover-password', $corpo);
        $this->assertStringContainsString(config('wiseller.pagina_das_novidades'), $corpo);
    }

    public function test_conta_sem_nome_recebe_assunto_e_titulo_sem_nome()
    {
        $email = new VoltaAoWiseller(null);

        $this->assertSame('O Wiseller mudou. Vem ver?', $email->build()->subject);
        $this->assertStringContainsString('O Wiseller está de cara nova', $email->render());
    }

    public function test_excluir_a_conta_apaga_o_registro_de_envio()
    {
        $usuario = $this->contaSemAcessoRecente();
        $this->enviarCampanha();

        DB::table('usuario_log')->where('id_user', $usuario['id'])->delete();
        DB::table('usuario')->where('id', $usuario['id'])->delete();

        $this->assertSame(0, DB::table('envio_de_campanha')->count());
    }
}
