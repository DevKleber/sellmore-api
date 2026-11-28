{{-- Sem quebra de linha no fim do arquivo: o link fica no meio de frases e a quebra viraria um espaco antes da pontuacao. --}}
@props(['href'])
@php($cor = \App\Mail\AparenciaDoEmail::COR)
<a href="{{ $href }}" style="color:{{ $cor['principal'] }};font-weight:600">{{ $slot }}</a>