<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

use App\User;
use App\Transaccion;
use App\Respuesta;

use App\Exports\RespuestaExport;
use Excel;
use Exception;

class RespuestaController extends Controller
{
    private function webhookCamposMinimos()
    {
        return [
            'reference',
            'response',
            'amount',
        ];
    }

    private function leerPayloadWebhook(Request $request, array $camposRequeridos)
    {
        $raw = (string) $request->getContent();
        $payload = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            return [null, $raw, 'JSON invalido.'];
        }

        foreach ($camposRequeridos as $campo) {
            if (!array_key_exists($campo, $payload) || $payload[$campo] === null || $payload[$campo] === '') {
                return [null, $raw, 'Campo requerido faltante: ' . $campo . '.'];
            }
        }

        if (array_key_exists('amount', $payload) && $payload['amount'] !== '' && !is_numeric($payload['amount'])) {
            return [null, $raw, 'Campo amount invalido.'];
        }

        return [$payload, $raw, null];
    }

    private function valorWebhook(array $payload, $campo, $default = '')
    {
        return array_key_exists($campo, $payload) ? $payload[$campo] : $default;
    }

    private function respuestaWebhookInvalido($mensaje, $status = 422)
    {
        Log::warning('Webhook Pagadetodo rechazado: ' . $mensaje);

        return response('error', $status);
    }

    private function transaccionPorRespuestaReferencia($reference)
    {
        return Transaccion::where('responseReference', 'LIKE', $reference)->first();
    }

    private function respuestaWebhookDuplicada($idtransaccion, $reference)
    {
        return Respuesta::where('idtransaccion', '=', $idtransaccion)
            ->where('reference', '=', $reference)
            ->lockForUpdate()
            ->first();
    }

    private function criteriosRespuestaPermitidos()
    {
        return [
            'cliente_nombre',
            'ClientReference',
            'Reference',
            'responseReference',
            'reference',
            'status',
            'foliocpagos',
            'auth',
            'email',
            'payment_type',
        ];
    }

    private function respuestaPerteneceUsuario(Respuesta $respuesta)
    {
        if ($this->usuarioEsAdministrador()) {
            return true;
        }

        $transaccion = Transaccion::find($respuesta->idtransaccion);

        return $this->usuarioPuedeOperarRegistro($transaccion);
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');               
        
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $offset = $this->offsetPaginacion($request->offset);
        $tipo = $request->tipo;

        $query = Respuesta::leftjoin('transacciones', 'transacciones.id','respuestas.idtransaccion')
        ->leftjoin('clientes', 'clientes.id','transacciones.idcliente')
        ->select('respuestas.id','respuestas.idtransaccion','respuestas.fecha','respuestas.reference','respuestas.status','respuestas.foliocpagos',
        'respuestas.auth','respuestas.cd_response','respuestas.cd_error','respuestas.nb_error','respuestas.time','respuestas.date','respuestas.nb_company',
        'respuestas.nb_merchant','respuestas.cc_type','respuestas.tp_operation','respuestas.cc_name','respuestas.cc_number','respuestas.cc_expmonth',
        'respuestas.cc_expyear','respuestas.amount','respuestas.id_url','respuestas.email','respuestas.payment_type','respuestas.promocion',
        'respuestas.number_tkn','respuestas.cc_mask', 'clientes.razon_social as nombre_cliente', 'transacciones.ClientReference as cliente_reference',
        'transacciones.Reference as transaccion_reference');

        $query->where('transacciones.tipo', '=', $tipo);
        
        $this->aplicarScopePropietario($query, 'transacciones');
        
        if ($buscar!=''){
            if (!$this->criterioPermitido($criterio, $this->criteriosRespuestaPermitidos())) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Criterio de búsqueda no permitido.',
                ], 422);
            }

            if($criterio=='cliente_nombre'){
                $query->where('clientes.razon_social', 'like', '%'. $buscar . '%');
            }
            else if($criterio=='ClientReference' || $criterio=='Reference' || $criterio=='responseReference') {
                $query->where('transacciones.'.$criterio, 'like', '%'. $buscar . '%');
            } else {
                $query->where('respuestas.'.$criterio, 'like', '%'. $buscar . '%');
            }
            
        }

        $respuestas = $query->orderBy('respuestas.id', 'desc')->paginate($offset);

        return [       
            'pagination' => [
                'total'        => $respuestas->total(),
                'current_page' => $respuestas->currentPage(),
                'per_page'     => $respuestas->perPage(),
                'last_page'    => $respuestas->lastPage(),
                'from'         => $respuestas->firstItem(),
                'to'           => $respuestas->lastItem(),
            ],    
            'respuestas' => $respuestas
        ];
    }    

    public function store(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $respuesta = new Respuesta();

        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $respuesta->fecha = $mytime->toDateTimeString();
            $respuesta->reference = $request->reference;
            $respuesta->status = $request->status;
            $respuesta->foliocpagos = $request->foliocpagos;
            $respuesta->auth = $request->auth;
            $respuesta->cd_response = $request->cd_response;
            $respuesta->cd_error = $request->cd_error;
            $respuesta->nb_error = $request->nb_error;
            $respuesta->time = $request->time;
            $respuesta->date = $request->date;
            $respuesta->nb_company = $request->nb_company;
            $respuesta->nb_merchant = $request->nb_merchant;
            $respuesta->cc_type = $request->cc_type;
            $respuesta->tp_operation = $request->tp_operation;
            $respuesta->cc_name = $request->cc_name;
            $respuesta->cc_number = $request->cc_number;
            $respuesta->cc_expmonth = $request->cc_expmonth;
            $respuesta->cc_expyear = $request->cc_expyear;
            $respuesta->amount = $request->amount;
            $respuesta->id_url = $request->id_url;
            $respuesta->email = $request->email;
            $respuesta->payment_type = $request->payment_type;
            $respuesta->promocion = $request->promocion;
            $respuesta->number_tkn = $request->number_tkn;
            $respuesta->cc_mask = $request->cc_mask;
            $respuesta->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
        }              
    }

    public function storePublic(Request $request)
    {
        [$date_response, $data, $error] = $this->leerPayloadWebhook($request, $this->webhookCamposMinimos());
        if ($error !== null) {
            return $this->respuestaWebhookInvalido($error);
        }

        $idtransaccion = 0;
        $transaccion = $this->transaccionPorRespuestaReferencia($date_response["reference"]);

        if ($transaccion !== null) {
            $idtransaccion = $transaccion->id;
        }

        try{
            DB::beginTransaction();
            $duplicada = $this->respuestaWebhookDuplicada($idtransaccion, $date_response["reference"]);
            if ($duplicada !== null) {
                DB::commit();
                return 'success';
            }

            $respuesta = new Respuesta();
            $mytime= Carbon::now('America/Hermosillo');
            $respuesta->idtransaccion = $idtransaccion;
            $respuesta->fecha = $mytime->toDateTimeString();
            $respuesta->reference = $date_response["reference"];
            $respuesta->status = $date_response["response"];
            $respuesta->foliocpagos = $this->valorWebhook($date_response, 'foliocpagos');
            $respuesta->auth = $this->valorWebhook($date_response, 'auth');
            $respuesta->cd_response = $this->valorWebhook($date_response, 'cd_response');
            $respuesta->cd_error = $this->valorWebhook($date_response, 'cd_error');
            $respuesta->nb_error = $this->valorWebhook($date_response, 'nb_error');
            $respuesta->time = $this->valorWebhook($date_response, 'time');
            $respuesta->date = $this->valorWebhook($date_response, 'date', null);
            $respuesta->nb_company = $this->valorWebhook($date_response, 'nb_company');
            $respuesta->nb_merchant = $this->valorWebhook($date_response, 'nb_merchant');
            $respuesta->cc_type = $this->valorWebhook($date_response, 'cc_type');
            $respuesta->tp_operation = $this->valorWebhook($date_response, 'tp_operation');
            $respuesta->cc_name = $this->valorWebhook($date_response, 'cc_name');
            $respuesta->cc_number = $this->valorWebhook($date_response, 'cc_number');
            $respuesta->cc_expmonth = $this->valorWebhook($date_response, 'cc_expmonth');
            $respuesta->cc_expyear = $this->valorWebhook($date_response, 'cc_expyear');
            $respuesta->amount = $date_response["amount"];
            $respuesta->id_url = $this->valorWebhook($date_response, 'id_url');
            $respuesta->email = $this->valorWebhook($date_response, 'email');
            $respuesta->payment_type = $this->valorWebhook($date_response, 'payment_type');
            $respuesta->promocion = $this->valorWebhook($date_response, 'promocion');
            $respuesta->number_tkn = $this->valorWebhook($date_response, 'number_tkn');
            $respuesta->cc_mask = $this->valorWebhook($date_response, 'cc_mask');
            $respuesta->response = $data;
            $respuesta->save();            
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            Log::error('Error al guardar webhook Pagadetodo liga: ' . $e->getMessage());
            return $this->respuestaWebhookInvalido('No se pudo guardar el webhook.', 500);
        }

        //Se envía la respuesta del cargo si fue aprobado
        if ($transaccion !== null && $date_response["response"] == 'approved') {
            $usuario = User::find($transaccion->idusuario);
            if($usuario !== null && $usuario->notificaPago){
                 // Set request params
                $params = array(
                    "folio" => $transaccion->ClientReference,
                    "monto" => ((float) $date_response["amount"]),
                    "reference" => $date_response["reference"],
                    "foliocpagos" =>  $this->valorWebhook($date_response, 'foliocpagos'),
                    "auth" => $this->valorWebhook($date_response, 'auth'),
                    "cc_type" => $this->valorWebhook($date_response, 'cc_type'),
                    "cc_name" => $this->valorWebhook($date_response, 'cc_name'),
                    "cc_number" => $this->valorWebhook($date_response, 'cc_number'),
                    "cc_expmonth" => $this->valorWebhook($date_response, 'cc_expmonth'),
                    "cc_expyear" => $this->valorWebhook($date_response, 'cc_expyear'),
                    "amount" => $date_response["amount"],
                    "id_url" => $this->valorWebhook($date_response, 'id_url'),
                    "email" => $this->valorWebhook($date_response, 'email'),
                    "payment_type" => $this->valorWebhook($date_response, 'payment_type')
                );

                $response = "";
                $response_body = "";
                $response_decode = "";
                $response_code = "";
                $response_msg = "";
                $error_code = "";
                $error_msg = "";

                try{
                    $client = new Client();
                    $response =  $client->request('POST', $usuario->ligaPago, [RequestOptions::JSON => $params]);
                } catch (RequestException $e){
                    $response_decode = "error";
                    // Log the error message
                    Log::info('Falló el envío de la respuesta de la transacción '. $idtransaccion);
                    // Imprime en el log la excepción
                    Log::info(Psr7\Message::toString($e->getRequest()));                    
                }

                if($response_decode == "") {
                    try {
                        $response_body = (string) $response->getBody();
                        $response_decode = json_decode($response_body);
                        $response_code = $response_decode->code;
                        $response_msg = $response_decode->message;
                    }
                    catch (Exception $e){
                        Log::info('Error al decodificar la respuesta de la transacción '. $idtransaccion);
                    }                    
                }

                if($response_code == "success"){
                    $respuesta->enviada = 1;
                    $respuesta->save();
                }
            }            
        }

        //Agregar el envío del cargo cuando se haya rechazado falta la variable para validar que si se desea enviar

        if($respuesta->id){
            return 'success';            
         }else{
            return 'error';
         }    
    }

    public function storeLectorPublic(Request $request)
    {
        [$date_response, $data, $error] = $this->leerPayloadWebhook($request, $this->webhookCamposMinimos());
        if ($error !== null) {
            return $this->respuestaWebhookInvalido($error);
        }

        $idtransaccion = 0;
        $transaccion = $this->transaccionPorRespuestaReferencia($date_response["reference"]);

        if ($transaccion !== null) {
            $idtransaccion = $transaccion->id;
        }

        try{
            DB::beginTransaction();
            $duplicada = $this->respuestaWebhookDuplicada($idtransaccion, $date_response["reference"]);
            if ($duplicada !== null) {
                DB::commit();
                return 'success';
            }

            $respuesta = new Respuesta();
            $mytime= Carbon::now('America/Hermosillo');
            $respuesta->idtransaccion = $idtransaccion;
            $respuesta->fecha = $mytime->toDateTimeString();
            $respuesta->reference = $date_response["reference"];
            $respuesta->status = $date_response["response"];
            $respuesta->foliocpagos = $this->valorWebhook($date_response, 'folio');
            $respuesta->auth = $this->valorWebhook($date_response, 'auth');
            $respuesta->cd_response = $this->valorWebhook($date_response, 'responseCode');
            $respuesta->cd_error = $this->valorWebhook($date_response, 'errorCode');
            $respuesta->nb_error = $this->valorWebhook($date_response, 'errorDescription');
            $respuesta->time = $this->valorWebhook($date_response, 'time');
            $respuesta->date = $this->valorWebhook($date_response, 'date', null);
            $respuesta->nb_company = $this->valorWebhook($date_response, 'company');
            $respuesta->nb_merchant = $this->valorWebhook($date_response, 'merchantName');
            $respuesta->cc_type = $this->valorWebhook($date_response, 'ccType');
            $respuesta->tp_operation = $this->valorWebhook($date_response, 'operationType');
            $respuesta->cc_name = $this->valorWebhook($date_response, 'ccName');
            $respuesta->cc_number = $this->valorWebhook($date_response, 'ccNumber');
            $respuesta->cc_expmonth = $this->valorWebhook($date_response, 'ccExpMonth');
            $respuesta->cc_expyear = $this->valorWebhook($date_response, 'ccExpYear');
            $respuesta->amount = $date_response["amount"];
            $respuesta->response = $data;
            $respuesta->save();            
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            Log::error('Error al guardar webhook Pagadetodo lector: ' . $e->getMessage());
            return $this->respuestaWebhookInvalido('No se pudo guardar el webhook.', 500);
        }

        if ($transaccion !== null && $date_response["response"] == 'approved') {
            $usuario = User::find($transaccion->idusuario);
            if($usuario !== null && $usuario->notificaPago){
                 // Set request params
                $params = array(
                    "folio" => $transaccion->ClientReference,
                    "monto" => ((float) $date_response["amount"]),
                    "reference" => $date_response["reference"],
                    "foliocpagos" =>  $this->valorWebhook($date_response, 'folio'),
                    "auth" => $this->valorWebhook($date_response, 'auth'),
                    "cc_type" => $this->valorWebhook($date_response, 'ccType'),
                    "cc_name" => $this->valorWebhook($date_response, 'ccName'),
                    "cc_number" => $this->valorWebhook($date_response, 'ccNumber'),
                    "cc_expmonth" => $this->valorWebhook($date_response, 'ccExpMonth'),
                    "cc_expyear" => $this->valorWebhook($date_response, 'ccExpYear'),
                    "amount" => $date_response["amount"]                                        
                );

                $response = "";
                $response_body = "";
                $response_decode = "";
                $response_code = "";
                $response_msg = "";
                $error_code = "";
                $error_msg = "";

                try{
                    $client = new Client();
                    $response =  $client->request('POST', $usuario->ligaPago, [RequestOptions::JSON => $params]);
                } catch (RequestException $e){
                    $response  = $e->getResponse();
                    $response_body = $response !== null ? (string) $response->getBody() : '';
                    Log::info('Falló el envío de la respuesta de la transacción '. $idtransaccion);
                }

                if($response_decode == "" && $response !== "") {
                    $response_body = (string) $response->getBody();
                    $response_decode = json_decode($response_body);
                    $response_code = $response_decode->code ?? '';
                    $response_msg = $response_decode->message ?? '';
                }

                if($response_code == "success"){
                    $respuesta->enviada = 1;
                    $respuesta->save();
                }
            }
        }

        if($respuesta->id){
            return 'success';            
         }else{
            return 'error';
         }    
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        try{
            DB::beginTransaction();
            $respuesta = Respuesta::findOrFail($request->id);
            if (!$this->respuestaPerteneceUsuario($respuesta)) {
                DB::rollBack();
                return $this->respuestaNoAutorizado($request);
            }
            $respuesta->reference = $request->reference;
            $respuesta->status = $request->status;
            $respuesta->foliocpagos = $request->foliocpagos;
            $respuesta->auth = $request->auth;                
            $respuesta->cd_response = $request->cd_response;
            $respuesta->cd_error = $request->cd_error;
            $respuesta->nb_error = $request->nb_error;
            $respuesta->time = $request->time;                
            $respuesta->date = $request->date;                
            $respuesta->nb_company = $request->nb_company;
            $respuesta->nb_merchant = $request->nb_merchant;                
            $respuesta->cc_type = $request->cc_type;
            $respuesta->tp_operation = $request->tp_operation;
            $respuesta->cc_name = $request->cc_name;
            $respuesta->cc_number = $request->cc_number;
            $respuesta->cc_expmonth = $request->cc_expmonth;
            $respuesta->cc_expyear = $request->cc_expyear;
            $respuesta->amount = $request->amount;
            $respuesta->id_url = $request->id_url;
            $respuesta->email = $request->email;
            $respuesta->payment_type = $request->payment_type;
            $respuesta->promocion = $request->promocion;
            $respuesta->number_tkn = $request->number_tkn;
            $respuesta->cc_mask = $request->cc_mask;       
            $respuesta->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
        }
    }
    
    public function delete(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $respuesta = Respuesta::findOrFail($request->id);
        if (!$this->respuestaPerteneceUsuario($respuesta)) {
            return $this->respuestaNoAutorizado($request);
        }
        $respuesta->delete();
    }

    public function exportar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $respuestaExport = new RespuestaExport();        
        return Excel::download($respuestaExport, 'respuestas.xlsx');
    }
}

