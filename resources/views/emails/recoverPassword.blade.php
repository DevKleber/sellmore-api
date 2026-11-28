<x-email.layout titulo="Troca de senha" previa="O link para criar uma senha nova no Wiseller.">
    <x-email.titulo>Olá, {{ $user['no_pessoa'] }}</x-email.titulo>
    <x-email.paragrafo>Recebemos um pedido para trocar a senha da sua conta no Wiseller.</x-email.paragrafo>

    <x-email.botao :href="$user['link']">Criar uma senha nova</x-email.botao>

    <x-email.paragrafo discreto>
        O link vale por 24 horas e só pode ser usado uma vez.
        Se você não pediu a troca, ignore este e-mail: a sua senha continua a mesma.
    </x-email.paragrafo>

    <x-slot:rodape>
        Você recebeu este e-mail porque alguém pediu a troca da senha da sua conta no Wiseller.
    </x-slot:rodape>
</x-email.layout>
