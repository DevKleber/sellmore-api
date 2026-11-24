<?php

namespace App;

use Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Link publico pelo qual o cliente de um vendedor informa, ele mesmo, quem indica.
 */
class LinkDeIndicacao
{
    /** Cabe uma lista de contatos importada de uma vez. */
    public const MAXIMO_DE_INDICADOS_POR_ENVIO = 50;

    private const TAMANHO_DO_CODIGO = 40;
    private const MAXIMO_DE_REFERIDOS_DO_CLIENTE = 200;
    private const CODIGO_DO_BRASIL = '55';
    private const DIGITOS_DE_TELEFONE_ACEITOS = [10, 11];
    private const TAMANHO_MINIMO_DO_NOME = 2;
    private const TAMANHO_MAXIMO_DO_NOME = 80;
    private const STATUS_ABERTO = 'a';
    private const OBSERVACAO = 'Indicado pelo link de indicação';
    private const CARACTERES_DE_MARCACAO = '/[<>"\'&]/u';

    public static function codigoDoCliente(Customers $cliente)
    {
        if (empty($cliente->codigo_do_link_de_indicacao)) {
            $cliente->codigo_do_link_de_indicacao = Str::random(self::TAMANHO_DO_CODIGO);
            $cliente->save();
        }

        return $cliente->codigo_do_link_de_indicacao;
    }

    public static function endereco($codigo)
    {
        return config('wiseller.endereco_da_plataforma').'/indicar/'.$codigo;
    }

    public static function clienteDoCodigo($codigo)
    {
        if (self::TAMANHO_DO_CODIGO !== strlen((string) $codigo)) {
            return null;
        }

        return Customers::where('codigo_do_link_de_indicacao', $codigo)->where('bo_ativo', true)->first();
    }

    /**
     * Devolve so os indicados com nome e telefone validos, ja no formato de gravacao.
     */
    public static function indicadosValidos($indicados)
    {
        $validos = [];
        foreach (is_array($indicados) ? $indicados : [] as $indicado) {
            $nome = self::nomeLimpo($indicado['nome'] ?? '');
            $telefone = self::telefoneLimpo($indicado['telefone'] ?? '');
            if (null === $nome || null === $telefone) {
                continue;
            }

            $validos[$telefone] = ['nome' => $nome, 'telefone' => $telefone];
        }

        return array_values($validos);
    }

    private static function nomeLimpo($nome)
    {
        $nome = is_string($nome) ? $nome : '';
        $nome = trim(preg_replace(self::CARACTERES_DE_MARCACAO, '', Helpers::remove_emoji($nome)));
        if (mb_strlen($nome) < self::TAMANHO_MINIMO_DO_NOME) {
            return null;
        }

        return mb_substr($nome, 0, self::TAMANHO_MAXIMO_DO_NOME);
    }

    private static function telefoneLimpo($telefone)
    {
        $digitos = preg_replace('/[^0-9]/', '', is_string($telefone) ? $telefone : '');
        $maiorTamanhoSemPais = max(self::DIGITOS_DE_TELEFONE_ACEITOS);
        if (strlen($digitos) > $maiorTamanhoSemPais && str_starts_with($digitos, self::CODIGO_DO_BRASIL)) {
            $digitos = substr($digitos, strlen(self::CODIGO_DO_BRASIL));
        }

        if (!in_array(strlen($digitos), self::DIGITOS_DE_TELEFONE_ACEITOS, true)) {
            return null;
        }

        return Helpers::numeroNonoDigito($digitos);
    }

    /**
     * Grava os indicados como referidos do cliente e devolve os nomes gravados. Telefone que o vendedor ja tem
     * e ignorado em silencio, para a pagina publica nao revelar quem esta na carteira dele.
     */
    public static function gravar(Customers $cliente, array $indicados)
    {
        return DB::transaction(function () use ($cliente, $indicados) {
            $vagas = self::MAXIMO_DE_REFERIDOS_DO_CLIENTE - self::totalDeReferidos($cliente);
            $gravados = [];

            foreach ($indicados as $indicado) {
                if (count($gravados) >= $vagas) {
                    break;
                }
                if (self::vendedorJaTemOTelefone($cliente->id_usuario, $indicado['telefone'])) {
                    continue;
                }

                self::criarReferido($cliente, $indicado);
                $gravados[] = $indicado['nome'];
            }

            return $gravados;
        });
    }

    private static function totalDeReferidos(Customers $cliente)
    {
        return Customers::where('id_usuario', $cliente->id_usuario)->where('id_parent', $cliente->id)->count();
    }

    private static function vendedorJaTemOTelefone($idUsuario, $telefone)
    {
        return Customers::join('customers_phone', 'customers_phone.id_customers', '=', 'customers.id')
            ->where('customers.id_usuario', $idUsuario)
            ->where('customers_phone.phone', $telefone)
            ->exists();
    }

    private static function criarReferido(Customers $cliente, array $indicado)
    {
        $referido = new Customers();
        $referido->name = $indicado['nome'];
        $referido->status = self::STATUS_ABERTO;
        $referido->id_usuario = $cliente->id_usuario;
        $referido->id_parent = $cliente->id;
        $referido->observation = self::OBSERVACAO;
        $referido->status_alterado_em = now();
        $referido->save();

        Phone::create([
            'id_customers' => $referido->id,
            'phone' => $indicado['telefone'],
            'country_code' => self::CODIGO_DO_BRASIL,
            'bo_whatsapp' => true,
        ]);
    }
}
