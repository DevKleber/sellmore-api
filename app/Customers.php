<?php

namespace App;

use Helpers;
use Illuminate\Database\Eloquent\Model;

class Customers extends Model
{
    protected $table = 'customers';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'phone', 'address', 'status', 'id_usuario', 'id_parent', 'observation', 'bo_ativo', 'bo_preference'];


    public static function getStatus()
    {
        return [
            'a' => 'Aberto',
            'pc' => 'Problemas com cartão',
            'ld' => 'Ligar depois',
            'n' => 'Não tem interesse',
            'c' => 'Comprou',
        ];
    }
    private const STATUS_POR_FILTRO = [
        'boShowAberto' => 'a',
        'boShowProblemasCartao' => 'pc',
        'boShowLigarDepois' => 'ld',
        'boShowNaotemInteresse' => 'n',
        'boShowComprou' => 'c',
    ];

    private const LEADS_POR_PAGINA_PADRAO = 8;
    private const MAXIMO_DE_LEADS_POR_PAGINA = 50;

    /**
     * Leads do usuario logado, cada um com seus telefones e referidos.
     * Sem "page" devolve todos; com "page" devolve so aquela pagina e o total de leads.
     */
    public static function getAll($options = [])
    {
        $statusSelecionados = self::statusSelecionados($options);
        if (0 === count($statusSelecionados)) {
            return [];
        }

        $idUsuario = auth('api')->user()->id;
        $consultaDeLeads = self::consultaDeLeads($idUsuario, $options['orderBy'] ?? null);
        $pagina = self::paginaPedida($options);

        $resposta = [];
        if (null !== $pagina) {
            $resposta['total'] = (clone $consultaDeLeads)->count();
            $consultaDeLeads->forPage($pagina, self::leadsPorPagina($options));
        }

        $leads = $consultaDeLeads->get();
        self::anexarReferidosETelefones($leads, $idUsuario, $statusSelecionados, null !== $pagina);

        return ['arCustomers' => $leads->all(), 'statistics' => self::statistics()] + $resposta;
    }

    private static function statusSelecionados($options)
    {
        $selecionados = [];
        foreach (self::STATUS_POR_FILTRO as $filtro => $status) {
            if ('true' === ($options[$filtro] ?? null)) {
                $selecionados[] = $status;
            }
        }

        return $selecionados;
    }

    /**
     * Um cliente aparece como lead quando nao foi indicado por ninguem ou quando ja indicou alguem.
     */
    private static function consultaDeLeads($idUsuario, $ordem)
    {
        $consulta = self::where('id_usuario', $idUsuario)
            ->where(function ($leads) use ($idUsuario) {
                $leads->whereNull('id_parent')
                    ->orWhereIn('id', function ($quemIndicou) use ($idUsuario) {
                        $quemIndicou->select('id_parent')
                            ->from('customers')
                            ->where('id_usuario', $idUsuario)
                            ->whereNotNull('id_parent');
                    });
            });

        if (isset($ordem->column)) {
            return $consulta->orderBy("{$ordem->column}", "{$ordem->type}")->orderBy('id');
        }

        return $consulta->orderBy('name')->orderBy('id');
    }

    private static function paginaPedida($options)
    {
        $pagina = (int) ($options['page'] ?? 0);

        return $pagina > 0 ? $pagina : null;
    }

    private static function leadsPorPagina($options)
    {
        $pedido = (int) ($options['perPage'] ?? 0);
        if ($pedido <= 0) {
            return self::LEADS_POR_PAGINA_PADRAO;
        }

        return min($pedido, self::MAXIMO_DE_LEADS_POR_PAGINA);
    }

    private static function anexarReferidosETelefones($leads, $idUsuario, $statusSelecionados, $somenteDestaPagina)
    {
        $referidosPorLead = self::referidosPorLead($leads->pluck('id')->all(), $idUsuario, $statusSelecionados);

        $idsExibidos = $somenteDestaPagina
            ? $leads->pluck('id')->merge($referidosPorLead->flatten()->pluck('id'))->all()
            : null;
        $telefones = self::getPhoneByUser($idsExibidos);

        foreach ($leads as $lead) {
            $referidos = $referidosPorLead->get($lead->id, collect());
            foreach ($referidos as $referido) {
                $referido->phones = $telefones[$referido->id] ?? [];
            }

            $lead->phones = $telefones[$lead->id] ?? [];
            $lead->referidos = $referidos;
        }
    }

    private static function referidosPorLead($idsDosLeads, $idUsuario, $statusSelecionados)
    {
        return self::where('id_usuario', $idUsuario)
            ->whereIn('id_parent', $idsDosLeads)
            ->where('bo_ativo', true)
            ->whereIn('status', $statusSelecionados)
            ->orderBy('status', 'asc')
            ->orderBy('bo_preference', 'desc')
            ->orderBy('name', 'asc')
            ->orderBy('id')
            ->get()
            ->groupBy('id_parent');
    }

    /**
     * Telefones agrupados por cliente. Sem ids, traz os de todos os clientes do usuario logado.
     */
    public static function getPhoneByUser($idsDosClientes = null)
    {
        $idUsuario = auth('api')->user()->id;

        $consulta = \App\Customers::join('customers_phone', 'customers_phone.id_customers', '=', 'customers.id')
            ->where('id_usuario', $idUsuario)
            ->select('customers.id_usuario', 'customers_phone.*')
            ->orderBy('customers_phone.id');

        if (null !== $idsDosClientes) {
            $consulta->whereIn('customers.id', $idsDosClientes);
        }

        $arPhones = [];
        foreach ($consulta->get() as $phone) {
            $arPhones[$phone->id_customers][] = $phone;
        }

        return $arPhones;
    }

    /**
     * Quantos clientes ha em cada status. Vendas contam mesmo arquivadas; os demais, so os ativos.
     */
    public static function statistics()
    {
        $statistics = [
            'a' => 0,
            'pc' => 0,
            'ld' => 0,
            'n' => 0,
            'c' => 0,
        ];

        $contagens = self::where('id_usuario', auth('api')->user()->id)
            ->selectRaw('status, bo_ativo, count(*) as total')
            ->groupBy('status', 'bo_ativo')
            ->get();

        foreach ($contagens as $contagem) {
            $conta = 'c' == $contagem->status || $contagem->bo_ativo;
            if ($conta && array_key_exists($contagem->status, $statistics)) {
                $statistics[$contagem->status] += (int) $contagem->total;
            }
        }

        return $statistics;
    }

    public static function importContacts($request, $id)
    {
        if ($request->hasFile('imagem')) {
            $array_texto = file($request->file('imagem')->getRealPath(), FILE_IGNORE_NEW_LINES);

            $i = 0;
            $ar = [];
            foreach ($array_texto as $line_num => $line) {
                $ar[$i][] = $line;

                if (preg_match('/END:/', $line)) {
                    ++$i;
                }
            }
            $contatos = [];
            foreach ($ar as $key => $value) {
                if (count($value) <=2) {
                    continue;
                }

                // $contatos[$key] = [
                //     'nome' => explode('FN:', $value[3])[1],
                // ];

                $countPhone = 0;
                foreach ($value as $item) {
                    $fn = explode('FN:', $item);
                    if (count($fn) > 1) {
                        $contatos[$key] = [ 'nome' => $fn[1]];
                    }

                    $numeros = explode('TEL', $item);

                    if (count($numeros) > 1) {
                        $numeroWaid = explode('waid=', $numeros[1]);

                        $explodByAddition = explode('+', $numeros[1])[1] ?? ' ';
                        $countryCode = explode(' ', $explodByAddition)[0] ?? '';

                        if (count($numeroWaid) > 1) {
                            $numeroWhatsapp = explode(':', $numeroWaid[1])[0];
                            $numero = preg_replace('/[^0-9]/', '', $numeroWhatsapp);

                            $numeroWithoutCountryCode = substr($numero, strlen($countryCode));

                            if ($numeroWithoutCountryCode == '') {
                                continue;
                            }

                            $contatos[$key]['numeros']['whatsapp'][$countPhone]['phone'] = $numeroWithoutCountryCode;
                            if ('55' == $countryCode) {
                                $contatos[$key]['numeros']['whatsapp'][$countPhone]['phone'] = Helpers::numeroNonoDigito($numeroWithoutCountryCode);
                            }
                            $contatos[$key]['numeros']['whatsapp'][$countPhone]['countryCode'] = $countryCode;
                        } else {
                            $numeroNormal = explode('TEL:', $numeros[1])[0];
                            $numero = preg_replace('/[^0-9]/', '', $numeroNormal);
                            $numeroWithoutCountryCode = substr($numero, strlen($countryCode));

                            if ($numeroWithoutCountryCode == '') {
                                continue;
                            }
                            $contatos[$key]['numeros']['phone'][$countPhone]['phone'] = $numeroWithoutCountryCode;
                            if ('55' == $countryCode) {
                                if (strlen($numeroWithoutCountryCode) > 11) { //Se numero tem 13 digitos 64 9 99967545
                                    $contatos[$key]['numeros']['phone'][$countPhone]['countryCode'] = $countryCode;
                                    continue;
                                }
                                $contatos[$key]['numeros']['phone'][$countPhone]['phone'] = Helpers::numeroNonoDigito($numeroWithoutCountryCode);
                            }
                            $contatos[$key]['numeros']['phone'][$countPhone]['countryCode'] = $countryCode;
                        }
                        ++$countPhone;
                    }
                }
            }

            return $contatos;
        }
    }

    public static function verifyCustomerExist($telefones, $id_usuario)
    {
        foreach ($telefones as $value) {
            if (!$value['phone']) {
                continue;
            }
            $customers = \App\Customers::join('customers_phone', 'customers_phone.id_customers', '=', 'customers.id')
                ->where('customers_phone.phone', $value['phone'] ?? $value)
                ->where('id_usuario', $id_usuario)
                ->get();
            if ($customers->count()) {
                $customersArray = $customers->toArray();

                $indicadoPor = \App\Customers::where('id', $customersArray[0]['id_parent'])->first();
                if (!$indicadoPor) { // indico por um lead que não tem lead.
                    throw new \Exception("Número de telefone já existe ({$customersArray[0]['name']})", 1);
                    // return  response(['response' => "Número de telefone já existe ({$customersArray[0]['name']})"], 400);
                }

                throw new \Exception('Referido já indicado pelo(a) ' . $indicadoPor->name, 1);
                // return  response( ['response' => 'Referido já indicado pelo(a) '.$indicadoPor->name], 400 );
            }

            return true;
        }
    }

    public static function insertFkPhone($telefones, $customers)
    {
        foreach ($telefones as $value) {
            $value['id_customers'] = $customers->id;
            $customersPhone = \App\Phone::create($value);
            if (!$customersPhone) {
                \DB::rollBack();

                throw new \Exception('Erro ao inserir telefone', 1);
            }
        }
    }

    public static function updatePreference($customer, $bo_true = true)
    {
        $id_usuario = auth('api')->user()->id;
        $customers = \App\Customers::where('id', $customer['id'])->where('id_usuario', $id_usuario)->first();

        if (!$customers) {
            return response(['response' => 'Referido não encontrado'], 400);
        }
        $customers->bo_preference = $bo_true;
        if (!$customers->save()) {
            return response(['response' => 'Erro ao arquivar referido'], 400);
        }

        return response(['response' => 'Referido arquivado']);
    }
}
