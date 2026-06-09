<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware'=>['guest']],function(){
    Route::get('/','TransaccionController@showForm');
    Route::post('/','TransaccionController@storePublic')->name('register');
    Route::get('/url','TransaccionController@showURL');
    Route::post('/url','TransaccionController@openPublic')->name('open');
    Route::get('/login','Auth\LoginController@showLoginForm');
    Route::post('/login', 'Auth\LoginController@login')->name('login');    
});

Route::group(['middleware'=>['auth']],function(){
    
    Route::post('/logout', 'Auth\LoginController@logout')->name('logout');
    Route::get('/dashboard','DashboardController');
    //Notificaciones
    Route::post('/notification/get','NotificationController@get');
    
    Route::get('/main', function () {
        return view('contenido/contenido');
    })->name('main');    
    
    Route::group(['middleware' => ['Administrador']], function () {

        Route::get('/estado', 'EstadoController@index');
        Route::post('/estado/registrar', 'EstadoController@store');
        Route::put('/estado/actualizar', 'EstadoController@update');
        Route::put('/estado/desactivar', 'EstadoController@desactivar');
        Route::put('/estado/activar', 'EstadoController@activar');
        Route::get('/estado/selectEstado', 'EstadoController@selectEstado');
		
		Route::get('/ciudad', 'CiudadController@index');
        Route::post('/ciudad/registrar', 'CiudadController@store');
        Route::put('/ciudad/actualizar', 'CiudadController@update');
        Route::put('/ciudad/desactivar', 'CiudadController@desactivar');
        Route::put('/ciudad/activar', 'CiudadController@activar');
        Route::get('/ciudad/selectCiudad', 'CiudadController@selectCiudad');
		Route::get('/ciudad/listarCiudad', 'CiudadController@listarCiudad');

        Route::get('/cliente', 'ClienteController@index');
        Route::get('/cliente/consolidar', 'ClienteController@consolidarIndex');
        Route::post('/cliente/consolidar/combinar', 'ClienteController@consolidarCombinar');
        Route::get('/cliente/depurar', 'ClienteController@depurarIndex');
        Route::post('/cliente/depurar/eliminar', 'ClienteController@depurarEliminar');
        Route::post('/cliente/registrar', 'ClienteController@store');
        Route::put('/cliente/actualizar', 'ClienteController@update');
        Route::get('/cliente/selectCliente', 'ClienteController@selectCliente');
        Route::get('/cliente/exportar', 'ClienteController@exportar');

        Route::get('/archivo', 'ArchivoController@index');
        Route::post('/archivo/registrar', 'ArchivoController@store');
        Route::put('/archivo/eliminar', 'ArchivoController@delete');
        Route::get('/archivo/descargar', 'ArchivoController@download');

        Route::get('/transaccion', 'TransaccionController@index');
        Route::get('/transaccion/reporteTransacciones', 'TransaccionController@reporteTransacciones');
        Route::post('/transaccion/registrar', 'TransaccionController@store');
        Route::post('/transaccion/registrarDom', 'TransaccionController@storeDom');
        Route::post('/transaccion/registrarSpei', 'TransaccionController@storeSpei');
        Route::post('/transaccion/registrarLector', 'TransaccionController@storeLector');
        Route::put('/transaccion/actualizar', 'TransaccionController@update');
        Route::put('/transaccion/desactivar', 'TransaccionController@desactivar');
        Route::put('/transaccion/activar', 'TransaccionController@activar');
        Route::put('/transaccion/rechazar', 'TransaccionController@rechazar'); 
        Route::put('/transaccion/proximo-cargo', 'TransaccionController@actualizarProximoCargoDomiciliacion');
        Route::put('/transaccion/eliminar', 'TransaccionController@delete');
        Route::get('/transaccion/selectDomiciliacion', 'TransaccionController@selectDomiciliacion');
        Route::get('/domiciliacion-activa', 'TransaccionController@domiciliacionActiva');
        Route::get('/transaccion/exportar', 'TransaccionController@exportar');
        Route::get('/transaccion/exportarTransacciones', 'TransaccionController@exportarReporte');
        Route::post('/transaccion/importar/iniciar', 'TransaccionController@iniciarImportacion');
        Route::post('/transaccion/importar/procesar', 'TransaccionController@procesarImportacion');
        Route::post('/transaccion/importar/cancelar', 'TransaccionController@cancelarImportacion');
        Route::get('/transaccion/importar/estatus', 'TransaccionController@estatusImportacion');
        Route::get('/transaccion/importar/log', 'TransaccionController@descargarLogImportacion');
        

        Route::get('/respuesta', 'RespuestaController@index');
        Route::post('/respuesta/registrar', 'RespuestaController@store');
        Route::put('/respuesta/actualizar', 'RespuestaController@update');
        Route::put('/respuesta/eliminar', 'RespuestaController@delete');
        Route::get('/respuesta/exportar', 'RespuestaController@exportar');

        Route::get('/transaccionDom', 'TransaccionDomController@index');
        Route::get('/transaccionDom/reporteTransaccionesDom', 'TransaccionDomController@reporteTransaccionesDom');
        Route::post('/transaccionDom/registrar', 'TransaccionDomController@store');        
        Route::put('/transaccionDom/actualizar', 'TransaccionDomController@update');
        Route::put('/transaccionDom/eliminar', 'TransaccionDomController@delete');
        Route::get('/transaccionDom/exportar', 'TransaccionDomController@exportar');
        Route::get('/transaccionDom/exportarTransacciones', 'TransaccionDomController@exportarTransacciones');

        Route::get('/consultaspei', 'ConsultaSpeiController@index');
        Route::get('/consultaspei/exportar', 'ConsultaSpeiController@exportar');
        Route::get('/pagospei', 'PagoSpeiController@index');
        Route::get('/pagospei/exportar', 'PagoSpeiController@exportar');
        Route::get('/pagospei/exportarReporteSpei', 'PagoSpeiController@exportarReporteSpei');
        Route::get('/pagospei/reportePagoSpei', 'PagoSpeiController@reportePagoSpei');
        Route::get('/cancelaspei', 'CancelaSpeiController@index');
        Route::get('/cancelaspei/exportar', 'CancelaSpeiController@exportar');
        Route::get('/pagos-recibidos', 'PagoRecibidoController@index');
        Route::put('/pagos-recibidos/status', 'PagoRecibidoController@actualizarStatus');

        Route::get('/rol', 'RolController@index');
        Route::get('/role', 'RolController@index');
        Route::get('/rol/selectRol', 'RolController@selectRol');
        
        Route::get('/user', 'UserController@index');
        Route::post('/user/registrar', 'UserController@store');
        Route::put('/user/actualizar', 'UserController@update');
        Route::put('/user/desactivar', 'UserController@desactivar');
        Route::put('/user/activar', 'UserController@activar');
        Route::get('/user/selectUsuario', 'UserController@selectUsuario');
    });       
});

//Route::get('/home', 'HomeController@index')->name('home');
