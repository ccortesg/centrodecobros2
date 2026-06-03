<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use SoapClient;
use telesign\sdk\messaging\MessagingClient;
use Mail;

use App\User;
use App\Transaccion;
use App\Respuesta;
use App\TransaccionDom;
use App\Mail\TransaccionValidada;

use Exception;

use App\Exports\ReporteTransaccionDomExport;
use App\Exports\TransaccionDomExport;
use Excel;

class TransaccionDomController extends Controller
{
    public $destinationPath = "transaccionesDom/";    
    public $urlDom = "https://pagadetodo.mx/Pagadetodo/Service/PagarDomiciliacionIndi"; 

    public $IntegrationID = "";
    public $BusinessID = "";
    
    public $IntegrationIDDom = "";
    public $BusinessIDDom = "";

    //Integration y Business para Banco de Alimentos
    public $IntegrationIDDomBA = "";
    public $BusinessIDDomBA = "";

    // Credenciales configuradas por ambiente; no deben versionarse.
    public $User = "";
    public $Password = "";

    public $UserSandbox = "";
    public $PasswordSandbox = "";

     public $UserDom = "";
     public $PasswordDom = "";

     public $UserDomBA = "";
     public $PasswordDomBA = "";

    public function __construct()
    {
        $this->urlDom = config('services.pagadetodo.urls.cargo_domiciliacion', $this->urlDom);

        $this->IntegrationID = config('services.pagadetodo.integration.id', '');
        $this->BusinessID = config('services.pagadetodo.integration.business_id', '');
        $this->IntegrationIDDom = config('services.pagadetodo.integration.dom_id', '');
        $this->BusinessIDDom = config('services.pagadetodo.integration.dom_business_id', '');
        $this->IntegrationIDDomBA = config('services.pagadetodo.integration.dom_ba_id', '');
        $this->BusinessIDDomBA = config('services.pagadetodo.integration.dom_ba_business_id', '');

        $this->User = config('services.pagadetodo.credentials.user', '');
        $this->Password = config('services.pagadetodo.credentials.password', '');
        $this->UserDom = config('services.pagadetodo.credentials.dom_user', '');
        $this->PasswordDom = config('services.pagadetodo.credentials.dom_password', '');
        $this->UserDomBA = config('services.pagadetodo.credentials.dom_ba_user', '');
        $this->PasswordDomBA = config('services.pagadetodo.credentials.dom_ba_password', '');
        $this->UserSandbox = config('services.pagadetodo.credentials.sandbox_user', '');
        $this->PasswordSandbox = config('services.pagadetodo.credentials.sandbox_password', '');
    }

    private function criteriosTransaccionDomPermitidos()
    {
        return [
            'ClientReference',
            'Reference',
            'response_reference',
            'status',
            'foliocpagos',
            'auth',
            'cliente_nombre',
        ];
    }

