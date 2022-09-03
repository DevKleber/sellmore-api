<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Helpers;


class PhoneController extends Controller
{
    
    
    public function index()
    {
        $Phone = \App\Phone::all();
        if(!$Phone){
            return response(["response"=>"Não existe Phone"],400);
        }
        return response(["dados"=>$Phone]);
    }

    
    public function store(Request $request)
    {
        
        $request['bo_ativo'] = true;
        
        $Phone = \App\Phone::create($request->all());
        if(!$Phone){
            return  response(["response"=>"Erro ao salvar Phone"],400); 
        }
        return response(["response"=>"Salvo com sucesso",'dados'=>$Phone]);
        
    }

    
    public function show($id)
    {
        $Phone =\App\Phone::find($id);
        if(!$Phone){
            return response(["response"=>"Não existe Phone"],400);
        }
        return response($Phone);
    }

    
    public function update(Request $request, $id)
    {
        $Phone =  \App\Phone::find($id);
        
        if(!$Phone){
            return response(['response'=>'Phone Não encontrado'],400);
        }
        $Phone = Helpers::processarColunasUpdate($Phone,$request->all());
        
        if(!$Phone->update()){
            return response(['response'=>'Erro ao alterar'],400);
        }
        return response(['response'=>'Atualizado com sucesso']);
      
    }
    

    public function destroy($id)
    {
        $Phone =  \App\Phone::find($id);
        
        if(!$Phone){
            return response(['response'=>'Phone Não encontrado'],400);
        }
        $Phone->bo_ativo = false;
        if(!$Phone->save()){
            return response(["response"=>"Erro ao deletar Phone"],400);
        }
        return response(['response'=>'Phone Inativado com sucesso']);
    }
}