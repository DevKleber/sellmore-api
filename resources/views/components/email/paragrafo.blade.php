@props(['discreto' => false])
@php
    $cor = \App\Mail\AparenciaDoEmail::COR;
    $estilo = $discreto
        ? "font-size:14px;line-height:21px;color:{$cor['apagado']}"
        : "font-size:16px;line-height:24px;color:{$cor['texto']}";
@endphp
<p style="margin:0 0 20px;{{ $estilo }}">{{ $slot }}</p>
