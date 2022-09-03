<?php

namespace App\Http\Controllers;

use Helpers;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index()
    {
        $calendar = \App\Calendar::join('customers', 'customers.id', '=', 'calendar.id_customers')
            ->where('calendar.id_usuario', auth('api')->user()->id)
            ->where('calendar.bo_ativo', true)
            ->select('calendar.*','calendar.id as id_calendar', 'customers.*', 'customers.id as id_customer')
            ->get()
        ;
        if (!$calendar) {
            return response(['response' => 'Não existe Calendar'], 400);
        }
        $ar = [];
        foreach ($calendar as $key => $value) {
            $end_date = date('Y/m/d H:i:s', strtotime("{$value->date} +30 minute"));
            if($value->date_end !== null){
                $end_date = $value->date_end;
            }
            $hour = date('H:i:s', strtotime("{$value->date}"));
            $cutomersPhone = \App\Phone::where('id_customers', $value->id_customer)->select('phone')->get();
            $arNumbers = [];
            foreach ($cutomersPhone as $keyPhone => $valuePhone) {
                $arNumbers[] = $valuePhone->phone;
            }
            $numbersPhone = implode(',', $arNumbers);

            $startDate = str_replace('-', '/', $value->date);

            $ar[$key]['id'] = $value->id_calendar;
            $ar[$key]['start'] = $startDate;
            $ar[$key]['end'] = $end_date;
            $ar[$key]['title'] = "<small class='displayNone'>{$value->id_customers}:|:;</small> {$value->name} {$numbersPhone} <br />Ligar às {$hour}";
            $ar[$key]['phones'] = $numbersPhone;
            $ar[$key]['color'] = '#fff';
            $ar[$key]['allDay'] = false;
        }

        return response(['dados' => $ar]);
    }

    public function store(Request $request)
    {
        $request['bo_ativo'] = true;
        $request['id_usuario'] = auth('api')->user()->id;
        $date = Helpers::convertDateWithoutSeparatorToDatabase($request['date']);
        $hour = Helpers::convertHourWithoutSeparatorToDatabase($request['hour']);
        $request['date'] = "{$date} {$hour}";

        $calendar = \App\Calendar::create($request->all());
        if (!$calendar) {
            return  response(['response' => 'Erro ao salvar Calendar'], 400);
        }

        $end_date = date('Y-m-d H:i:s', strtotime("{$calendar->date} +30 minute"));
        if($calendar->date_end !== null){
            $end_date = $calendar->date_end;
        }
        $hour = date('H:i:s', strtotime("{$calendar->date}"));
        $cutomers = \App\Customers::find($request['id_customers']);
        $cutomersPhone = \App\Phone::where('id_customers', $cutomers->id)->select('phone')->get();
        $arNumbers = [];
        foreach ($cutomersPhone as $key => $value) {
            $arNumbers[] = $value->phone;
        }
        $numbersPhone = implode(',', $arNumbers);

        $ar['id'] = $calendar->id;
        $ar['start'] = $calendar->date;
        $ar['end'] = $end_date;
        $ar['title'] = "<small class='displayNone'>{$cutomers->id}:|:;</small>  {$cutomers->name} {$numbersPhone}  Ligar às {$hour}";
        $ar['phones'] = $arNumbers;
        $ar['color'] = '#fff';
        $ar['allDay'] = false;

        return response(['response' => 'Salvo com sucesso', 'dados' => $ar]);
    }

    public function show($id)
    {
        $calendar = \App\Calendar::find($id);
        if (!$calendar) {
            return response(['response' => 'Não existe Calendar'], 400);
        }

        return response($calendar);
    }

    public function update(Request $request, $id)
    {
        $calendar = \App\Calendar::find($id);

        $newStart = date("Y-m-d H:i:s", strtotime($request->newStart));
        $newEnd = date("Y-m-d H:i:s", strtotime($request->newEnd));

        if (!$calendar) {
            return response(['response' => 'Calendar Não encontrado'], 400);
        }

        $calendar->date = $newStart;
        $calendar->date_end = $newEnd;

        if (!$calendar->update()) {
            return response(['response' => 'Erro ao alterar'], 400);
        }

        return response(['response' => 'Atualizado com sucesso']);
    }

    public function destroy($id)
    {
        $ususario = $request['id_usuario'] = auth('api')->user()->id;
        $calendar = \App\Calendar::find($id);

        if($calendar->id_usuario !== $ususario) {
            return response(['response' => 'Sem permissão para deletar esse contato.'], 400);
        }


        if (!$calendar) {
            return response(['response' => 'Calendar Não encontrado'], 400);
        }
        $calendar->bo_ativo = false;
        if (!$calendar->delete()) {
            return response(['response' => 'Erro ao deletar Calendar'], 400);
        }

        return response(['response' => 'Deletado com sucesso!']);
    }
}
