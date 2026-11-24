<?php

namespace App\Http\Controllers;

use App\MetricasDeUso;
use App\Resultados;

class ResultadosController extends Controller
{
    private const ID_DO_ADMINISTRADOR = 1;

    public function doVendedor()
    {
        return response(['dados' => Resultados::doUsuario(auth('api')->user()->id)]);
    }

    public function daPlataforma()
    {
        if (self::ID_DO_ADMINISTRADOR != auth('api')->user()->id) {
            return response(['response' => 'Sem permissão'], 400);
        }

        return response(['dados' => MetricasDeUso::resumo()]);
    }
}
