<?php

namespace App\Http\Controllers;

use App\Funcionario;
use App\LembreteDeLigacoes;

class LembreteController extends Controller
{
    /**
     * Link do rodape do e-mail: desliga o lembrete sem exigir login.
     */
    public function cancelar($idUsuario, $assinatura)
    {
        $usuario = Funcionario::find($idUsuario);
        $assinaturaEsperada = LembreteDeLigacoes::assinaturaDoCancelamento($idUsuario, $usuario->email ?? '');

        if (!$usuario || !hash_equals($assinaturaEsperada, (string) $assinatura)) {
            return response()->view('lembreteCancelado', ['cancelado' => false], 400);
        }

        $usuario->bo_lembrete_email = false;
        $usuario->save();

        return response()->view('lembreteCancelado', ['cancelado' => true]);
    }
}
