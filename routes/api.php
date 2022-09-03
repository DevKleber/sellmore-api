<?php

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


Route::post('auth/login', 'AuthController@login');
Route::post('auth/recoverPassword', 'AuthController@recoverPassword');
Route::get('auth/recoverPassword', 'AuthController@recoverPasswordAfterEmail');
Route::put('auth/changePassoword', 'AuthController@recoverPasswordAfterEmail');

Route::post('user/new', 'FuncionarioController@store');

Route::post('issue', 'TrelloController@issue');

Route::get('currentVersion', function () {
    $version = '1.1.81';
    return ["version" => $version, "resume" => "Correção: Servidor offline 📱"];
});

Route::group(['middleware' => 'apiJwt'], function () {
    Route::resource('employee', 'FuncionarioController');



    Route::post('auth/logout', 'AuthController@logout');
    Route::post('auth/refresh', 'AuthController@refresh');
    Route::get('auth/me', 'AuthController@me');
    Route::put('auth/changePassword', 'AuthController@changePassword');

    Route::post('file', 'ArquivoController@store');

    Route::resource('calendar', 'CalendarController');

    Route::get('search', 'CustomersController@customerSearch');
    Route::resource('customers', 'CustomersController');
    Route::put('customersActivate/{id}', 'CustomersController@activate');
    Route::get('getCustomersLd', 'CustomersController@getCustomersLd');
    Route::get('parents/customers/{id}', 'CustomersController@getAllParents');
    Route::put('status/customers/{id}', 'CustomersController@changeStatus');
    Route::post('import-contact/customers/{id}', 'CustomersController@importContact');

    Route::post('preference', 'CustomersController@giveOrRemovePreference');

    Route::get('strategy', 'StrategyController@index');
    Route::put('strategy', 'StrategyController@update');

    Route::resource('access_log', 'LogController');
});
