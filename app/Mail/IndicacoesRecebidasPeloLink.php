<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class IndicacoesRecebidasPeloLink extends Mailable
{
    public $nomeDoVendedor;
    public $nomeDoCliente;
    public $indicados;

    public function __construct(string $nomeDoVendedor, string $nomeDoCliente, array $indicados)
    {
        $this->nomeDoVendedor = $nomeDoVendedor;
        $this->nomeDoCliente = $nomeDoCliente;
        $this->indicados = $indicados;
    }

    public function build()
    {
        $total = count($this->indicados);
        $quantos = 1 === $total ? '1 pessoa' : "{$total} pessoas";

        return $this->view('emails.indicacoesRecebidas')->subject("Wiseller - {$this->nomeDoCliente} indicou {$quantos}");
    }
}
