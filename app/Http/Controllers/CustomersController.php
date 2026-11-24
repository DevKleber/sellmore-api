<?php

namespace App\Http\Controllers;

use Helpers;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    private const MAXIMO_DE_NIVEIS_DA_ARVORE = 5;
    private const CLIENTE_NAO_ENCONTRADO = 'Referido não encontrado';

    public function index(Request $request)
    {
        $options['boShowProblemasCartao'] = $request->input('boShowProblemasCartao');
        $options['boShowLigarDepois'] = $request->input('boShowLigarDepois');
        $options['boShowNaotemInteresse'] = $request->input('boShowNaotemInteresse');
        $options['boShowComprou'] = $request->input('boShowComprou');
        $options['boShowAberto'] = $request->input('boShowAberto');

        $options['orderBy'] = !empty($request->input('orderBy')) ? json_decode($request->input('orderBy')): null;
        $options['page'] = $request->input('page');
        $options['perPage'] = $request->input('perPage');

        $arCustomers = \App\Customers::getAll($options);

        if (!$arCustomers) {
            return response(['response' => 'Não existe Customers'], 200);
        }

        return response($arCustomers);
    }

    public function customerSearch(Request $request)
    {
        $query = Helpers::removerCaracteresPhone($request->input('q'));
        if (empty($query)) {
            return response([]);
        }
        $id_usuario = auth('api')->user()->id;

        $customers = \App\Customers::where('id_usuario', $id_usuario)
            ->join('customers_phone', 'customers_phone.id_customers', '=', 'customers.id')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', '%'.$query.'%')
                    ->orWhere('customers_phone.phone', 'LIKE', '%'.$query.'%')
                    ->orWhere('customers.observation', 'LIKE', '%'.$query.'%')
                ;
            })
            ->orderBy('name')
            ->select('customers.*')
            ->get();

        return $this->comTelefones($customers->unique('id')->values());
    }

    public function getCustomersLd()
    {
        $arCustomers = \App\Customers::where('id_usuario', auth('api')->user()->id)
            ->where('bo_ativo', true)
            ->where('status', 'ld')
            ->orderBy('name')
            ->get()
        ;

        return response($this->comTelefones($arCustomers));
    }

    /**
     * Do cliente informado ate o primeiro lead da cadeia de indicacoes, limitado a alguns niveis.
     */
    public function getAllParents($id)
    {
        $id_usuario = auth('api')->user()->id;
        $parents = collect();
        $proximoId = $id;

        while ($proximoId && $parents->count() < self::MAXIMO_DE_NIVEIS_DA_ARVORE) {
            $cliente = \App\Customers::where('id_usuario', $id_usuario)->where('id', $proximoId)->first();
            if (!$cliente) {
                break;
            }

            $parents->push($cliente);
            $proximoId = $cliente->id_parent;
        }

        if ($parents->isEmpty()) {
            return response(['response' => 'Referido não encontrado'], 400);
        }

        return $this->comTelefones($parents);
    }

    /**
     * Acrescenta a cada cliente a lista de telefones, buscando todos em uma unica consulta.
     */
    private function comTelefones($clientes)
    {
        $telefones = \App\Phone::agrupadosPorCliente($clientes->pluck('id')->all());

        foreach ($clientes as $cliente) {
            $cliente->phones = $telefones->get($cliente->id, collect());
        }

        return $clientes;
    }

    public function importContact(Request $request, $id)
    {
        $id_usuario = auth('api')->user()->id;
        if (!\App\Customers::doUsuario($id, $id_usuario)) {
            return response(['response' => self::CLIENTE_NAO_ENCONTRADO], 400);
        }

        \DB::beginTransaction();
        $contacts = \App\Customers::importContacts($request, $id);
        $duplicado = [];
        $imported = [];
        foreach ($contacts as $key => $value) {
            $whatsapp = $value['numeros']['whatsapp'] ?? [];
            $phone = $value['numeros']['phone'] ?? [];

            try {
                \App\Customers::verifyCustomerExist($whatsapp, $id_usuario);
            } catch (\Throwable $th) {
                $duplicado[] = $value['nome'].' - '.$th->getMessage();

                continue;
            }

            try {
                \App\Customers::verifyCustomerExist($phone, $id_usuario);
            } catch (\Throwable $th) {
                $duplicado[] = $value['nome'].' - '.$th->getMessage();

                continue;
            }

            $ar['name'] = Helpers::remove_emoji($value['nome']); // removendo emoji
            $ar['address'] = null;
            $ar['status'] = 'a';
            $ar['id_usuario'] = $id_usuario;
            $ar['id_parent'] = $id;
            $ar['observation'] = '';

            $customer = \App\Customers::create($ar);
            if (!$customer) {
                \DB::rollBack();

                return  response(['response' => 'Erro ao importar contatos'], 400);
            }
            $imported[] = $customer;

            foreach ($whatsapp as $key => $value) {
                $arPhones['bo_whatsapp'] = true;
                $arPhones['id_customers'] = $id;
                $arPhones['phone'] = $value['phone'];
                $arPhones['country_code'] = $value['countryCode'];

                try {
                    \App\Customers::insertFkPhone([$arPhones], $customer);
                } catch (\Throwable $th) {
                    \DB::rollBack();

                    return  response(['response' => $th->getMessage()], 400);
                }
            }
            foreach ($phone as $key => $value) {
                $arPhones['bo_whatsapp'] = false;
                $arPhones['id_customers'] = $id;
                $arPhones['phone'] = $value['phone'];
                $arPhones['country_code'] = $value['countryCode'];

                try {
                    \App\Customers::insertFkPhone([$arPhones], $customer);
                } catch (\Throwable $th) {
                    \DB::rollBack();

                    return  response(['response' => $th->getMessage()], 400);
                }
            }
        }
        // \DB::rollBack();
        \DB::commit();

        return ['res' => $imported, 'repetidos' => $duplicado];
    }

    public function store(Request $request)
    {
        $id_usuario = auth('api')->user()->id;
        $request['id_usuario'] = $id_usuario;
        $request['status'] = ('' == $request['status'] || null == $request['status']) ? 'a' : $request['status'];

        if (!$this->quemIndicouPertenceAoUsuario($request['id_parent'], $id_usuario)) {
            return response(['response' => self::CLIENTE_NAO_ENCONTRADO], 400);
        }

        try {
            \App\Customers::verifyCustomerExist($request['telefones'], $id_usuario);
        } catch (\Throwable $th) {
            return  response(['response' => $th->getMessage()], 400);
        }

        \DB::beginTransaction();
        $customers = \App\Customers::create($request->all());
        if (!$customers) {
            return  response(['response' => 'Erro ao salvar Customers'], 400);
        }

        try {
            \App\Customers::insertFkPhone($request['telefones'], $customers);
        } catch (\Throwable $th) {
            return  response(['response' => $th->getMessage()], 400);
        }
        \DB::commit();

        return response(['response' => 'Salvo com sucesso', 'dados' => $customers]);
    }

    /**
     * Um cliente so pode ser indicado por outro cliente do mesmo vendedor.
     */
    private function quemIndicouPertenceAoUsuario($idDeQuemIndicou, $idUsuario)
    {
        if (empty($idDeQuemIndicou)) {
            return true;
        }

        return null !== \App\Customers::doUsuario($idDeQuemIndicou, $idUsuario);
    }

    public function show($id)
    {
        $customers = \App\Customers::doUsuario($id, auth('api')->user()->id);
        if (!$customers) {
            return response(['response' => 'Não existe Customers'], 400);
        }

        return response($customers);
    }

    public function changeStatus(Request $request, $id)
    {
        $customers = \App\Customers::doUsuario($id, auth('api')->user()->id);

        if (!$customers) {
            return response(['response' => 'Customers Não encontrado'], 400);
        }

        $dados = $request->only(['status']);
        $customers = Helpers::processarColunasUpdate($customers, $dados);

        if (!$customers->update()) {
            return response(['response' => 'Erro ao alterar'], 400);
        }

        return response(['response' => 'Atualizado com sucesso']);
    }

    public function update(Request $request, $id)
    {
        $id_usuario = auth('api')->user()->id;
        $customers = \App\Customers::doUsuario($id, $id_usuario);
        if (!$customers) {
            return response(['response' => self::CLIENTE_NAO_ENCONTRADO], 400);
        }

        if (!$this->quemIndicouPertenceAoUsuario($request['id_parent'], $id_usuario)) {
            return response(['response' => self::CLIENTE_NAO_ENCONTRADO], 400);
        }

        $request['status'] = ('' == $request['status'] || null == $request['status']) ? 'a' : $request['status'];
        $request['id_usuario'] = $id_usuario;

        \DB::beginTransaction();

        $phoneCustomers = \App\Phone::join('customers', 'customers.id', '=', 'customers_phone.id_customers')
            ->where('id_customers', $id)
            ->where('id_usuario', $id_usuario)
        ;

        $phoneCustomers->delete();

        try {
            \App\Customers::verifyCustomerExist($request['telefones'], $id_usuario);
        } catch (\Throwable $th) {
            \DB::rollBack();

            return  response(['response' => $th->getMessage()], 400);
        }

        $customers = Helpers::processarColunasUpdate($customers, $request->all());
        if (!$customers->update()) {
            \DB::rollBack();

            return response(['response' => 'Erro ao alterar'], 400);
        }

        try {
            \App\Customers::insertFkPhone($request['telefones'], $customers);
        } catch (\Throwable $th) {
            \DB::rollBack();

            return  response(['response' => $th->getMessage()], 400);
        }
        \DB::commit();

        return response(['response' => 'Atualizado com sucesso']);
    }

    public function destroy($id)
    {
        $id_usuario = auth('api')->user()->id;
        $customers = \App\Customers::where('id', $id)->where('id_usuario', $id_usuario)->first();

        if (!$customers) {
            return response(['response' => 'Referido não encontrado'], 400);
        }
        $customers->bo_ativo = false;
        if (!$customers->save()) {
            return response(['response' => 'Erro ao arquivar referido'], 400);
        }

        return response(['response' => 'Referido arquivado']);
    }
    public function activate($id)
    {
        $id_usuario = auth('api')->user()->id;
        $customers = \App\Customers::where('id', $id)->where('id_usuario', $id_usuario)->first();

        if (!$customers) {
            return response(['response' => 'Referido não encontrado'], 400);
        }
        $customers->bo_ativo = true;
        if (!$customers->save()) {
            return response(['response' => 'Erro ao arquivar referido'], 400);
        }

        return response(['response' => 'Referido ativado']);
    }

    public function giveOrRemovePreference(Request $request)
    {
        return \App\Customers::updatePreference($request->all(), $request['bo_preference']);
    }
}
