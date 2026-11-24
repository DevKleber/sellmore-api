<?php

namespace App;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Numeros da plataforma para o administrador: contas criadas, ativacao e retorno.
 */
class MetricasDeUso
{
    private const SEMANAS_ACOMPANHADAS = 8;
    private const DIAS_DA_SEMANA = 7;
    private const DIAS_DO_MES = 30;
    private const DIAS_DO_HISTORICO_DE_ACESSOS = 14;
    private const CONTAS_RECENTES_LISTADAS = 20;
    private const STATUS_COMPROU = 'c';
    private const FORMATO_DA_DATA = 'Y-m-d';

    public static function resumo()
    {
        return [
            'contas' => [
                'total' => DB::table('usuario')->count(),
                'ultimos7Dias' => self::contasCriadasDesde(self::DIAS_DA_SEMANA),
                'ultimos30Dias' => self::contasCriadasDesde(self::DIAS_DO_MES),
            ],
            'ativacao' => [
                'comCliente' => self::contasComCliente(false),
                'comIndicacao' => self::contasComCliente(true),
            ],
            'ativos' => [
                'ultimos7Dias' => self::contasQueAcessaramDesde(self::DIAS_DA_SEMANA),
                'ultimos30Dias' => self::contasQueAcessaramDesde(self::DIAS_DO_MES),
            ],
            'semanas' => self::funilPorSemanaDeCadastro(),
            'hoje' => self::atividadeDeHoje(),
            'acessosPorDia' => self::contasQueAcessaramPorDia(),
            'contasRecentes' => self::contasRecentes(),
        ];
    }

    private static function atividadeDeHoje()
    {
        $inicioDoDia = now()->startOfDay();

        return [
            'contasQueAcessaram' => DB::table('usuario_log')->where('created_at', '>=', $inicioDoDia)->distinct()->count('id_user'),
            'contasNovas' => DB::table('usuario')->where('created_at', '>=', $inicioDoDia)->count(),
            'clientesCadastrados' => DB::table('customers')->where('created_at', '>=', $inicioDoDia)->count(),
            'vendas' => DB::table('customers')->where('status', self::STATUS_COMPROU)->where('status_alterado_em', '>=', $inicioDoDia)->count(),
        ];
    }

    /**
     * Quantas contas diferentes acessaram em cada um dos ultimos dias, do mais antigo para hoje.
     */
    private static function contasQueAcessaramPorDia()
    {
        $primeiroDia = now()->startOfDay()->subDays(self::DIAS_DO_HISTORICO_DE_ACESSOS - 1);
        $porDia = DB::table('usuario_log')
            ->where('created_at', '>=', $primeiroDia)
            ->selectRaw('DATE(created_at) as dia, count(distinct id_user) as contas')
            ->groupBy('dia')
            ->pluck('contas', 'dia');

        $dias = [];
        for ($dia = $primeiroDia->copy(); $dia->lte(now()); $dia->addDay()) {
            $chave = $dia->format(self::FORMATO_DA_DATA);
            $dias[] = ['dia' => $chave, 'contas' => (int) ($porDia[$chave] ?? 0)];
        }

        return $dias;
    }

    private static function contasRecentes()
    {
        return DB::table('usuario')
            ->orderByDesc('usuario.id')
            ->limit(self::CONTAS_RECENTES_LISTADAS)
            ->select('usuario.id', 'usuario.nome', 'usuario.email', 'usuario.created_at as criadaEm')
            ->selectRaw('(SELECT count(*) FROM customers WHERE customers.id_usuario = usuario.id) AS clientes')
            ->selectRaw('(SELECT max(created_at) FROM usuario_log WHERE usuario_log.id_user = usuario.id) AS ultimoAcesso')
            ->get();
    }

    private static function contasCriadasDesde($dias)
    {
        return DB::table('usuario')->where('created_at', '>=', now()->subDays($dias))->count();
    }

    private static function contasComCliente($somenteIndicados)
    {
        $consulta = DB::table('customers');
        if ($somenteIndicados) {
            $consulta->whereNotNull('id_parent');
        }

        return $consulta->distinct()->count('id_usuario');
    }

    private static function contasQueAcessaramDesde($dias)
    {
        return DB::table('usuario_log')->where('created_at', '>=', now()->subDays($dias))->distinct()->count('id_user');
    }

    /**
     * Para cada semana: quantos criaram conta, quantos cadastraram um cliente e quantos voltaram depois de sete dias.
     */
    private static function funilPorSemanaDeCadastro()
    {
        $primeiraSemana = now()->startOfWeek()->subWeeks(self::SEMANAS_ACOMPANHADAS - 1);
        $semanas = self::semanasVazias($primeiraSemana);

        foreach (self::contasCriadasComSinaisDeUso($primeiraSemana) as $conta) {
            $semana = Carbon::parse($conta->created_at)->startOfWeek()->format(self::FORMATO_DA_DATA);
            if (!isset($semanas[$semana])) {
                continue;
            }

            ++$semanas[$semana]['criaram'];
            $semanas[$semana]['cadastraramCliente'] += (int) $conta->tem_cliente;
            $semanas[$semana]['voltaram'] += (int) $conta->voltou;
        }

        return array_values($semanas);
    }

    private static function semanasVazias(Carbon $primeiraSemana)
    {
        $semanas = [];
        for ($inicio = $primeiraSemana->copy(); $inicio->lte(now()); $inicio->addWeek()) {
            $chave = $inicio->format(self::FORMATO_DA_DATA);
            $semanas[$chave] = ['inicio' => $chave, 'criaram' => 0, 'cadastraramCliente' => 0, 'voltaram' => 0];
        }

        return $semanas;
    }

    private static function contasCriadasComSinaisDeUso(Carbon $desde)
    {
        $dias = self::DIAS_DA_SEMANA;

        return DB::table('usuario')
            ->where('usuario.created_at', '>=', $desde)
            ->selectRaw('usuario.created_at')
            ->selectRaw('EXISTS (SELECT 1 FROM customers WHERE customers.id_usuario = usuario.id) AS tem_cliente')
            ->selectRaw(
                'EXISTS (SELECT 1 FROM usuario_log WHERE usuario_log.id_user = usuario.id'
                ." AND usuario_log.created_at >= DATE_ADD(usuario.created_at, INTERVAL {$dias} DAY)) AS voltou"
            )
            ->get();
    }
}
