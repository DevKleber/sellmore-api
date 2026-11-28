<?php

namespace Tests\Feature;

use App\Mail\VerificadorDeDominio;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;
use Tests\Support\CaixaDeEnvioFalsa;

/**
 * Envio de verdade pela camada de e-mail, com o SMTP trocado por uma caixa falsa que
 * recusa enderecos escolhidos. Os e-mails nao usam dominio .test porque esses sao
 * descartados antes de chegar ao SMTP.
 */
class CampanhaDeRetornoRecusaTest extends ApiTestCase
{
    private const CAIXA_INEXISTENTE = [550, '550 5.4.6 An email sent to this address has been rejected with the reason \'Mailbox does not exist\' within the last 30 days.'];
    private const LIMITE_DE_ENVIO = [550, '550 5.4.6 Daily sending limit exceeded for this account'];
    private const FALHA_TEMPORARIA = [451, '451 4.7.1 Try again later'];

    private $caixa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(VerificadorDeDominio::class, new class implements VerificadorDeDominio {
            public function recebeEmail(string $dominio): bool
            {
                return true;
            }
        });
    }

    private function usarCaixaQueResponde(array $respostasDeErro): void
    {
        $this->caixa = new CaixaDeEnvioFalsa($respostasDeErro);
        Mail::extend('caixa-falsa', fn () => $this->caixa);
        config(['mail.mailers.caixa-falsa' => ['transport' => 'caixa-falsa'], 'mail.default' => 'caixa-falsa']);
        Mail::purge('caixa-falsa');
    }

    private function contas(int $quantidade): array
    {
        return array_map(
            fn ($indice) => $this->criarUsuario(['email' => "conta{$indice}@exemplo.com.br"]),
            range(1, $quantidade)
        );
    }

    private function enviarCampanha(): void
    {
        $this->assertSame(0, Artisan::call('campanha:volta-ao-wiseller', ['--lote' => 10]));
    }

    private function recusasRegistradas(): array
    {
        return DB::table('envio_de_campanha')->whereNotNull('motivo_da_recusa')->pluck('id_usuario')->all();
    }

    public function test_caixa_inexistente_fica_registrada_como_recusa_e_o_lote_segue()
    {
        [$primeira, $inexistente, $terceira] = $this->contas(3);
        $this->usarCaixaQueResponde([$inexistente['email'] => self::CAIXA_INEXISTENTE]);

        $this->enviarCampanha();

        $this->assertSame([$primeira['email'], $terceira['email']], $this->caixa->entregues);
        $this->assertSame([$inexistente['id']], $this->recusasRegistradas());
        $this->assertStringContainsString('Enviados: 2. Recusados: 1. Pendentes: 0.', Artisan::output());
    }

    public function test_endereco_recusado_nao_e_tentado_de_novo_no_lote_seguinte()
    {
        [, $inexistente] = $this->contas(2);
        $this->usarCaixaQueResponde([$inexistente['email'] => self::CAIXA_INEXISTENTE]);

        $this->enviarCampanha();
        $this->enviarCampanha();

        $this->assertCount(1, $this->caixa->entregues);
        $this->assertSame(2, DB::table('envio_de_campanha')->count());
    }

    public function test_limite_de_envio_interrompe_o_lote_sem_registrar_o_destinatario()
    {
        [$primeira, $noLimite] = $this->contas(3);
        $this->usarCaixaQueResponde([$noLimite['email'] => self::LIMITE_DE_ENVIO]);

        $this->enviarCampanha();

        $this->assertSame([$primeira['email']], $this->caixa->entregues);
        $this->assertSame([], $this->recusasRegistradas());
        $this->assertSame(1, DB::table('envio_de_campanha')->count());
    }

    public function test_falha_temporaria_interrompe_o_lote_e_o_destinatario_fica_para_depois()
    {
        [$comFalha] = $this->contas(2);
        $this->usarCaixaQueResponde([$comFalha['email'] => self::FALHA_TEMPORARIA]);

        $this->enviarCampanha();

        $this->assertSame([], $this->caixa->entregues);
        $this->assertSame(0, DB::table('envio_de_campanha')->count());
    }

    public function test_recusas_demais_em_sequencia_interrompem_o_lote()
    {
        $contas = $this->contas(7);
        $this->usarCaixaQueResponde(array_fill_keys(array_column($contas, 'email'), self::CAIXA_INEXISTENTE));

        $this->enviarCampanha();

        $this->assertCount(5, $this->recusasRegistradas());
    }
}
