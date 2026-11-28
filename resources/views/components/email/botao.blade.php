@props(['href'])
@php($cor = \App\Mail\AparenciaDoEmail::COR)
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 20px">
    <tr>
        <td style="border-radius:12px;background-color:{{ $cor['principal'] }}">
            <a href="{{ $href }}"
                style="display:inline-block;padding:14px 28px;font-size:16px;font-weight:700;color:{{ $cor['sobrePrincipal'] }};text-decoration:none;border-radius:12px">{{ $slot }}</a>
        </td>
    </tr>
</table>
