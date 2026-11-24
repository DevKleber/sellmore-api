<?php

namespace App\Http\Controllers;

use App\ExportacaoDeClientes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ContaController extends Controller
{
    private const ID_DO_ADMINISTRADOR = 1;
    private const NOME_DO_ARQUIVO = 'clientes-wiseller.csv';
    private const SEPARADOR_DE_COLUNAS = ';';
    private const MARCA_DE_UTF8_PARA_PLANILHAS = "\xEF\xBB\xBF";

    public function exportarClientes()
    {
        $linhas = ExportacaoDeClientes::linhasDoUsuario(auth('api')->user()->id);

        return response()->streamDownload(function () use ($linhas) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, self::MARCA_DE_UTF8_PARA_PLANILHAS);
            foreach (array_merge([ExportacaoDeClientes::CABECALHO], $linhas) as $linha) {
                fputcsv($saida, $linha, self::SEPARADOR_DE_COLUNAS, '"', '');
            }
            fclose($saida);
        }, self::NOME_DO_ARQUIVO, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function alterarLembrete(Request $request)
    {
        $usuario = auth('api')->user();
        $usuario->bo_lembrete_email = (bool) $request['bo_lembrete_email'];
        $usuario->save();

        return response(['response' => 'Preferência salva', 'dados' => ['bo_lembrete_email' => $usuario->bo_lembrete_email]]);
    }

    /**
     * Apaga a conta e tudo o que pertence a ela. Nao tem volta, por isso exige a senha.
     */
    public function excluir(Request $request)
    {
        $usuario = auth('api')->user();
        if (!Hash::check((string) $request['password'], $usuario->password)) {
            return response(['response' => 'A senha não confere'], 400);
        }

        if (self::ID_DO_ADMINISTRADOR == $usuario->id) {
            return response(['response' => 'A conta de administrador não pode ser excluída'], 400);
        }

        DB::transaction(function () use ($usuario) {
            $idsDosClientes = DB::table('customers')->where('id_usuario', $usuario->id)->pluck('id');

            DB::table('calendar')->where('id_usuario', $usuario->id)->delete();
            DB::table('customers_phone')->whereIn('id_customers', $idsDosClientes)->delete();
            DB::table('customers')->where('id_usuario', $usuario->id)->delete();
            DB::table('strategy')->where('id_usuario', $usuario->id)->delete();
            DB::table('usuario_log')->where('id_user', $usuario->id)->delete();
            DB::table('usuario')->where('id', $usuario->id)->delete();
        });

        return response(['response' => 'Conta excluída']);
    }
}
