@props(['titulo', 'previa' => ''])
@php
    $cor = \App\Mail\AparenciaDoEmail::COR;
    $fonte = \App\Mail\AparenciaDoEmail::FONTE;
    $enderecoDoSite = config('wiseller.endereco_do_site');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:0;background-color:{{ $cor['fundo'] }}">
    @if ($previa)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $previa }}</div>
    @endif
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ $cor['fundo'] }}">
        <tr>
            <td align="center" style="padding:24px 12px">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                    style="max-width:600px;font-family:{{ $fonte }}">
                    <tr>
                        <td style="padding:0 8px 16px">
                            <a href="{{ $enderecoDoSite }}" style="text-decoration:none;color:{{ $cor['tinta'] }}">
                                <img src="{{ $enderecoDoSite }}/assets/img/system/wiseller.png" width="36" height="37" alt=""
                                    style="vertical-align:middle;border:0">
                                <span style="vertical-align:middle;font-size:20px;font-weight:800;color:{{ $cor['tinta'] }};padding-left:6px">Wiseller</span>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:{{ $cor['superficie'] }};border:1px solid {{ $cor['borda'] }};border-radius:20px;padding:32px 28px">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 8px 0;font-size:12px;line-height:18px;color:{{ $cor['apagado'] }}">
                            @isset($rodape)
                                {{ $rodape }}<br>
                            @endisset
                            <a href="{{ $enderecoDoSite }}" style="color:{{ $cor['apagado'] }}">wiseller.com.br</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
