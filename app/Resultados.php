<?php

namespace App;

use Illuminate\Support\Carbon;

/**
 * Numeros do vendedor: indicacoes recebidas, clientes atendidos e vendas, por periodo.
 */
class Resultados
{
    private const STATUS_ABERTO = 'a';
    private const STATUS_COMPROU = 'c';
    private const MESES_DO_HISTORICO = 6;
    private const FORMATO_DO_MES_NO_BANCO = '%Y-%m';
    private const FORMATO_DO_MES = 'Y-m';
    private const PERCENTUAL_TOTAL = 100;

    public static function doUsuario($idUsuario)
    {
        return [
            'clientes' => Customers::where('id_usuario', $idUsuario)->count(),
            'semana' => self::doPeriodo($idUsuario, now()->startOfWeek()),
            'mes' => self::doPeriodo($idUsuario, now()->startOfMonth()),
            'total' => self::doPeriodo($idUsuario, null),
            'historico' => self::historico($idUsuario),
        ];
    }

    private static function doPeriodo($idUsuario, ?Carbon $inicio)
    {
        $atendidos = self::desde(self::atendidos($idUsuario), 'status_alterado_em', $inicio)->count();
        $vendas = self::desde(self::vendas($idUsuario), 'status_alterado_em', $inicio)->count();

        return [
            'indicacoes' => self::desde(self::indicacoes($idUsuario), 'created_at', $inicio)->count(),
            'atendidos' => $atendidos,
            'vendas' => $vendas,
            'conversao' => self::percentual($vendas, $atendidos),
        ];
    }

    private static function indicacoes($idUsuario)
    {
        return Customers::where('id_usuario', $idUsuario)->whereNotNull('id_parent');
    }

    private static function atendidos($idUsuario)
    {
        return Customers::where('id_usuario', $idUsuario)->where('status', '<>', self::STATUS_ABERTO);
    }

    private static function vendas($idUsuario)
    {
        return Customers::where('id_usuario', $idUsuario)->where('status', self::STATUS_COMPROU);
    }

    private static function desde($consulta, $coluna, ?Carbon $inicio)
    {
        if (null === $inicio) {
            return $consulta;
        }

        return $consulta->where($coluna, '>=', $inicio);
    }

    private static function percentual($parte, $todo)
    {
        if (0 === $todo) {
            return 0;
        }

        return (int) round($parte * self::PERCENTUAL_TOTAL / $todo);
    }

    /**
     * Indicacoes e vendas de cada um dos ultimos meses, do mais antigo para o atual.
     */
    private static function historico($idUsuario)
    {
        $primeiroMes = now()->startOfMonth()->subMonths(self::MESES_DO_HISTORICO - 1);
        $indicacoes = self::totalPorMes(self::indicacoes($idUsuario), 'created_at', $primeiroMes);
        $vendas = self::totalPorMes(self::vendas($idUsuario), 'status_alterado_em', $primeiroMes);

        $historico = [];
        for ($mes = $primeiroMes->copy(); $mes->lte(now()); $mes->addMonth()) {
            $chave = $mes->format(self::FORMATO_DO_MES);
            $historico[] = [
                'mes' => $chave,
                'indicacoes' => (int) ($indicacoes[$chave] ?? 0),
                'vendas' => (int) ($vendas[$chave] ?? 0),
            ];
        }

        return $historico;
    }

    private static function totalPorMes($consulta, $coluna, Carbon $primeiroMes)
    {
        $formato = self::FORMATO_DO_MES_NO_BANCO;

        return $consulta->where($coluna, '>=', $primeiroMes)
            ->selectRaw("DATE_FORMAT({$coluna}, '{$formato}') as mes, count(*) as total")
            ->groupBy('mes')
            ->pluck('total', 'mes');
    }
}
