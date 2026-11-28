<?php

namespace App\Mail;

/**
 * Cores e fonte dos e-mails, as mesmas do site e da plataforma. Os clientes de e-mail
 * ignoram folhas de estilo, entao os componentes em resources/views/components/email
 * aplicam estes valores direto em cada elemento.
 */
class AparenciaDoEmail
{
    public const COR = [
        'principal' => '#0b7f73',
        'principalSuave' => '#e1f3f0',
        'fundo' => '#f4f6f8',
        'superficie' => '#ffffff',
        'borda' => '#e3e8ef',
        'tinta' => '#0f172a',
        'texto' => '#334155',
        'apagado' => '#5b6b82',
        'sobrePrincipal' => '#ffffff',
    ];

    public const FONTE = "Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
}
