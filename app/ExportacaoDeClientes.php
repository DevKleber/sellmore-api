<?php

namespace App;

/**
 * Todos os clientes do vendedor em linhas prontas para virar planilha.
 */
class ExportacaoDeClientes
{
    public const CABECALHO = ['Nome', 'Telefones', 'Status', 'Indicado por', 'Observação', 'Arquivado', 'Cadastrado em'];

    private const SEPARADOR_DE_TELEFONES = ', ';
    private const FORMATO_DA_DATA = 'd/m/Y';
    private const CARACTERES_QUE_VIRAM_FORMULA = ['=', '+', '-', '@'];

    public static function linhasDoUsuario($idUsuario)
    {
        $clientes = Customers::where('id_usuario', $idUsuario)->orderBy('name')->orderBy('id')->get();
        $telefones = Phone::agrupadosPorCliente($clientes->pluck('id')->all());
        $nomes = $clientes->pluck('name', 'id');
        $status = Customers::getStatus();

        return $clientes->map(function ($cliente) use ($telefones, $nomes, $status) {
            return array_map([self::class, 'semFormula'], [
                $cliente->name,
                $telefones->get($cliente->id, collect())->pluck('phone')->implode(self::SEPARADOR_DE_TELEFONES),
                $status[$cliente->status] ?? '',
                $nomes->get($cliente->id_parent, ''),
                (string) $cliente->observation,
                $cliente->bo_ativo ? 'Não' : 'Sim',
                optional($cliente->created_at)->format(self::FORMATO_DA_DATA) ?? '',
            ]);
        })->all();
    }

    /**
     * Planilhas executam celulas que comecam com esses caracteres; o apostrofo faz valer como texto.
     */
    private static function semFormula($valor)
    {
        $valor = (string) $valor;
        if ('' === $valor || !in_array($valor[0], self::CARACTERES_QUE_VIRAM_FORMULA, true)) {
            return $valor;
        }

        return "'".$valor;
    }
}
