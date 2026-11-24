<?php

namespace App\Http\Controllers;

use App\Customers;
use App\Funcionario;
use App\LinkDeIndicacao;
use Illuminate\Http\Request;

class IndicacaoController extends Controller
{
    private const LINK_INVALIDO = 'Este link de indicação não está mais disponível';

    public function gerarLink($id)
    {
        $cliente = Customers::doUsuario($id, auth('api')->user()->id);
        if (!$cliente) {
            return response(['response' => 'Referido não encontrado'], 400);
        }

        $codigo = LinkDeIndicacao::codigoDoCliente($cliente);

        return response(['dados' => ['codigo' => $codigo, 'link' => LinkDeIndicacao::endereco($codigo)]]);
    }

    /**
     * Pagina publica: mostra so o primeiro nome do vendedor e de quem esta indicando.
     */
    public function apresentar($codigo)
    {
        $cliente = LinkDeIndicacao::clienteDoCodigo($codigo);
        if (!$cliente) {
            return response(['response' => self::LINK_INVALIDO], 400);
        }

        $vendedor = Funcionario::find($cliente->id_usuario);

        return response(['dados' => [
            'vendedor' => $this->primeiroNome($vendedor->nome ?? ''),
            'cliente' => $this->primeiroNome($cliente->name),
            'maximoPorEnvio' => LinkDeIndicacao::MAXIMO_DE_INDICADOS_POR_ENVIO,
        ]]);
    }

    public function receber(Request $request, $codigo)
    {
        $cliente = LinkDeIndicacao::clienteDoCodigo($codigo);
        if (!$cliente) {
            return response(['response' => self::LINK_INVALIDO], 400);
        }

        $indicados = LinkDeIndicacao::indicadosValidos($request['indicados']);
        if (0 === count($indicados)) {
            return response(['response' => 'Informe o nome e o telefone com DDD de pelo menos uma pessoa'], 400);
        }
        if (count($indicados) > LinkDeIndicacao::MAXIMO_DE_INDICADOS_POR_ENVIO) {
            return response(['response' => 'Envie no máximo '.LinkDeIndicacao::MAXIMO_DE_INDICADOS_POR_ENVIO.' pessoas por vez'], 400);
        }

        LinkDeIndicacao::gravar($cliente, $indicados);

        return response(['response' => 'Indicações recebidas. Obrigado!', 'dados' => ['recebidos' => count($indicados)]]);
    }

    private function primeiroNome($nome)
    {
        return explode(' ', trim((string) $nome))[0];
    }
}
