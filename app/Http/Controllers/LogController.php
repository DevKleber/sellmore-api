<?php

namespace App\Http\Controllers;

use Helpers;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index()
    {
        if (1 != auth('api')->user()->id) {
            return response(['response' => 'Sem permissão'], 400);
        }

        $log = \App\Log::Join('usuario', 'usuario.id', '=', 'usuario_log.id_user')
            ->orderBy('usuario_log.id', 'desc')
            ->select('usuario_log.*', 'usuario.nome')
            ->whereRaw('usuario_log.created_at >= now() - INTERVAL 7 day')
            ->get()
        ;
        $logDay = \App\Log::Join('usuario', 'usuario.id', '=', 'usuario_log.id_user')
            ->groupBy('usuario.nome')
            ->select('usuario.nome')
            ->orderBy('usuario.nome')
            ->where('usuario_log.created_at','>=', date("Y-m-d 00:00:00"))
            ->get()
        ;

        $totalCustomerActive = \App\Customers::Join('usuario', 'usuario.id', '=', 'customers.id_usuario')
            ->selectRaw('id_usuario, nome, count(*) as total')
            ->groupBy(['id_usuario', 'nome'])
            ->where('customers.bo_ativo', true)
            ->orderByRaw('total desc ')
            ->get()
        ;
        $totalCustomerSeller = \App\Customers::Join('usuario', 'usuario.id', '=', 'customers.id_usuario')
            ->selectRaw('id_usuario, nome, count(*) as total')
            ->groupBy(['id_usuario', 'nome'])
            ->where('customers.status', 'c')
            ->orderByRaw('total desc ')
            ->get()
        ;

        $totalAccessByUser = \App\Log::Join('usuario', 'usuario.id', '=', 'usuario_log.id_user')
            ->selectRaw('id_user, nome, count(*) as total')
            ->groupBy(['id_user', 'nome'])
            ->orderByRaw('total desc ')
            ->get()
        ;

        $totalDisplay = \App\Log::selectRaw('display_resolution, count(*) as total')
            ->groupBy(['display_resolution'])
            ->orderByRaw('total desc ')
            ->get()
        ;
        $totalBrowser = \App\Log::selectRaw('browser, count(*) as total')
            ->groupBy(['browser'])
            ->orderByRaw('total desc ')
            ->get()
        ;
        $totalDevice = \App\Log::selectRaw('device, count(*) as total')
            ->groupBy(['device'])
            ->orderByRaw('total desc ')
            ->get()
        ;
        $totalDevice = \App\Log::selectRaw('device, count(*) as total')
            ->groupBy(['device'])
            ->orderByRaw('total desc ')
            ->get()
        ;
        $totalLanguage = \App\Log::selectRaw('`language`, count(*) as total')
            ->groupBy(['language'])
            ->orderByRaw('total desc ')
            ->get()
        ;

        return response([
            'dados' => $log,
            'logDay' => $logDay,
            'totalCustomerActive' => $totalCustomerActive,
            'totalCustomerSeller' => $totalCustomerSeller,
            'totalAccessByUser' => $totalAccessByUser,
            'totalDisplay' => $totalDisplay,
            'totalBrowser' => $totalBrowser,
            'totalDevice' => $totalDevice,
            'totalLanguage' => $totalLanguage,
        ]);
    }

    public function store(Request $request)
    {
        $request['ip'] = Helpers::getUserIpAddr();
        $request['id_user'] = auth('api')->user()->id;
        $request['device'] = Helpers::getOSClient();
        $request['browser'] = Helpers::get_browser_name();
        $log = \App\Log::create($request->all());
        if (!$log) {
            return  response(['response' => 'Erro ao salvar Log'], 400);
        }

        return response(['response' => 'Salvo com sucesso', 'dados' => $log]);
    }

    public function show($id)
    {
        $log = \App\Log::find($id);
        if (!$log) {
            return response(['response' => 'Não existe Log'], 400);
        }

        return response($log);
    }

    public function destroy($id)
    {
        $log = \App\Log::find($id);

        if (!$log) {
            return response(['response' => 'Log Não encontrado'], 400);
        }
        $log->bo_ativo = false;
        if (!$log->save()) {
            return response(['response' => 'Erro ao deletar Log'], 400);
        }

        return response(['response' => 'Log Inativado com sucesso']);
    }
}
