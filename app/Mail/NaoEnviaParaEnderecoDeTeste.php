<?php

namespace App\Mail;

use Illuminate\Mail\Events\MessageSending;

/**
 * Cancela o envio quando todos os destinatarios estao em dominios reservados para teste (RFC 6761),
 * que nunca chegam a ninguem. Protege o SMTP real das contas criadas por testes automatizados.
 */
class NaoEnviaParaEnderecoDeTeste
{
    private const DOMINIOS_RESERVADOS = ['test', 'example', 'invalid', 'localhost'];

    public function handle(MessageSending $evento)
    {
        $enderecos = array_map(function ($destinatario) {
            return $destinatario->getAddress();
        }, $evento->message->getTo());

        if (self::todosSaoDeTeste($enderecos)) {
            return false;
        }
    }

    public static function todosSaoDeTeste(array $enderecos)
    {
        if (0 === count($enderecos)) {
            return false;
        }

        foreach ($enderecos as $endereco) {
            if (!self::eDeTeste($endereco)) {
                return false;
            }
        }

        return true;
    }

    private static function eDeTeste($endereco)
    {
        $partes = explode('.', strtolower((string) $endereco));

        return in_array(end($partes), self::DOMINIOS_RESERVADOS, true);
    }
}
