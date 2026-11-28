<?php

namespace Tests\Support;

use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Faz o papel do servidor SMTP: anota quem recebeu e responde com erro para os
 * enderecos pedidos, como o Titan faz com caixa que nao existe ou com limite estourado.
 */
class CaixaDeEnvioFalsa extends AbstractTransport
{
    public $entregues = [];

    private $respostasDeErro;

    /**
     * @param array<string, array{0:int, 1:string}> $respostasDeErro endereco => [codigo, mensagem]
     */
    public function __construct(array $respostasDeErro)
    {
        parent::__construct();
        $this->respostasDeErro = $respostasDeErro;
    }

    protected function doSend(SentMessage $mensagem): void
    {
        $endereco = $mensagem->getEnvelope()->getRecipients()[0]->getAddress();
        if (isset($this->respostasDeErro[$endereco])) {
            [$codigo, $texto] = $this->respostasDeErro[$endereco];

            throw new UnexpectedResponseException("Expected response code \"250\" but got code \"{$codigo}\", with message \"{$texto}\".", $codigo);
        }

        $this->entregues[] = $endereco;
    }

    public function __toString(): string
    {
        return 'caixa-de-envio-falsa://';
    }
}
