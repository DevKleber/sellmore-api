@php
    $cor = \App\Mail\AparenciaDoEmail::COR;
    $novidades = [
        ['titulo' => 'Visual novo', 'texto' => 'Telas mais limpas, fáceis de ler e confortáveis no celular.'],
        ['titulo' => 'Muito mais rápido', 'texto' => 'A lista de leads abre quase na hora, mesmo para quem tem milhares de contatos.'],
        ['titulo' => 'O que dava erro foi corrigido', 'texto' => 'Alterar e arquivar contatos e remarcar ou remover agendamentos voltaram a funcionar.'],
        ['titulo' => 'Para quem ligar hoje', 'texto' => 'Se você tiver retornos marcados para o dia, recebe de manhã um e-mail com a lista.'],
        ['titulo' => 'Link de indicação', 'texto' => 'Mande um link para o seu cliente e ele mesmo cadastra os contatos que quer indicar, inclusive importando do WhatsApp.'],
    ];
@endphp
<x-email.layout titulo="O Wiseller mudou" previa="Visual novo, lista que abre na hora e um aviso diário de para quem ligar.">
    <x-email.titulo>{{ $primeiroNome ? "{$primeiroNome}, o" : 'O' }} Wiseller está de cara nova</x-email.titulo>
    <x-email.paragrafo>
        Faz um tempo que você não entra no Wiseller, e a plataforma mudou bastante.
        Seus leads e referidos continuam salvos do jeito que você deixou.
    </x-email.paragrafo>

    <a href="{{ $enderecoDoLogin }}" style="text-decoration:none">
        <img src="{{ $message->embed(resource_path('images/email/tela-nova.jpg')) }}" width="542"
            alt="A tela principal do Wiseller com o visual novo"
            style="display:block;width:100%;max-width:542px;height:auto;border:1px solid {{ $cor['borda'] }};border-radius:12px">
    </a>

    <x-email.subtitulo>O que tem de novo</x-email.subtitulo>
    <x-email.itens-com-visto :itens="$novidades" />

    <x-email.botao :href="$enderecoDoLogin">Entrar no Wiseller</x-email.botao>
    <x-email.paragrafo discreto>
        Para entrar, use este mesmo e-mail. Esqueceu a senha?
        <x-email.link :href="$enderecoDaRecuperacao">Crie uma nova aqui</x-email.link>.
    </x-email.paragrafo>

    <x-email.paragrafo>
        Quer ver tudo com imagens? <x-email.link :href="$enderecoDasNovidades">Conheça as novidades</x-email.link>
    </x-email.paragrafo>
    <x-email.paragrafo>
        O Wiseller continua gratuito. Se tiver dúvida ou sugestão, é só responder este e-mail.
    </x-email.paragrafo>
    <x-email.paragrafo>
        Um abraço,<br>
        <strong style="color:{{ $cor['tinta'] }}">Kleber, do Wiseller</strong>
    </x-email.paragrafo>

    <x-slot:rodape>
        Você recebeu esta mensagem porque tem uma conta no Wiseller com este e-mail.
        Se não quiser mais receber, responda com "sair".
    </x-slot:rodape>
</x-email.layout>
