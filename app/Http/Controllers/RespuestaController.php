<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


use App\User;
use App\Transaccion;
use App\Respuesta;

use App\Exports\RespuestaExport;
use Excel;
use Exception;
use Throwable;

use App\Services\WebhookEventPublisher;
use App\Services\TransaccionStatusSynchronizer;

class RespuestaController extends Controller
{
    private TransaccionStatusSynchronizer $statusSynchronizer;

    public function __construct(TransaccionStatusSynchronizer $statusSynchronizer)
    {
        $this->statusSynchronizer = $statusSynchronizer;
    }

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

    private function eventosWebhookRespuesta($transaccion, Respuesta $respuesta)
    {
        if ($transaccion === null) {
            return [];
        }

        $approved = (string) $respuesta->status === 'approved';

        if ((int) $transaccion->tipo === 1) {
            return [$approved ? 'payment_link.payment.approved' : 'payment_link.payment.rejected'];
        }

        if ((int) $transaccion->tipo === 2) {
            if (!$approved) {
                return ['domiciliation_link.payment.rejected'];
            }

            return [
                'domiciliation_link.payment.approved',
                trim((string) $respuesta->number_tkn) === ''
                    ? 'domiciliation.activation_failed'
                    : 'domiciliation.activated',
            ];
        }

        if ((int) $transaccion->tipo === 4) {
            return [$approved ? 'terminal.payment.approved' : 'terminal.payment.rejected'];
        }

        return [];
    }

    private function payloadWebhookRespuesta(Transaccion $transaccion, array $data, $lector = false)
    {
        $payload = $data;
        $payload['folio'] = $transaccion->ClientReference;
        $payload['monto'] = (float) ($data['amount'] ?? 0);
        $payload['reference'] = $data['reference'] ?? '';
        $payload['foliocpagos'] = $lector ? ($data['folio'] ?? '') : ($data['foliocpagos'] ?? '');
        $payload['auth'] = $data['auth'] ?? '';
        $payload['cc_type'] = $lector ? ($data['ccType'] ?? '') : ($data['cc_type'] ?? '');
        $payload['cc_name'] = $lector ? ($data['ccName'] ?? '') : ($data['cc_name'] ?? '');
        $payload['cc_number'] = $lector ? ($data['ccNumber'] ?? '') : ($data['cc_number'] ?? '');
        $payload['cc_expmonth'] = $lector ? ($data['ccExpMonth'] ?? '') : ($data['cc_expmonth'] ?? '');
        $payload['cc_expyear'] = $lector ? ($data['ccExpYear'] ?? '') : ($data['cc_expyear'] ?? '');
        $payload['amount'] = $data['amount'] ?? 0;
        $payload['id_url'] = $data['id_url'] ?? '';
        $payload['email'] = $data['email'] ?? '';
        $payload['payment_type'] = $data['payment_type'] ?? '';

        return $payload;
    }

    private function procesarNotificacionRespuesta(
        $transaccion,
        Respuesta $respuesta,
        array $data,
        $lector = false
    ) {
        if ($transaccion === null) {
            return;
        }

        $usuario = User::find($transaccion->idusuario);
        $payload = $this->payloadWebhookRespuesta($transaccion, $data, $lector);
        $publisher = app(WebhookEventPublisher::class);

        foreach ($this->eventosWebhookRespuesta($transaccion, $respuesta) as $eventType) {
            $publisher->publish($usuario, $eventType, $payload, [
                'idtransaccion' => $transaccion->id,
                'source_type' => 'respuesta',
                'source_id' => $respuesta->id,
                'source_context' => $lector ? 'terminal' : 'webhook',
                'source_payload' => $data,
                'idempotency_key' => $eventType . ':respuesta:' . $respuesta->id,
                'occurred_at' => $respuesta->fecha,
            ]);
        }

        if ((string) $respuesta->status !== 'approved'
            || $usuario === null
            || !$usuario->notificaPago
            || !$publisher->shouldUseLegacy($usuario)) {
            return;
        }

        try {
            $response = $this->postJsonAuditado($usuario->ligaPago, $payload, [
                'provider' => 'Cliente',
                'source_context' => $lector ? 'callback_webhook_lector' : 'callback_webhook_liga',
                'idusuario' => $usuario->id,
                'idtransaccion' => $transaccion->id,
                'productivo' => $transaccion->productivo,
                'correlation_reference' => $transaccion->ClientReference,
            ]);
            $decoded = json_decode((string) $response->getBody(), true);

            if (is_array($decoded) && strtolower((string) ($decoded['code'] ?? '')) === 'success') {
                $respuesta->enviada = 1;
                $respuesta->save();
            }
        } catch (Throwable $exception) {
            Log::info('Fallo el envio de la respuesta de la transaccion ' . $transaccion->id, [
                'error' => $exception->getMessage(),
            ]);
        }
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

    private function statusRespuestaValido($status)
    {
        return in_array((string) $status, ['approved', 'denied', 'error', '99'], true);
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
        $status = $request->status ?? 99;
        $fechaInicio = $request->fechaInicio ?? '';
        $fechaFin = $request->fechaFin ?? '';

        if ($validacionFechas = $this->validarRangoFechasListado($fechaInicio, $fechaFin)) {
            return $validacionFechas;
        }

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

        if (!$this->statusRespuestaValido($status)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Status no permitido.',
            ], 422);
        }

