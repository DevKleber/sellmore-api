@php($contatos = array_map(fn ($indicado) => ['texto' => $indicado], $indicados))
<x-email.layout titulo="Indicações recebidas" previa="{{ $nomeDoCliente }} usou o seu link de indicação.">
    <x-email.titulo>Olá, {{ $nomeDoVendedor }}</x-email.titulo>
    <x-email.paragrafo>
        <strong>{{ $nomeDoCliente }}</strong> usou o seu link de indicação.
        Estes contatos já estão no card dele, como referidos:
    </x-email.paragrafo>

    <x-email.itens-com-visto :itens="$contatos" />

    <x-email.botao :href="config('wiseller.endereco_da_plataforma')">Abrir o Wiseller</x-email.botao>

    <x-slot:rodape>
        Você recebe este e-mail quando um cliente indica alguém pelo link que você enviou.
    </x-slot:rodape>
</x-email.layout>
