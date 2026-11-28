@props(['itens'])
@php($cor = \App\Mail\AparenciaDoEmail::COR)
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 8px">
    @foreach ($itens as $item)
        <tr>
            <td width="36" valign="top" style="padding:0 0 14px">
                <div style="width:24px;height:24px;line-height:24px;border-radius:12px;text-align:center;font-size:14px;font-weight:700;background-color:{{ $cor['principalSuave'] }};color:{{ $cor['principal'] }}">&#10003;</div>
            </td>
            <td valign="top" style="padding:0 0 14px;font-size:15px;line-height:22px;color:{{ $cor['texto'] }}">
                @isset($item['titulo'])
                    <strong style="color:{{ $cor['tinta'] }}">{{ $item['titulo'] }}.</strong>
                @endisset
                {{ $item['texto'] }}
            </td>
        </tr>
    @endforeach
</table>
