@php($cor = \App\Mail\AparenciaDoEmail::COR)
<x-email.layout titulo="Ligações de hoje" previa="As ligações que você agendou para hoje no Wiseller.">
    <x-email.titulo>Olá, {{ $vendedor['nome'] }}</x-email.titulo>
    <x-email.paragrafo>Estas são as ligações que você agendou para hoje:</x-email.paragrafo>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px">
        @foreach ($vendedor['ligacoes'] as $ligacao)
            <tr>
                <td width="72" style="padding:12px 0;border-bottom:1px solid {{ $cor['borda'] }};font-size:15px;font-weight:700;color:{{ $cor['principal'] }}">{{ $ligacao['hora'] }}</td>
                <td style="padding:12px 0;border-bottom:1px solid {{ $cor['borda'] }};font-size:15px;color:{{ $cor['tinta'] }}">{{ $ligacao['cliente'] }}</td>
            </tr>
        @endforeach
    </table>

    <x-email.botao :href="$enderecoDaPlataforma">Abrir o Wiseller</x-email.botao>

    <x-slot:rodape>
        Você recebe este e-mail nos dias em que tem ligação agendada.
        <a href="{{ $enderecoDoCancelamento }}" style="color:{{ $cor['apagado'] }}">Não quero mais receber</a>.
    </x-slot:rodape>
</x-email.layout>