        if ((string) $status !== '99') {
            $query->where('respuestas.status', '=', $status);
        }

        $this->aplicarRangoFechasListado($query, 'respuestas.fecha', $fechaInicio, $fechaFin);

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
        $transaccion = $this->transaccionPorRespuestaReferencia($request->reference);

        if ($transaccion !== null && !$this->usuarioPuedeOperarRegistro($transaccion)) {
            return $this->respuestaNoAutorizado($request);
        }

        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $respuesta->idtransaccion = $transaccion->id ?? null;
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
            $this->statusSynchronizer->sincronizarPorRespuesta($transaccion, $respuesta);
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
                $this->statusSynchronizer->sincronizarPorRespuesta($transaccion, $duplicada);
                DB::commit();
                $this->procesarNotificacionRespuesta($transaccion, $duplicada, $date_response);
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
            $this->statusSynchronizer->sincronizarPorRespuesta($transaccion, $respuesta);
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            Log::error('Error al guardar webhook Pagadetodo liga: ' . $e->getMessage());
            return $this->respuestaWebhookInvalido('No se pudo guardar el webhook.', 500);
        }

        $this->procesarNotificacionRespuesta($transaccion, $respuesta, $date_response);

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
                $this->statusSynchronizer->sincronizarPorRespuesta($transaccion, $duplicada);
                DB::commit();
                $this->procesarNotificacionRespuesta($transaccion, $duplicada, $date_response, true);
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
            $this->statusSynchronizer->sincronizarPorRespuesta($transaccion, $respuesta);
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            Log::error('Error al guardar webhook Pagadetodo lector: ' . $e->getMessage());
            return $this->respuestaWebhookInvalido('No se pudo guardar el webhook.', 500);
        }

        $this->procesarNotificacionRespuesta($transaccion, $respuesta, $date_response, true);

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
            $transaccion = $respuesta->idtransaccion
                ? Transaccion::find($respuesta->idtransaccion)
                : $this->transaccionPorRespuestaReferencia($request->reference);

            if ($transaccion !== null && !$this->usuarioPuedeOperarRegistro($transaccion)) {
                DB::rollBack();
                return $this->respuestaNoAutorizado($request);
            }

            if ($respuesta->idtransaccion === null && $transaccion !== null) {
                $respuesta->idtransaccion = $transaccion->id;
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
            $this->statusSynchronizer->sincronizarPorRespuesta($transaccion, $respuesta);
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
        $buscar = $request->buscar ?? '';
        $criterio = $request->criterio ?? 'Reference';
        $tipo = $request->tipo ?? null;
        $status = $request->status ?? 99;
        $fechaInicio = $request->fechaInicio ?? '';
        $fechaFin = $request->fechaFin ?? '';

        if ($buscar !== '' && !$this->criterioPermitido($criterio, $this->criteriosRespuestaPermitidos())) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Criterio de búsqueda no permitido.',
            ], 422);
        }

        if (!$this->statusRespuestaValido($status)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Status no permitido.',
            ], 422);
        }

        if ($validacionFechas = $this->validarRangoFechasListado($fechaInicio, $fechaFin)) {
            return $validacionFechas;
        }

        $respuestaExport = new RespuestaExport($tipo, $buscar, $criterio, $status, $fechaInicio, $fechaFin);
        return Excel::download($respuestaExport, 'respuestas.xlsx');
    }
}
