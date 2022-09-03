<?php

namespace App\Http\Controllers;

use App\Library\Jira;
use Illuminate\Http\Request;

class TrelloController extends Controller
{
    public function issue(Request $request)
    {
        try {
            $request['desc'] = $request['desc'].'

            Reportado por: '
            .auth('api')->user()->nome;

            Jira::newTask($request);

            return response(['response' => 'Obrigado por nos reportar!']);
        } catch (\Throwable $th) {
            return response(['response' => 'Erro ao reportar.'], 400);
        }
    }
}
