<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class LembreteDasLigacoesDoDia extends Mailable
{
    public $vendedor;
    public $enderecoDaPlataforma;
    public $enderecoDoCancelamento;

    public function __construct(array $vendedor, string $enderecoDaPlataforma, string $enderecoDoCancelamento)
    {
        $this->vendedor = $vendedor;
        $this->enderecoDaPlataforma = $enderecoDaPlataforma;
        $this->enderecoDoCancelamento = $enderecoDoCancelamento;
    }

    public function build()
    {
        $total = count($this->vendedor['ligacoes']);
        $assunto = 1 === $total ? 'Você tem 1 ligação agendada para hoje' : "Você tem {$total} ligações agendadas para hoje";

        return $this->view('emails.lembreteDasLigacoes')->subject("Wiseller - {$assunto}");
    }
}
