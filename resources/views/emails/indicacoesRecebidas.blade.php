<table style="font-family:Trebuchet ms,Arial,sans-serif;max-width:600px;width:100%; border: 1px solid #1D3055" cellspacing="0" cellpadding="0"
    border="0" align="center">
    <tbody>
        <tr style="background-color:#1D3055">
            <td style="padding:15px;color:#fff;font-size:24px" valign="middle">
                <img src="{{ config('wiseller.endereco_do_site') }}/assets/img/system/wiseller.png" width="40" alt="" style="vertical-align:middle">
                Wiseller
            </td>
        </tr>
        <tr>
            <td style="padding:20px;color:#666666">
                <h2 style="color:#333333">Olá, {{ $nomeDoVendedor }}</h2>
                <p><strong>{{ $nomeDoCliente }}</strong> usou o seu link de indicação. Estes contatos já estão no card dele, como referidos:</p>
                <ul>
                    @foreach ($indicados as $indicado)
                        <li style="color:#333333;padding:2px 0">{{ $indicado }}</li>
                    @endforeach
                </ul>
                <p style="text-align:center;padding-top:20px">
                    <a href="{{ config('wiseller.endereco_da_plataforma') }}"
                        style="background-color:#1D3055;color:#ffffff;padding:12px 24px;text-decoration:none;border-radius:6px">Abrir o Wiseller</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="color:#999999;font-size:12px;line-height:18px;padding:16px">
                Você recebe este e-mail quando um cliente indica alguém pelo link que você enviou.
            </td>
        </tr>
    </tbody>
</table>
