<?php

namespace App\Mail;

interface VerificadorDeDominio
{
    /**
     * Diz se o dominio declara um servidor que recebe e-mail.
     */
    public function recebeEmail(string $dominio): bool;
}
