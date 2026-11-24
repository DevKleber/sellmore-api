<?php

namespace Tests\Unit;

use App\Mail\NaoEnviaParaEnderecoDeTeste;
use Illuminate\Mail\Events\MessageSending;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

class NaoEnviaParaEnderecoDeTesteTest extends TestCase
{
    private function resultadoDoEnvioPara(string ...$enderecos)
    {
        $mensagem = (new Email())->to(...$enderecos);

        return (new NaoEnviaParaEnderecoDeTeste())->handle(new MessageSending($mensagem));
    }

    public function test_cancela_o_envio_para_dominios_reservados_para_teste()
    {
        $this->assertFalse($this->resultadoDoEnvioPara('e2e+1@wiseller.test'));
        $this->assertFalse($this->resultadoDoEnvioPara('alguem@EXEMPLO.EXAMPLE', 'outro@servidor.invalid'));
    }

    public function test_nao_interfere_no_envio_para_endereco_real()
    {
        $this->assertNull($this->resultadoDoEnvioPara('vendedor@empresa.com.br'));
        $this->assertNull($this->resultadoDoEnvioPara('teste@gmail.com'));
    }

    public function test_envia_quando_ha_ao_menos_um_destinatario_real()
    {
        $this->assertNull($this->resultadoDoEnvioPara('e2e+1@wiseller.test', 'vendedor@empresa.com.br'));
    }
}
