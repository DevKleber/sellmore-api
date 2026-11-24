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
                <h2 style="color:#333333">Olá, {{ $vendedor['nome'] }}</h2>
                <p>Estas são as ligações que você agendou para hoje:</p>
                <table cellspacing="0" cellpadding="8" border="0" width="100%">
                    @foreach ($vendedor['ligacoes'] as $ligacao)
                        <tr>
                            <td style="border-bottom:1px solid #eeeeee;color:#1D3055;font-weight:bold" width="70">{{ $ligacao['hora'] }}</td>
                            <td style="border-bottom:1px solid #eeeeee;color:#333333">{{ $ligacao['cliente'] }}</td>
                        </tr>
                    @endforeach
                </table>
                <p style="text-align:center;padding-top:20px">
                    <a href="{{ $enderecoDaPlataforma }}"
                        style="background-color:#1D3055;color:#ffffff;padding:12px 24px;text-decoration:none;border-radius:6px">Abrir o Wiseller</a>
                </p>
            </td>
        </tr>
        <tr>
            <td style="color:#999999;font-size:12px;line-height:18px;padding:16px">
                Você recebe este e-mail nos dias em que tem ligação agendada.
                <a href="{{ $enderecoDoCancelamento }}" style="color:#999999;text-decoration:underline">Não quero mais receber</a>.
            </td>
        </tr>
    </tbody>
</table>
