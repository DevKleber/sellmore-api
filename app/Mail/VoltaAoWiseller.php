<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * Convite para quem deixou de usar o Wiseller voltar e ver as novidades.
 */
class VoltaAoWiseller extends Mailable
{
    private const ASSUNTO = 'o Wiseller mudou. Vem ver?';

    public $primeiroNome;
    public $enderecoDoLogin;
    public $enderecoDaRecuperacao;
    public $enderecoDasNovidades;

    public function __construct(?string $nomeDoVendedor)
    {
        $plataforma = config('wiseller.endereco_da_plataforma');

        $this->primeiroNome = self::primeiroNome((string) $nomeDoVendedor);
        $this->enderecoDoLogin = "{$plataforma}/login";
        $this->enderecoDaRecuperacao = "{$plataforma}/request-recover-password";
        $this->enderecoDasNovidades = config('wiseller.pagina_das_novidades');
    }

    public function build()
    {
        return $this->view('emails.voltaAoWiseller')->subject($this->assunto());
    }

    private function assunto(): string
    {
        if ('' === $this->primeiroNome) {
            return ucfirst(self::ASSUNTO);
        }

        return "{$this->primeiroNome}, ".self::ASSUNTO;
    }

    /**
     * Os nomes foram digitados de qualquer jeito no cadastro ("JOAO SILVA", "maria").
     */
    public static function primeiroNome(string $nomeCompleto): string
    {
        $palavras = preg_split('/\s+/', trim($nomeCompleto), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($palavras)) {
            return '';
        }

        return mb_convert_case($palavras[0], MB_CASE_TITLE, 'UTF-8');
    }
}
