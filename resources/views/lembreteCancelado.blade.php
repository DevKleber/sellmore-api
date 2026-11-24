<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Wiseller</title>
</head>
<body style="font-family:Arial,sans-serif;max-width:480px;margin:60px auto;padding:0 16px;color:#333333;text-align:center">
    <h1 style="color:#1D3055">Wiseller</h1>
    @if ($cancelado)
        <p>Pronto: você não vai mais receber o lembrete das ligações do dia.</p>
        <p>Para voltar a receber, ligue a opção em "Minha conta" na plataforma.</p>
    @else
        <p>Este link não é válido.</p>
    @endif
    <p><a href="{{ config('wiseller.endereco_da_plataforma') }}" style="color:#1D3055">Abrir o Wiseller</a></p>
</body>
</html>
