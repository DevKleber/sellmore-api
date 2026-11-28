<?php

namespace App\Mail;

use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

/**
 * Separa a recusa que vale so para um endereco (a caixa nao existe) das falhas que
 * valem para qualquer envio (limite da caixa de saida, conexao, autenticacao).
 */
class RecusaDoDestinatario
{
    private const CODIGOS_DE_DESTINATARIO_INVALIDO = [550, 551, 553];
    private const SINAIS_DE_LIMITE_DE_ENVIO = '/\b(limit|limits|quota|rate|too many|exceeded)\b/i';

    public static function eDefinitiva(\Throwable $falha): bool
    {
        if (!$falha instanceof UnexpectedResponseException) {
            return false;
        }

        if (!in_array($falha->getCode(), self::CODIGOS_DE_DESTINATARIO_INVALIDO, true)) {
            return false;
        }

        return !preg_match(self::SINAIS_DE_LIMITE_DE_ENVIO, $falha->getMessage());
    }
}
