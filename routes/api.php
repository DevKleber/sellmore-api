<?php

use App\Http\Controllers\ArquivoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ResultadosController;
use App\Http\Controllers\StrategyController;
use App\Http\Controllers\TrelloController;
use Illuminate\Support\Facades\Route;

// Route::get('teste', function () {

// });

// Route::get('teste', function () {
//     return view('emails/recoverPassword', [
//         'user' => ['email' => 'meuemail@gmail.com',
//         'no_pessoa' => 'Kleber de souza',
//         'link' => 'aqui vai o link para recuperar']
//     ]);
// });


Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/recoverPassword', [AuthController::class, 'recoverPassword']);
Route::get('auth/recoverPassword', [AuthController::class, 'recoverPasswordAfterEmail']);
Route::put('auth/changePassoword', [AuthController::class, 'recoverPasswordAfterEmail']);

Route::post('user/new', [FuncionarioController::class, 'store']);

Route::post('issue', [TrelloController::class, 'issue']);

Route::get('currentVersion', function () {
    $version = '1.1.81';
    return ["version" => $version, "resume" => "Correção: Servidor offline 📱"];
});

Route::group(['middleware' => 'apiJwt'], function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::put('auth/changePassword', [AuthController::class, 'trocarSenhaLogado']);

    Route::post('file', [ArquivoController::class, 'store']);

    Route::resource('calendar', CalendarController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::get('search', [CustomersController::class, 'customerSearch']);
    Route::resource('customers', CustomersController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::put('customersActivate/{id}', [CustomersController::class, 'activate']);
    Route::get('getCustomersLd', [CustomersController::class, 'getCustomersLd']);
    Route::get('parents/customers/{id}', [CustomersController::class, 'getAllParents']);
    Route::put('status/customers/{id}', [CustomersController::class, 'changeStatus']);
    Route::post('import-contact/customers/{id}', [CustomersController::class, 'importContact']);

    Route::post('preference', [CustomersController::class, 'giveOrRemovePreference']);

    Route::get('resultados', [ResultadosController::class, 'doVendedor']);
    Route::get('metricas-de-uso', [ResultadosController::class, 'daPlataforma']);

    Route::get('strategy', [StrategyController::class, 'index']);
    Route::put('strategy', [StrategyController::class, 'update']);

    Route::resource('access_log', LogController::class)->only(['index', 'store']);
});