    private function apiTieneCampos(array $data, array $fields)
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                return false;
            }
        }

        return true;
    }

    private function apiCargoError($code, $message, $status = 400)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'folio' => '',
            'monto' => '',
            'reference' => '',
            'foliocpagos' => '',
            'auth' => '',
            'cc_type' => '',
            'cc_name' => '',
            'cc_number' => '',
            'cc_expmonth' => '',
            'cc_expyear' => '',
            'amount' => '',
            "id_url" => '',
            'email' => '',
            'payment_type' => '',
        ], $status);
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');               
        
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $offset =  $this->offsetPaginacion($request->offset);
        $tipo =  $request->tipo;

        $query = TransaccionDom::leftjoin('clientes','clientes.id','transaccionesDom.idcliente')
            ->leftjoin('transacciones','transacciones.id','transaccionesDom.idtransaccion')
            ->select('transaccionesDom.id','transaccionesDom.idtransaccion','transaccionesDom.idcliente','transaccionesDom.fecha',
            'transaccionesDom.Token','transaccionesDom.Amount','transaccionesDom.Reference','transaccionesDom.ExpMonth','transaccionesDom.ExpYear',
            'transaccionesDom.code','transaccionesDom.message','transaccionesDom.response_reference','transaccionesDom.status','transaccionesDom.foliocpagos',
            'transaccionesDom.auth','transaccionesDom.cd_response','transaccionesDom.cd_error','transaccionesDom.nb_error','transaccionesDom.time','transaccionesDom.date',
            'transaccionesDom.nb_company','transaccionesDom.nb_merchant','transaccionesDom.nb_street','transaccionesDom.cc_type','transaccionesDom.tp_operation',
            'transaccionesDom.cc_name','transaccionesDom.cc_number','transaccionesDom.cc_expmonth','transaccionesDom.cc_expyear','transaccionesDom.response_amount',
            'transaccionesDom.voucher','transaccionesDom.payment_type','transaccionesDom.response_token','transaccionesDom.idusuario','transaccionesDom.productivo',
            'clientes.razon_social');

        $this->aplicarScopePropietario($query, 'transaccionesDom');
        
        if ($buscar!=''){
            if (!$this->criterioPermitido($criterio, $this->criteriosTransaccionDomPermitidos())) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Criterio de búsqueda no permitido.',
                ], 422);
            }

            if($criterio == 'cliente_nombre'){
                $query->where('clientes.razon_social', 'like', '%'. $buscar . '%');
            } else if($criterio  == 'ClientReference') { 
                $query->where('transacciones.ClientReference', 'like', '%'. $buscar . '%');
            } else {
                $query->where('transaccionesDom.'.$criterio, 'like', '%'. $buscar . '%');
            }        
        }        
        
        $transaccionesDom = $query->orderBy('transaccionesDom.id', 'desc')->paginate($offset);

        return [       
            'pagination' => [
                'total'        => $transaccionesDom->total(),
                'current_page' => $transaccionesDom->currentPage(),
                'per_page'     => $transaccionesDom->perPage(),
                'last_page'    => $transaccionesDom->lastPage(),
                'from'         => $transaccionesDom->firstItem(),
                'to'           => $transaccionesDom->lastItem(),
            ],    
            'transaccionesDom' => $transaccionesDom
        ];
    }
    
    public function reporteTransaccionesDom(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $transacciones = $this->buildReporteTransaccionesDomQuery(
            $request->idcliente,
            $request->fechaInicio,
            $request->fechaFin
        )->orderBy('transaccionesDom.id', 'desc')->get();

        return [            
            'transacciones' => $transacciones
        ];
    }

    public function store(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $this->validate($request,[            
            'idtransaccion' => 'required'
        ],
        [            
            'idtransaccion.required' => 'Debe seleccionar la transacción.'
        ]); 
                       
        //$date = Carbon::createFromFormat('Y-m-d', $request->ExpirationDate);

        $respuesta = Respuesta::where([
            ['idtransaccion', '=', $request->idtransaccion],
            ['status','LIKE','approved']])
            ->first();
        
        $transaccion = Transaccion::find($request->idtransaccion);
        if (!$this->usuarioPuedeOperarRegistro($transaccion)) {
            return $this->respuestaNoAutorizado($request);
        }
        
        $Amount = $transaccion->Amount;
        $Token = $respuesta->number_tkn;
        $ExpMonth = $respuesta->cc_expmonth;
        $ExpYear = $respuesta->cc_expyear;
        $params = array();
        $max = 0;
        $User = "";
        $Password = "";
        $IntegrationID = "";
        $BusinessID = "";
        
        if(\Auth::user()->productivo == 1) {
            //Validar IntegrationID y BusinessID dependiendo del usuario
            //Se dejo fijo por tesoreria                         
            if(\Auth::user()->id == 1 || \Auth::user()->id == 6) {
                $IntegrationID = $this->IntegrationIDDom;
                $BusinessID = $this->BusinessIDDom;
                $User = $this->UserDom;
                $Password = $this->PasswordDom;
            } else if(\Auth::user()->id == 4) {
                $IntegrationID = $this->IntegrationIDDomBA;
                $BusinessID = $this->BusinessIDDomBA;
                $User = $this->UserDomBA;
                $Password = $this->PasswordDomBA;
            } else {
                $IntegrationID = \Auth::user()->IntegrationID;
                $BusinessID = \Auth::user()->BusinessID;
                $User = $this->User;
                $Password = $this->Password;
            }
            $max = (TransaccionDom::where([['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (TransaccionDom::where([['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') + 1);            
        } else {            
            $IntegrationID = \Auth::user()->IntegrationID;
            $BusinessID = \Auth::user()->BusinessID;
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
            $max = (TransaccionDom::where('productivo', '=', '0')->max('folio') == null) ? 1 : (TransaccionDom::where('productivo', '=',  '0')->max('folio') + 1);
        }
        
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "Token" => $Token,
            "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),            
            "Amount" => $Amount,            
            "ExpMonth" => $ExpMonth,
            "ExpYear" => $ExpYear
        );

        $error = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{        
            $response = $this->postJsonControlado($this->urlDom, $params);
        } catch (RequestException $e){
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body, true);
            $error = $response_decode['message'];
        }

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body, true);
        }        

        $transaccionDom = new TransaccionDom();
        try{
            DB::beginTransaction();                
            $mytime = Carbon::now('America/Hermosillo');
            $transaccionDom->fecha = $mytime->toDateTimeString();
            $transaccionDom->folio = $max;
            $transaccionDom->idtransaccion = $transaccion->id;
            $transaccionDom->idcliente = $transaccion->idcliente;
            $transaccionDom->User = $User;
            $transaccionDom->Password = $Password;
            $transaccionDom->IntegrationID = $IntegrationID;
            $transaccionDom->BusinessID = $BusinessID;
            $transaccionDom->Token = $Token;
            $transaccionDom->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);            
            $transaccionDom->Amount = $Amount;            
            $transaccionDom->ExpMonth = $ExpMonth;
            $transaccionDom->ExpYear = $ExpYear;            
            $transaccionDom->response = $response_body;            
            $transaccionDom->code = $response_decode['code'];
            $transaccionDom->message = $response_decode['message'];
            if($transaccionDom->code == '00'){
                $transaccionDom->response_reference = $response_decode['txResponse']['reference'];
                $transaccionDom->status = $response_decode['txResponse']['response'];
                $transaccionDom->foliocpagos = $response_decode['txResponse']['foliocpagos'];
                $transaccionDom->auth = $response_decode['txResponse']['auth'];                
                $transaccionDom->cd_response = $response_decode['txResponse']['cd_response'];
                $transaccionDom->cd_error = $response_decode['txResponse']['cd_error'];
                $transaccionDom->nb_error = $response_decode['txResponse']['nb_error'];
                $transaccionDom->time = $response_decode['txResponse']['time'];                
                $transaccionDom->date = $response_decode['txResponse']['date'];                
                $transaccionDom->nb_company = $response_decode['txResponse']['nb_company'];
                $transaccionDom->nb_merchant = $response_decode['txResponse']['nb_merchant'];
                $transaccionDom->nb_street = $response_decode['txResponse']['nb_street'];
                $transaccionDom->cc_type = $response_decode['txResponse']['cc_type'];
                $transaccionDom->tp_operation = $response_decode['txResponse']['tp_operation'];
                $transaccionDom->cc_name = $response_decode['txResponse']['cc_name'];
                $transaccionDom->cc_number = $response_decode['txResponse']['cc_number'];
                $transaccionDom->cc_expmonth = $response_decode['txResponse']['cc_expmonth'];
                $transaccionDom->cc_expyear = $response_decode['txResponse']['cc_expyear'];
                $transaccionDom->response_amount = $response_decode['txResponse']['amount'];
                $transaccionDom->voucher = $response_decode['txResponse']['voucher'];
                $transaccionDom->payment_type = $response_decode['txResponse']['payment_type'];
                $transaccionDom->response_token = $response_decode['token'];
            }
            $transaccionDom->idusuario = \Auth::user()->id;
            $transaccionDom->productivo = \Auth::user()->productivo;
            $transaccionDom->save();            
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            $error = $e->getMessage();
        }

        return [                
            'error' => $error,
            'msg' => "El cargo se realizó con éxito."
        ];
    }

    public function storeAPI(Request $request)
    {        
        $data = $request->json()->all();
        if(!$this->apiTieneCampos($data, ['User', 'Password'])){
            return $this->apiCargoError("02", "El usuario y la contraseÃ±a son obligatorios.");
        }
        if(!$this->apiTieneCampos($data, ['ClientReference'])){
            return $this->apiCargoError("03", "La referencia del cliente es obligatoria.");
        }
        if(!array_key_exists('Amount', $data) || !is_numeric($data['Amount'])){
            return $this->apiCargoError("03", "El monto es obligatorio.");
        }

        $usuario = User::where([['usuario','LIKE',$data['User']],['token','LIKE',$data['Password']]])->first();
        if($usuario == null){
            return response()->json([
                'code' => "50",
                'message' => "Error interno del sistema, el usuario no pudo ser identificado. ",
                'folio' => '',
                'monto' => '',
                'reference' => '',
                'foliocpagos' => '',
                'auth' => '',
                'cc_type' => '',
                'cc_name' => '',
                'cc_number' => '',
                'cc_expmonth' => '',
                'cc_expyear' => '',
                'amount' => '',
                "id_url" => '',
                'email' => '',
                'payment_type' => '',
            ], 400);
        }

        $transaccion = Transaccion::where([
            ['ClientReference','LIKE',$data['ClientReference']],
            ['tipo','=','2']])->first();        
        if($transaccion == null){
            return response()->json([
                'code' => "51",
                'message' => "La transacción no pudo ser identificada.",
                'folio' => '',
                'monto' => '',
                'reference' => '',
                'foliocpagos' => '',
                'auth' => '',
                'cc_type' => '',
                'cc_name' => '',
                'cc_number' => '',
                'cc_expmonth' => '',
                'cc_expyear' => '',
                'amount' => '',
                "id_url" => '',
                'email' => '',
                'payment_type' => '',
            ], 400);
        }

        $respuesta = Respuesta::where([
            ['idtransaccion', '=', $transaccion->id],
            ['status','LIKE','approved']])->first();
        if($respuesta == null){
            return response()->json([
                'code' => "52",
                'message' => "La respuesta no pudo ser identificada.",
                'folio' => '',
                'monto' => '',
                'reference' => '',
                'foliocpagos' => '',
                'auth' => '',
                'cc_type' => '',
                'cc_name' => '',
                'cc_number' => '',
                'cc_expmonth' => '',
                'cc_expyear' => '',
                'amount' => '',
                "id_url" => '',
                'email' => '',
                'payment_type' => '',
            ], 400);
        }
        $Amount = 0;
        if($data['Amount']==0){
            $Amount = $transaccion->Amount;
        }else{
            $Amount =  $data['Amount']*100;
        }        
        $Token = $respuesta->number_tkn;
        $ExpMonth = $respuesta->cc_expmonth;
        $ExpYear = $respuesta->cc_expyear;
        $params = array();
        $max = 0;    
        $User = "";
        $Password = ""; 
        $IntegrationID = "";
        $BusinessID = "";
                    
        if($usuario->productivo == 1) {
            //Validar IntegrationID y BusinessID dependiendo del usuario
            //Se dejo fijo por tesoreria                         
            if($usuario->id == 1 || $usuario->id == 6) {
                $IntegrationID = $this->IntegrationIDDom;
                $BusinessID = $this->BusinessIDDom;
                $User = $this->UserDom;
                $Password = $this->PasswordDom;
            } else if($usuario->id == 4) {
                $IntegrationID = $this->IntegrationIDDomBA;
                $BusinessID = $this->BusinessIDDomBA;
                $User = $this->UserDomBA;
                $Password = $this->PasswordDomBA;
            } else {
                $IntegrationID = $usuario->IntegrationID;
                $BusinessID = $usuario->BusinessID;
                $User = $this->User;
                $Password = $this->Password;
            }
            $max = (TransaccionDom::where([['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (TransaccionDom::where([['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') + 1);               
        } else {
            $IntegrationID = $usuario->IntegrationID;
            $BusinessID = $usuario->BusinessID;
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
            $max = (TransaccionDom::where('productivo', '=', '0')->max('folio') == null) ? 1 : (TransaccionDom::where('productivo', '=',  '0')->max('folio') + 1);                
        }
        
        if($max == 0){
            Log::info('No se pudo obtener el folio en storeAPI de domiciliación.');
            return response()->json([
                'code' => "53",
                'message' => "El folio no pudo ser asignado.",
                'folio' => '',
                'monto' => '',
                'reference' => '',
                'foliocpagos' => '',
                'auth' => '',
                'cc_type' => '',
                'cc_name' => '',
                'cc_number' => '',
                'cc_expmonth' => '',
                'cc_expyear' => '',
                'amount' => '',
                "id_url" => '',
                'email' => '',
                'payment_type' => '',
            ], 400);
        }

        // Set request params
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "Token" => $Token,
            "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),
            "Amount" => $Amount,
            "ExpMonth" => $ExpMonth,
            "ExpYear" => $ExpYear
        );

        $error = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{        
            $response = $this->postJsonControlado($this->urlDom, $params);
        } catch (RequestException $e){           
            Log::info('Error al consultar el servicio para registrar el cargo de domiciliación.');
            Log::info($e->getMessage());
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body, true);
            $error = "Error al consultar el servicio para registrar el cargo de domiciliación.";
            $error_code = "54";            
        }

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body, true);
            Log::info('response_body: '.$response_body);
        }        

        $transaccionDom = new TransaccionDom();
        try{
            DB::beginTransaction();
            $mytime = Carbon::now('America/Hermosillo');
            $transaccionDom->fecha = $mytime->toDateTimeString();
            $transaccionDom->folio = $max;
            $transaccionDom->idtransaccion = $transaccion->id;
            $transaccionDom->idcliente = $transaccion->idcliente;
            $transaccionDom->User = $User;
            $transaccionDom->Password = $Password;
            $transaccionDom->IntegrationID = $IntegrationID;
            $transaccionDom->BusinessID = $BusinessID;
            $transaccionDom->Token = $Token;
            $transaccionDom->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);
            $transaccionDom->Amount = $Amount;
            $transaccionDom->ExpMonth = $ExpMonth;
            $transaccionDom->ExpYear = $ExpYear;
            $transaccionDom->response = $response_body;
            $transaccionDom->code = $response_decode['code'];
            $transaccionDom->message = $response_decode['message'];
            if($transaccionDom->code == '00'){
                $transaccionDom->response_reference = $response_decode['txResponse']['reference'];
                $transaccionDom->status = $response_decode['txResponse']['response'];
                $transaccionDom->foliocpagos = $response_decode['txResponse']['foliocpagos'];
                $transaccionDom->auth = $response_decode['txResponse']['auth'];                
                $transaccionDom->cd_response = $response_decode['txResponse']['cd_response'];
                $transaccionDom->cd_error = $response_decode['txResponse']['cd_error'];
                $transaccionDom->nb_error = $response_decode['txResponse']['nb_error'];
                $transaccionDom->time = $response_decode['txResponse']['time'];                
                $transaccionDom->date = $response_decode['txResponse']['date'];                
                $transaccionDom->nb_company = $response_decode['txResponse']['nb_company'];
                $transaccionDom->nb_merchant = $response_decode['txResponse']['nb_merchant'];
                $transaccionDom->nb_street = $response_decode['txResponse']['nb_street'];
                $transaccionDom->cc_type = $response_decode['txResponse']['cc_type'];
                $transaccionDom->tp_operation = $response_decode['txResponse']['tp_operation'];
                $transaccionDom->cc_name = $response_decode['txResponse']['cc_name'];
                $transaccionDom->cc_number = $response_decode['txResponse']['cc_number'];
                $transaccionDom->cc_expmonth = $response_decode['txResponse']['cc_expmonth'];
                $transaccionDom->cc_expyear = $response_decode['txResponse']['cc_expyear'];
                $transaccionDom->response_amount = $response_decode['txResponse']['amount'];
                $transaccionDom->voucher = $response_decode['txResponse']['voucher'];
                $transaccionDom->payment_type = $response_decode['txResponse']['payment_type'];
                $transaccionDom->response_token = $response_decode['token'];
            }                        
            $transaccionDom->idusuario = $usuario->id;
            $transaccionDom->productivo = $usuario->productivo;
            $transaccionDom->save();            
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            Log::info('Error al registrar la respuesta del cargo de domiciliación. Error: '.$e->getMessage());
            $error_code = "55";
            $error = "No se pudo guardar la respuesta del cargo.";
        }

        if($error != ""){
            return response()->json([
                'code' => $error_code,
                'message' => $error,
                'folio' => '',
                'monto' => '',
                'reference' => '',
                'foliocpagos' => '',
                'auth' => '',
                'cc_type' => '',
                'cc_name' => '',
                'cc_number' => '',
                'cc_expmonth' => '',
                'cc_expyear' => '',
                'amount' => '',
                "id_url" => '',
                'email' => '',
                'payment_type' => '',
            ], 400);
        }

        return response()->json([
            'code' => $transaccionDom->code,
            'message' => $transaccionDom->status,
            'folio' => $transaccionDom->Reference,
            'monto' => ((float)$transaccionDom->Amount/100),
            'reference' => $transaccionDom->response_reference,
            'foliocpagos' => $transaccionDom->foliocpagos,
            'auth' => $transaccionDom->auth,
            'cc_type' => $transaccionDom->cc_type,
            'cc_name' => $transaccionDom->cc_name,
            'cc_number' => $transaccionDom->cc_number,
            'cc_expmonth' => $transaccionDom->cc_expmonth,
            'cc_expyear' => $transaccionDom->cc_expyear,
            'amount' => $transaccionDom->response_amount,
            "id_url" => $transaccion->id_url,
            'email' => $transaccion->email,
            'payment_type' => $transaccionDom->payment_type,
        ], 200);
    }

    public function ejecutarCron(){

        //Agregar en el query para los cargos recurrentes qeu solo obtenga los que sean para el día de hoy
        $transacciones = Transaccion::join('respuestas','respuestas.idtransaccion','transacciones.id')
            ->leftjoin('clientes','clientes.id','transacciones.idcliente')
            ->join('users','users.id','transacciones.idusuario')
            ->select('transacciones.*')
            ->where([                
                ['transacciones.condicion', '=', '1'],
                ['transacciones.tipo', '=', '2'],
                ['transacciones.productivo','=','1'],
                ['respuestas.status','LIKE','approved'],
                ['users.recurrente', '=', '1'],])
            ->whereDate('transacciones.ProximoCargo', '=', Carbon::now()->toDateString())
            ->get();

        //Si no tiene datos la consulta agregar al log que no hubo transacciones
        if($transacciones->count() == 0){            
            Log::info('No se encontraron transacciones para ejecutar el cron de domiciliación.');            
        }

        //Recorrer las transacciones si hay
        foreach ($transacciones as $transaccion) {
            
            $cargoDate = Carbon::createFromFormat('Y-m-d', $transaccion->ProximoCargo);
    
            $respuesta = Respuesta::where([
                ['idtransaccion', '=', $transaccion->id],
                ['status','LIKE','approved']])
                ->first();

            $usuario = User::where('id','=',$transaccion->idusuario)->first();
    
            $Amount = $transaccion->Amount;
            $Token = $respuesta->number_tkn;
            $ExpMonth = $respuesta->cc_expmonth;
            $ExpYear = $respuesta->cc_expyear;

            $params = array();
            $max = 0;

            $User = $transaccion->User;
            $Password = $transaccion->Password;
            $IntegrationID = $transaccion->IntegrationID;
            $BusinessID = $transaccion->BusinessID;
                    
            $max = (TransaccionDom::where([['idusuario','=',$usuario->id],['productivo','=','1']])->max('folio') == null) ? 1 : (TransaccionDom::where([['idusuario','=',$usuario->id],['productivo','=','1']])->max('folio') + 1);

            $params = array(
                "User" => $User,
                "Password" => $Password,
                "IntegrationID" => $IntegrationID,
                "BusinessID" => $BusinessID,
                "Token" => $Token,
                "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),            
                "Amount" => $Amount,            
                "ExpMonth" => $ExpMonth,
                "ExpYear" => $ExpYear
            );

            $error = "";
            $response = "";
            $response_body = "";
            $response_decode = "";

            try{        
                $client = new Client();
                $response =  $client->request('POST', $this->urlDom, [RequestOptions::JSON => $params]);                        
            } catch (RequestException $e){
                //Agregar al log cuando hubo un error con la respuesta del error con el id de la transacción
                Log::info('Error en cargo de domiciliación. IdTransacción: '.$transaccion->id);
                $response  = $e->getResponse();
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body, true);
                $error = $response_decode['message'];
                //Agregar al log cuando hubo un error con la respuesta del error
                Log::info('Error al consultar el servicio para registrar el cargo de domiciliación. Error: '.$error);
                
            }

            if($response_decode == "") {
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body, true);
                //Agregar al log la respuesta del servicio
                Log::info('response_body: '.$response_body);
            }

            $transaccionDom = new TransaccionDom();

            try{
                DB::beginTransaction();                

                $mytime = Carbon::now('America/Hermosillo');                        

                $transaccionDom->fecha = $mytime->toDateTimeString();
                $transaccionDom->folio = $max;
                $transaccionDom->idtransaccion = $transaccion->id;
                $transaccionDom->idcliente = $transaccion->idcliente;
                $transaccionDom->User = $User;
                $transaccionDom->Password = $Password;
                $transaccionDom->IntegrationID = $IntegrationID;
                $transaccionDom->BusinessID = $BusinessID;
                $transaccionDom->Token = $Token;
                $transaccionDom->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);            
                $transaccionDom->Amount = $Amount;            
                $transaccionDom->ExpMonth = $ExpMonth;
                $transaccionDom->ExpYear = $ExpYear;            
                $transaccionDom->response = $response_body;            
                $transaccionDom->code = $response_decode['code'];
                $transaccionDom->message = $response_decode['message'];

                if($transaccionDom->code == '00'){
                    $transaccionDom->response_reference = $response_decode['txResponse']['reference'];
                    $transaccionDom->status = $response_decode['txResponse']['response'];
                    $transaccionDom->foliocpagos = $response_decode['txResponse']['foliocpagos'];
                    $transaccionDom->auth = $response_decode['txResponse']['auth'];                
                    $transaccionDom->cd_response = $response_decode['txResponse']['cd_response'];
                    $transaccionDom->cd_error = $response_decode['txResponse']['cd_error'];
                    $transaccionDom->nb_error = $response_decode['txResponse']['nb_error'];
                    $transaccionDom->time = $response_decode['txResponse']['time'];                
                    $transaccionDom->date = $response_decode['txResponse']['date'];                
                    $transaccionDom->nb_company = $response_decode['txResponse']['nb_company'];
                    $transaccionDom->nb_merchant = $response_decode['txResponse']['nb_merchant'];
                    $transaccionDom->nb_street = $response_decode['txResponse']['nb_street'];
                    $transaccionDom->cc_type = $response_decode['txResponse']['cc_type'];
                    $transaccionDom->tp_operation = $response_decode['txResponse']['tp_operation'];
                    $transaccionDom->cc_name = $response_decode['txResponse']['cc_name'];
                    $transaccionDom->cc_number = $response_decode['txResponse']['cc_number'];
                    $transaccionDom->cc_expmonth = $response_decode['txResponse']['cc_expmonth'];
                    $transaccionDom->cc_expyear = $response_decode['txResponse']['cc_expyear'];
                    $transaccionDom->response_amount = $response_decode['txResponse']['amount'];
                    $transaccionDom->voucher = $response_decode['txResponse']['voucher'];
                    $transaccionDom->payment_type = $response_decode['txResponse']['payment_type'];
                    $transaccionDom->response_token = $response_decode['token'];
                }
                            
                $transaccionDom->idusuario = $usuario->id;
                $transaccionDom->productivo = $transaccion->productivo;
                $transaccionDom->save();
                
                DB::commit();
            } catch (Exception $e){
                //Poner comentario de que no se pudo guardar correctamente un cargo recurrente
                DB::rollBack();        
                //Log de error al guardar el cargo recurrente
                Log::info('Error al guardar el cargo recurrente. IdTransacción: '.$transaccion->id);            
            }
            
            //Si el cargo fue rechazado o hubo error la fecha del próximo cargo será el siguiente día
            $newDate = Carbon::now('America/Hermosillo');
            if($transaccionDom->code == '00' && $transaccionDom->status == 'approved'){ 
                if($transaccion->frecuencia == 1){
                    $newDate = $cargoDate->addDay(7);
                } else if($transaccion->frecuencia == 2){
                    $newDate = $cargoDate->addMonth();
                } else if($transaccion->frecuencia == 3){
                    $newDate = $cargoDate->addMonth(2);
                } else if($transaccion->frecuencia == 4){
                    $newDate = $cargoDate->addMonth(6);
                } else if($transaccion->frecuencia == 5){
                    $newDate = $cargoDate->addYear();
                }
            } else {
                $newDate = $newDate->addDay();                        
            }
                                
            try{
                $tran = Transaccion::find($transaccion->id);
                DB::beginTransaction();    
                $tran->ProximoCargo = $newDate->toDateString();
                $tran->save();
                DB::commit();
            } catch (Exception $e){
                //Poner comentario de que no se pudo guardar correctamente la fecha próxima de cargo
                DB::rollBack();
                //Log de error al guardar la fecha próxima de cargo
                Log::info('Error al guardar la fecha próxima de cargo. IdTransacción: '.$transaccion->id);
            }

            //Agregar envío de respuestas de cargos recurrentes
            //Se envía la respuesta del cargo si fue aprobado                    
            $usuario = User::find($transaccion->idusuario);
            if($usuario->notificaPago){                        
                // Set request params
                $params = array(
                    "folio" => $transaccionDom->folio,
                    "idtransaccion" => $transaccion->id,
                    "Reference" => $transaccionDom->Reference,
                    "ClientReference" => $transaccion->ClientReference,                    
                    "monto" => (float) $transaccionDom->response_amount,                    
                    "Amount" => (float) $transaccionDom->response_amount,
                    "ExpMonth" => $transaccionDom->ExpMonth,
                    "ExpYear" => $transaccionDom->ExpYear,
                    "response" => $transaccionDom->response,
                    "code" => $transaccionDom->code,
                    "message" => $transaccionDom->message,
                    "response_reference" => $transaccionDom->response_reference ? $transaccionDom->response_reference : "",
                    "status" => $transaccionDom->status ? $transaccionDom->status : "",
                    "foliocpagos" =>  $transaccionDom->foliocpagos ? $transaccionDom->foliocpagos : "",
                    "auth" => $transaccionDom->auth ? $transaccionDom->auth : "",
                    "cd_response" => $transaccionDom->cd_response ? $transaccionDom->cd_response : "",
                    "cd_error" => $transaccionDom->cd_error ? $transaccionDom->cd_error : "",
                    "nb_error" => $transaccionDom->nb_error ? $transaccionDom->nb_error : "",
                    "time" => $transaccionDom->time ? $transaccionDom->time : "",
                    "date" => $transaccionDom->date ? $transaccionDom->date : "",
                    "nb_company" => $transaccionDom->nb_company ? $transaccionDom->nb_company : "",
                    "nb_merchant" => $transaccionDom->nb_merchant ? $transaccionDom->nb_merchant : "",
                    "nb_street" => $transaccionDom->nb_street ? $transaccionDom->nb_street : "",
                    "tp_operation" => $transaccionDom->tp_operation ? $transaccionDom->tp_operation : "",
                    "cc_type" => $transaccionDom->cc_type ? $transaccionDom->cc_type : "",
                    "cc_name" => $transaccionDom->cc_name ? $transaccionDom->cc_name : "",
                    "cc_number" => $transaccionDom->cc_number ? $transaccionDom->cc_number : "",
                    "cc_expmonth" => $transaccionDom->cc_expmonth ? $transaccionDom->cc_expmonth : "",
                    "cc_expyear" => $transaccionDom->cc_expyear ? $transaccionDom->cc_expyear : "",
                    "amount" => $transaccionDom->cd_response ? $transaccionDom->cd_response : "",
                    "id_url" => $transaccionDom->id_url ? $transaccionDom->id_url : "",
                    "email" => $transaccionDom->email ? $transaccionDom->email : "",
                    "payment_type" => $transaccionDom->payment_type ? $transaccionDom->payment_type : ""
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
                    $response =  $client->request('POST', $usuario->ligaRecurrente, [RequestOptions::JSON => $params]);
                } catch (RequestException $e){
                    $response  = $e->getResponse();
                    $response_body = (string) $response->getBody();
                    Log::info('Falló el envío de la respuesta al domiciliar la transacción '. $transaccion->id);
                }

                if($response_decode == "") {
                    try {
                        $response_body = (string) $response->getBody();
                        $response_decode = json_decode($response_body);
                        $response_code = $response_decode->code;
                        $response_msg = $response_decode->message;
                    }
                    catch (Exception $e){
                        Log::info('Error al decodificar la respuesta al domiciliar la transacción '. $transaccion->id);
                    }                    
                }

                if($response_code == "success"){
                    $transaccionDom->enviada = 1;
                    $transaccionDom->save();
                }
            }
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

        $transaccionDom = TransaccionDom::findOrFail($request->id);
        if (!$this->usuarioPuedeOperarRegistro($transaccionDom)) {
            return $this->respuestaNoAutorizado($request);
        }
        $transaccionDom->response_reference = $request->reference;
        $transaccionDom->status = $request->response;
        $transaccionDom->foliocpagos = $request->foliocpagos;
        $transaccionDom->auth = $request->auth;
        $transaccionDom->cd_response = $request->cd_response;
        $transaccionDom->cd_error = $request->cd_error;
        $transaccionDom->nb_error = $request->nb_error;
        $transaccionDom->time = $request->time;
        $transaccionDom->date = $request->date;
        $transaccionDom->nb_company = $request->nb_company;
        $transaccionDom->nb_merchant = $request->nb_merchant;
        $transaccionDom->nb_street = $request->nb_street;
        $transaccionDom->cc_type = $request->cc_type;
        $transaccionDom->tp_operation = $request->tp_operation;
        $transaccionDom->cc_name = $request->cc_name;
        $transaccionDom->cc_number = $request->cc_number;
        $transaccionDom->cc_expmonth = $request->cc_expmonth;
        $transaccionDom->cc_expyear = $request->cc_expyear;
        $transaccionDom->response_amount = $request->amount;
        $transaccionDom->voucher = $request->voucher;
        $transaccionDom->payment_type = $request->payment_type;
        $transaccionDom->save();
    }
    
    public function delete(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $transaccion = TransaccionDom::findOrFail($request->id);
        if (!$this->usuarioPuedeOperarRegistro($transaccion)) {
            return $this->respuestaNoAutorizado($request);
        }
        $transaccion->delete();
    }

    public function exportar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $transaccionDomExport = new TransaccionDomExport();        
        return Excel::download($transaccionDomExport, 'transaccionesdom.xlsx');
    }

    public function exportarTransacciones(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $transacciones = $this->buildReporteTransaccionesDomQuery(
            $request->idcliente,
            $request->fechaInicio,
            $request->fechaFin
        )->orderBy('transaccionesDom.id', 'desc')->get();

        return Excel::download(
            new ReporteTransaccionDomExport($transacciones),
            'reporteTransaccionesDom.xlsx'
        );
    }

    private function buildReporteTransaccionesDomQuery($idcliente, $fechaInicio, $fechaFin)
    {
        $query = TransaccionDom::join('transacciones','transacciones.id','transaccionesDom.idtransaccion')
            ->leftjoin('clientes','clientes.id','transacciones.idcliente')
            ->leftjoin('users','users.id','transacciones.idusuario')
            ->select('transaccionesDom.id','transaccionesDom.folio','transaccionesDom.fecha','transacciones.PaymentTypes',
            'transacciones.IdReference','transacciones.Description','transaccionesDom.Amount','transaccionesDom.Reference',
            'transacciones.ExpirationDate','transacciones.ClientReference','transacciones.url', 'transaccionesDom.code',
            'transaccionesDom.message','transaccionesDom.response_reference','transacciones.referenceEmisor',
            'transacciones.idusuario','transacciones.idcliente','clientes.razon_social','users.usuario');

        if ((int) $idcliente > 0) {
            $query->where('transacciones.idcliente', '=', $idcliente);
        }

        $this->aplicarScopePropietario($query, 'transacciones');

        $query->where('transaccionesDom.status', '=', 'approved');

        if ($fechaInicio != 'null' && $fechaInicio != '') {
            $query->whereBetween('transaccionesDom.fecha', [
                Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $fechaFin)->endOfDay(),
            ]);
        }

        return $query;
    }
}
