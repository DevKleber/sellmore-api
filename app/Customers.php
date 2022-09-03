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
    public static function getAll($options=[])
    {
        $boShowProblemasCartao = $options['boShowProblemasCartao'] === 'true'? true: false;
        $boShowLigarDepois = $options['boShowLigarDepois'] === 'true'? true: false;
        $boShowNaotemInteresse = $options['boShowNaotemInteresse'] === 'true'? true: false;
        $boShowComprou = $options['boShowComprou'] === 'true'? true: false;
        $boShowAberto = $options['boShowAberto'] === 'true'? true: false;
        $order = $options['orderBy'];

        if (!self::hasAnyFilter($boShowProblemasCartao, $boShowLigarDepois, $boShowNaotemInteresse, $boShowComprou, $boShowAberto)) {
            return[];
        }

        $arPhones = self::getPhoneByUser();
        $id_usuario = auth('api')->user()->id;
        $arFather = [];

        $parentsRootQuery = self::where('id_usuario', $id_usuario)
            ->whereNull('id_parent')
            // ->where('bo_ativo', true)
            ->select('id');
        if (isset($order->column) ) {
            $parentsRootQuery->orderBy("{$order->column}", "{$order->type}");
        }else{
            $parentsRootQuery->orderBy('name');
        }

        $parentsRoot = $parentsRootQuery->get();
        foreach ($parentsRoot as $value) {
            $arFather[$value->id] = $value->id;
        }

        $parents = self::where('id_usuario', $id_usuario)
            ->groupBy('id_parent')
            ->select('id_parent')
            ->whereNotNull('id_parent')
            // ->where('bo_ativo', true)
            ->get();

        $parents->merge($parentsRoot);

        foreach ($parents as $keyParents => $value) {
            $arFather[$value->id_parent] = $value->id_parent;
        }

        $arTempFather = $arFather;
        $arFather =[];

        $arFatherQuery = self::select('id')
            ->where('id_usuario', $id_usuario)
            ->whereIn('id', array_values($arTempFather));
            // ->orderBy('name')
            // ->orderBy('id', 'desc')
        if (isset($order->column) ) {
            $arFatherQuery->orderBy("{$order->column}", "{$order->type}");
        }else{
            $arFatherQuery->orderBy('name');
        }

        $arFather = $arFatherQuery->get();


        $arCustomers = [];
        $count = 0;
        foreach ($arFather as $key => $value) {
            $customersParent = self::where('id_usuario', $id_usuario)
                ->where('id', $value->id)
                // ->where('bo_ativo', true)
                ->first();
            if (!$customersParent) {
                continue;
            }

            $queryReferidos = self::where('id_usuario', $id_usuario)
                ->where('id_parent', $value->id)
                ->where('bo_ativo', true);

            $queryReferidos->where(function ($queryReferidos) use ($boShowProblemasCartao, $boShowLigarDepois, $boShowNaotemInteresse, $boShowComprou, $boShowAberto) {
                if ($boShowProblemasCartao) {
                    $queryReferidos->orWhere('status', 'pc');
                }
                if ($boShowLigarDepois) {
                    $queryReferidos->orWhere('status', 'ld');
                }
                if ($boShowNaotemInteresse) {
                    $queryReferidos->orWhere('status', 'n');
                }
                if ($boShowComprou) {
                    $queryReferidos->orWhere('status', 'c');
                }
                if ($boShowAberto) {
                    $queryReferidos->orWhere('status', 'a');
                }
            });


            $queryReferidos->orderBy('status', 'asc')
                ->orderBy('bo_preference', 'desc')
                ->orderBy('name', 'asc');


            $referidos = $queryReferidos->get();

            $arCustomers[$key] = $customersParent;
            // $arCustomers[$key]['phones'] = \App\Phone::where('id_customers', $customersParent->id)->get();
            $arCustomers[$key]['phones'] = $arPhones[$customersParent->id]??[];
            $arCustomers[$key]['referidos'] = $referidos;
            foreach ($arCustomers[$key]['referidos'] as $keyRef => $value) {
                // $arCustomers[$key]['referidos'][$keyRef]['phones'] = \App\Phone::where('id_customers', $value->id)->get();
                $arCustomers[$key]['referidos'][$keyRef]['phones'] = $arPhones[$value->id]??[];
            }
            ++$count;
        }

        return ['arCustomers' => $arCustomers, 'statistics' => self::statistics($arCustomers)];
    }

    private static function hasAnyFilter($boShowProblemasCartao, $boShowLigarDepois, $boShowNaotemInteresse, $boShowComprou, $boShowAberto)
    {
        if (
            $boShowProblemasCartao || $boShowLigarDepois || $boShowNaotemInteresse || $boShowComprou || $boShowAberto
        ) {
            return true;
        }
        return false;
    }

    public static function getPhoneByUser()
    {
        $idUsuario = auth('api')->user()->id;

        $customersPhones = \App\Customers::join('customers_phone', 'customers_phone.id_customers', '=', 'customers.id')
                ->where('id_usuario', $idUsuario)
                ->select('customers.id_usuario', 'customers_phone.*')
                ->get();

        $arPhones = [];
        foreach ($customersPhones as $phone) {
            $arPhones[$phone->id_customers][] = $phone;
        }
        return $arPhones;
    }

    public static function statistics($arCustomers)
    {
        $id_usuario = auth('api')->user()->id;
        $statistics = [
            'a' => 0,
            'pc' => 0,
            'ld' => 0,
            'n' => 0,
            'c' => 0,
        ];
        $customers = self::where('id_usuario', $id_usuario)->get();
        foreach ($customers as $key => $value) {
            if ('c' == $value['status']) {
                ++$statistics[$value['status']];
            } else {
                if ($value['bo_ativo']) {
                    ++$statistics[$value['status']];
                }
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
