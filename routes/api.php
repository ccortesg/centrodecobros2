<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//Servicios Liga de Pago y Domiciliación
Route::post('Service/EntregarPagoLiga', 'RespuestaController@storePublic');
Route::post('Service/EntregarPagoLigaToken', 'RespuestaController@storePublic');

//Servicios registro de pago Lector
Route::post('Service/EntregarPagoLector', 'RespuestaController@storeLectorPublic');

//Servicios SPEI
Route::get('Service/ConsultaClabe', 'TransaccionController@consultaClabe');
Route::post('Service/PagoClabe', 'TransaccionController@pagoClabe');
Route::post('Service/CancelaClabe', 'TransaccionController@cancelaClabe');


//Servicios para clientes
//Registrar generación de liga de pago
Route::post('GenerarLigaPago', 'TransaccionController@storeAPI');

//Registrar generación liga para domiciliación
Route::post('GenerarLigaDomiciliacion', 'TransaccionController@storeDomAPI');

//Registrar pago de domiciliación
Route::post('CargoDomiciliacion', 'TransaccionDomController@storeAPI');

//Registrar la cancelación para la domiciliación
Route::post('CancelarDomiciliacion', 'TransaccionController@cancelarDomAPI');

//Registrar generación de clabe spei
Route::post('GenerarSpei', 'TransaccionController@storeSpeiAPI');

//Registrar generación de liga de pago para lector
Route::post('GenerarLigaLector', 'TransaccionController@storeLectorAPI');

//Cancelar liga de pago para lector

