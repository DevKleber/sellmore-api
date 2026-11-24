<?php

namespace App;

use Illuminate\Support\Facades\DB;

/**
 * Ligacoes agendadas para hoje, separadas por vendedor que aceita receber o lembrete.
 */
class LembreteDeLigacoes
{
    private const FORMATO_DA_HORA = 'H:i';

    public static function deHojePorVendedor()
    {
        $compromissos = DB::table('calendar')
            ->join('customers', 'customers.id', '=', 'calendar.id_customers')
            ->join('usuario', 'usuario.id', '=', 'calendar.id_usuario')
            ->where('calendar.bo_ativo', true)
            ->where('customers.bo_ativo', true)
            ->where('usuario.bo_ativo', true)
            ->where('usuario.bo_lembrete_email', true)
            ->whereBetween('calendar.date', [now()->startOfDay(), now()->endOfDay()])
            ->orderBy('calendar.date')
            ->select('usuario.id', 'usuario.nome', 'usuario.email', 'calendar.date', 'customers.name')
            ->get();

        return $compromissos->groupBy('id')->map(function ($doVendedor) {
            $vendedor = $doVendedor->first();

            return [
                'id' => $vendedor->id,
                'nome' => $vendedor->nome,
                'email' => $vendedor->email,
                'ligacoes' => $doVendedor->map(function ($compromisso) {
                    return ['hora' => date(self::FORMATO_DA_HORA, strtotime($compromisso->date)), 'cliente' => $compromisso->name];
                })->all(),
            ];
        })->values()->all();
    }

    public static function assinaturaDoCancelamento($idUsuario, $email)
    {
        return hash_hmac('sha256', "lembrete|{$idUsuario}|{$email}", config('app.key'));
    }

    public static function enderecoDoCancelamento($idUsuario, $email)
    {
        $assinatura = self::assinaturaDoCancelamento($idUsuario, $email);

        return config('wiseller.endereco_da_api')."/lembretes/cancelar/{$idUsuario}/{$assinatura}";
    }
}
