<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use Carbon\Carbon;
use SoapClient;
use Mail;
use Illuminate\Support\Str;

use App\User;
use App\Persona;
use App\Cliente;
use App\Transaccion;
use App\Respuesta;
use App\CancelacionDom;
use App\CancelacionLector;
use App\ConsultaSpei;
use App\PagoSpei;
use App\CancelaSpei;
use App\Mail\TransaccionValidada;

use Exception;

use App\Exports\ReporteExport;
use Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TransaccionController extends Controller
{
    public $destinationPath = "transacciones/";
    public $urlPath = "https://pagadetodo.mx/Pagadetodo/Service/GenerarLigaIndi";
    public $urlDomPath = "https://pagadetodo.mx/Pagadetodo/Service/GenerarLigaDomiciliacionIndi";
    public $urlDomCancel = "https://pagadetodo.mx/Pagadetodo/Service/CancelarDomiciliacionIndi";
    public $urlSpeiPath = "https://pagadetodo.mx/Pagadetodo/Service/GenerarClabeIndi";
    public $urlLectorPath = "https://pagadetodo.mx/Pagadetodo/Service/GenerarPagoLectorIndi";
    public $urlLectorCancel = "https://pagadetodo.mx/Pagadetodo/Service/CancelarReferenciaLectorIndi";

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

    public $UserDom = "";
    public $PasswordDom = "";

    public $UserDomBA = "";
    public $PasswordDomBA = "";

    public $UserSandbox = "";
    public $PasswordSandbox = "";

    public $fechaInicio = "";
    public $fechaFin = "";

    public function __construct()
    {
        $this->urlPath = config('services.pagadetodo.urls.generar_liga', $this->urlPath);
        $this->urlDomPath = config('services.pagadetodo.urls.generar_domiciliacion', $this->urlDomPath);
        $this->urlDomCancel = config('services.pagadetodo.urls.cancelar_domiciliacion', $this->urlDomCancel);
        $this->urlSpeiPath = config('services.pagadetodo.urls.generar_spei', $this->urlSpeiPath);
        $this->urlLectorPath = config('services.pagadetodo.urls.generar_lector', $this->urlLectorPath);
        $this->urlLectorCancel = config('services.pagadetodo.urls.cancelar_lector', $this->urlLectorCancel);

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

    private function apiTieneCampos(array $data, array $fields)
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                return false;
            }
        }

        return true;
    }

    private function leerPayloadServicioJson(Request $request, array $fields)
    {
        $raw = (string) $request->getContent();
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [null, 'JSON invalido.'];
        }

        if (!$this->apiTieneCampos($data, $fields)) {
            return [null, 'Campos requeridos incompletos.'];
        }

        return [$data, null];
    }

    private function valorServicio(array $data, $field, $default = '')
    {
        return array_key_exists($field, $data) ? $data[$field] : $default;
    }

    private function apiTieneCredenciales(array $data)
    {
        return $this->apiTieneCampos($data, ['User', 'Password']);
    }

    private function apiLigaError($code, $message, $status = 400)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'url' => '',
            'reference' => '',
            'referenceEmisor' => ''
        ], $status);
    }

    private function apiLectorError($code, $message, $status = 400)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'codeQR' => '',
            'reference' => '',
            'referenceEmisor' => ''
        ], $status);
    }

    private function apiCancelacionDomError($code, $message, $status = 400)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'reference' => ''
        ], $status);
    }

    private function clienteAutorizado($idcliente)
    {
        if ((int) $idcliente <= 0 || $this->usuarioEsAdministrador()) {
            return true;
        }

        return Cliente::where('id', '=', $idcliente)
            ->where('idusuario', '=', \Auth::user()->id)
            ->exists();
    }

    private function criteriosTransaccionPermitidos()
    {
        return [
            'folio',
            'fecha',
            'PaymentTypes',
            'IdReference',
            'Description',
            'Amount',
            'Reference',
            'ExpirationDate',
            'ClientReference',
            'responseReference',
            'referenceEmisor',
            'Error',
            'Date',
            'Clabe',
            'condicion',
            'cliente_nombre',
        ];
    }

    private function normalizarTelefonoCliente($telefono)
    {
        $telefono_limpio = preg_replace('/\D+/', '', trim($telefono));
        if(strlen($telefono_limpio) > 10){
            $telefono_limpio = substr($telefono_limpio, -10);
        }
        return $telefono_limpio;
    }

    private function resolverClienteApi($data, $usuario)
    {
        $cliente_id = 0;

        if(!(array_key_exists('Nombre', $data) && array_key_exists('Email', $data) && array_key_exists('Telefono', $data))){
            return $cliente_id;
        }

        $razon_social = "";
        $email_contacto = "";
        $telefono_contacto = "";
        $rfc_contacto = "";

        if($data['Nombre'] != ""){
            $razon_social = $data['Nombre'];
        }
        if($data['Email'] != ""){
            $email_contacto = trim($data['Email']);
        }
        if($data['Telefono'] != ""){
            $telefono_contacto = trim($data['Telefono']);
        }
        if(array_key_exists('RFC', $data) && $data['RFC'] != ""){
            $rfc_contacto = $data['RFC'];
        }

        $razon_social_normalizada = mb_strtolower(trim($razon_social));
        $email_contacto_normalizado = mb_strtolower(trim($email_contacto));
        $telefono_contacto_normalizado = $this->normalizarTelefonoCliente($telefono_contacto);

        $cliente = Cliente::where('idusuario', '=', $usuario->id)
            ->whereRaw('LOWER(TRIM(razon_social)) = ?', [$razon_social_normalizada])
            ->whereRaw('LOWER(TRIM(email_contacto)) = ?', [$email_contacto_normalizado])
            ->whereRaw("RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(telefono_contacto, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), 10) = ?", [$telefono_contacto_normalizado])
            ->first();

        if($cliente != null){
            return $cliente->id;
        }

        DB::beginTransaction();
        try{
            $num_documento = 1;
            if(Persona::join('clientes','clientes.id','personas.id')
                ->where([['tipo_documento','LIKE','CLIENTE'],['idusuario','=', $usuario->id]])->max(DB::raw('CAST(num_documento AS SIGNED)')) != null){
                $num_documento = Persona::join('clientes','clientes.id','personas.id')
                    ->where([['tipo_documento','LIKE','CLIENTE'],['idusuario','=', $usuario->id]])->max(DB::raw('CAST(num_documento AS SIGNED)')) + 1;
            }

            $persona = new Persona();
            $persona->nombre = $razon_social;
            $persona->tipo_documento = "CLIENTE";
            $persona->num_documento = $num_documento;
            $persona->telefono = $telefono_contacto;
            $persona->email = $email_contacto;
            $persona->save();

            $cliente = new Cliente();
            $cliente->id = $persona->id;
            $cliente->razon_social = $razon_social;
            $cliente->email_contacto = $email_contacto;
            $cliente->telefono_contacto = $telefono_contacto;
            $cliente->rfc = $rfc_contacto;
            $cliente->idciudad = 1;
            $cliente->idusuario = $usuario->id;
            $cliente->save();

            DB::commit();
            $cliente_id = $cliente->id;
        } catch(Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $cliente_id;
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');               
        
        $buscar = $request->buscar ?? '';
        $criterio = $request->criterio ?? '';
        $offset =  $this->offsetPaginacion($request->offset ?? 10);
        $tipo =  $request->tipo ?? 1;
        $status =  $request->status ?? 99;

        $query = Transaccion::leftjoin('clientes','clientes.id','transacciones.idcliente')
            ->leftjoin('users','users.id','transacciones.idusuario')
            ->select('transacciones.id','transacciones.folio','transacciones.fecha','transacciones.PaymentTypes',
            'transacciones.IdReference','transacciones.Description','transacciones.Amount','transacciones.Reference',
            'transacciones.ExpirationDate','transacciones.ClientReference','transacciones.url', 'transacciones.code',
            'transacciones.message','transacciones.responseReference','transacciones.referenceEmisor','transacciones.Error',
            'transacciones.Date','transacciones.Clabe','transacciones.idusuario','transacciones.idcliente','clientes.razon_social',
            'users.usuario','transacciones.tipo','transacciones.frecuencia','transacciones.ProximoCargo','transacciones.condicion',
            'transacciones.codeQR', 'transacciones.productivo');

        $query->where('transacciones.tipo', '=', $tipo);

        $this->aplicarScopePropietario($query, 'transacciones');
        
        if ($buscar!=''){
            if (!$this->criterioPermitido($criterio, $this->criteriosTransaccionPermitidos())) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Criterio de búsqueda no permitido.',
                ], 422);
            }

            if($criterio=='cliente_nombre'){
                $query->where('clientes.razon_social', 'like', '%'. $buscar . '%');
            } else {
                $query->where('transacciones.'.$criterio, 'like', '%'. $buscar . '%');
            }            
        }

        if($status <> 99) {
            $query->where('transacciones.status', '=', $status);
        }
        
        $transacciones = $query->orderBy('transacciones.id', 'desc')->paginate($offset);

        return [       
            'pagination' => [
                'total'        => $transacciones->total(),
                'current_page' => $transacciones->currentPage(),
                'per_page'     => $transacciones->perPage(),
                'last_page'    => $transacciones->lastPage(),
                'from'         => $transacciones->firstItem(),
                'to'           => $transacciones->lastItem(),
            ],    
            'transacciones' => $transacciones
        ];
    }

    public function reporteTransacciones(Request $request)
    {
        if (!$request->ajax()) return redirect('/');               
        
        $tipo =  $request->tipo;

        $fechaInicio = $request->fechaInicio;
        $fechaFin = $request->fechaFin;
        $idcliente = $request->idcliente;        

        $query = Transaccion::leftjoin('clientes','clientes.id','transacciones.idcliente')
            ->join('respuestas','respuestas.reference','transacciones.responseReference')
            ->leftjoin('users','users.id','transacciones.idusuario')
            ->select('transacciones.id','transacciones.folio','transacciones.fecha','transacciones.PaymentTypes',
            'transacciones.IdReference','transacciones.Description','transacciones.Amount','transacciones.Reference',
            'transacciones.ExpirationDate','transacciones.ClientReference','transacciones.url', 'transacciones.code',
            'transacciones.message','transacciones.responseReference','transacciones.referenceEmisor','transacciones.Error',
            'transacciones.Date','transacciones.Clabe','transacciones.idusuario','transacciones.idcliente','clientes.razon_social',
            'users.usuario','transacciones.tipo','transacciones.frecuencia','transacciones.ProximoCargo','transacciones.condicion',
            'transacciones.codeQR', 'transacciones.productivo');

        $atributos = [];

        if($idcliente > 0) array_push($atributos, [['transacciones.idcliente' => $idcliente]]);
                
        if(!$this->usuarioEsAdministrador()) {
            array_push($atributos, [['transacciones.idusuario' => \Auth::user()->id]]);
            array_push($atributos, [['transacciones.productivo' => \Auth::user()->productivo]]);
        }
        array_push($atributos, [['transacciones.tipo' => $tipo]]);
        array_push($atributos, [['respuestas.status' => 'approved']]);

        $query->where($atributos);

        if($fechaInicio != 'null' && $fechaInicio != '') {
            $this->fechaInicio = $fechaInicio;
            $this->fechaFin = $fechaFin;
            $query->where(function ($query) {
                $query->whereBetween('transacciones.fecha', [
                    Carbon::createFromFormat('Y-m-d',$this->fechaInicio)->startOfDay(), 
                    Carbon::createFromFormat('Y-m-d',$this->fechaFin)->endOfDay()]);
            });            
        }

        $transacciones = $query->orderBy('transacciones.id', 'desc')->get();

        return [            
            'transacciones' => $transacciones
        ];
    }
    
    public function selectDomiciliacion(Request $request)
    {
        if (!$request->ajax()) return redirect('/');        

        $transacciones = Transaccion::join('respuestas','respuestas.reference','transacciones.responseReference')
            ->leftjoin('clientes','clientes.id','transacciones.idcliente')
            ->select('transacciones.id','transacciones.ClientReference')
            ->where([
                ['transacciones.condicion', '=', '1'],
                ['transacciones.tipo', '=', '2'],
                ['respuestas.status','LIKE','approved']])
            ->orderBy('respuestas.id', 'desc');

        $this->aplicarScopePropietario($transacciones, 'transacciones');

        $transacciones = $transacciones->get();

        return [             
            'transacciones' => $transacciones
        ];
    }

    public function showForm(){
        //return view('transaccion.register');
        return redirect()->action([TransaccionController::class, 'index']);
    }

    public function showURL()
    {
        return view('transaccion.url');
    }

    public function openPublic(Request $request)
    {
        $validated = $request->validate([
            'url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

                    if (!in_array($scheme, ['http', 'https'], true)) {
                        $fail('La URL debe iniciar con http:// o https://.');
                    }
                },
            ],
        ], [
            'url.required' => 'Debe ingresar la URL.',
            'url.url' => 'Debe ingresar una URL valida.',
        ]);

        return redirect()->back()->withInput()->with('message', $validated['url']);
    }

    public function store(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $this->validate($request,[            
            'PaymentTypes' => 'required',
            'Description' => 'required|string|max:100',
            'Amount' => 'required',            
            'ClientReference' => 'required|string|max:50',
            'ExpirationDate' => 'required'
        ],
        [            
            'PaymentTypes.required' => 'Debe ingresar el tipo de pago.',
            'Description.required' => 'Debe ingresar la descripciÃ³n.',
            'Amount.required' => 'Debe ingresar el monto.',            
            'ClientReference.required' => 'Debe ingresar la referencia del cliente.',
            'ExpirationDate.required' => 'Debe ingresar la fecha de expiraciÃ³n.'
        ]); 

        if (!$this->clienteAutorizado($request->idcliente)) {
            return $this->respuestaNoAutorizado($request);
        }
                               
        $amount = $request->Amount * 100;
        $date = Carbon::createFromFormat('Y-m-d', $request->ExpirationDate);

        $max = 0;
        $params = array();

        $User = "";
        $Password = ""; 
        $IntegrationID = \Auth::user()->IntegrationID;
        $BusinessID = \Auth::user()->BusinessID;

        if(\Auth::user()->productivo == 1) {
            $max = (Transaccion::where([['tipo', '=', $request->tipo],['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') + 1);
            $User = $this->User;
            $Password = $this->Password;
        } else {
            $max = (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') + 1);
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
        }

        // Set request params
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "PaymentTypes" => $request->PaymentTypes,
            "Id" => str_pad($max, 10, '0', STR_PAD_LEFT),
            "Description" => $request->Description,
            "Amount" => $amount,
            "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),
            "ExpirationDate" => $date,
        );

        $error = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{        
            $response = $this->postJsonControlado($this->urlPath, $params);
        } catch (RequestException $e){            
            //$response  = Psr7\Message::toString($e->getRequest());
            //$response = Psr7\Message::toString($e->getResponse());
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
            $error = $response_decode->message;
        }

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
        }        

        $transaccion = new Transaccion();

        try{
            DB::beginTransaction();                

            $mytime= Carbon::now('America/Hermosillo');                        

            $transaccion->fecha = $mytime->toDateTimeString();
            $transaccion->folio = $max;
            $transaccion->User = $User;
            $transaccion->Password = $Password;
            $transaccion->IntegrationID = $IntegrationID;
            $transaccion->BusinessID = $BusinessID;
            $transaccion->PaymentTypes = $request->PaymentTypes;
            $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
            $transaccion->Description = $request->Description;
            $transaccion->Amount = $amount;
            $transaccion->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);
            $transaccion->ExpirationDate = $request->ExpirationDate;
            $transaccion->ClientReference = $request->ClientReference;
            $transaccion->response = $response_body;
            $transaccion->url = $response_decode->url;
            $transaccion->code = $response_decode->code;
            $transaccion->message = $response_decode->message;
            $transaccion->responseReference = $response_decode->reference;
            $transaccion->referenceEmisor = $response_decode->referenceEmisor;            
            $transaccion->idcliente = $request->idcliente;
            $transaccion->tipo = $request->tipo;
            $transaccion->idusuario =  \Auth::user()->id;
            $transaccion->productivo = \Auth::user()->productivo;
            $transaccion->save();
            
            DB::commit();

        } catch (Exception $e){
            DB::rollBack();
            $error = $e->getMessage();
        }

        return [                
            'error' => $error,
            'msg' => "El registro se realizÃ³ con Ã©xito."
        ];
    }

    //Store generar liga de pago API
    public function storeAPI(Request $request)
    {       
        $max = 0;                    
        $data = $request->json()->all();

        if(!array_key_exists('User', $data) || !array_key_exists('Password', $data)) {
            return response()->json([
                'code' => "02",
                'message' => "El usuario y la contraseÃ±a son obligatorios.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }

         if($data['User']=="" || $data['Password']==""){
            return response()->json([
                'code' => "02",
                'message' => "El usuario y la contraseÃ±a son obligatorios.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }        
        $usuario = User::where([['usuario','LIKE',$data['User']],['token','LIKE',$data['Password']]])->first();

        if($usuario == null){
            return response()->json([
                'code' => "50",
                'message' => "Error interno del sistema, el usuario no pudo ser identificado. ",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }

        if(!array_key_exists('Amount', $data)) {
            return response()->json([
                'code' => "03",
                'message' => "El monto es obligatorio.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }
         try {
            $amount = (float) $data['Amount'] * 100;            
         } catch(Exception $e) {
            return response()->json([
                'code' => "03",
                'message' => "Alguno de los valores no fueron establecidos.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }

        //Crear la variable $date con la fecha de hoy
        //$date = Carbon::now('America/Hermosillo');
        if(!array_key_exists('ExpirationDate', $data)) {
            return response()->json([
                'code' => "03",
                'message' => "La fecha de vencimiento es obligatoria.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }
        $date = Carbon::createFromFormat('Y-m-d', $data['ExpirationDate']);
        /*try {            
            //Comparar si la fecha ExpirationDate es menor que la actual
            if($date->lessThan(Carbon::now('America/Hermosillo'))) {
                return response()->json([
                    'code' => "08",
                    'message' => "La fecha de expiraciÃ³n no es vÃ¡lida.",
                    'url' => '',
                    'reference' => '',
                    'referenceEmisor' => ''
                ], 400);
            }
        } catch(Exception $e) {
            return response()->json([
                'code' => "08",
                'message' => "La fecha de expiraciÃ³n no es vÃ¡lida.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }*/

        if(!array_key_exists('Reference', $data) || !array_key_exists('Description', $data)) {
            return response()->json([
                'code' => "03",
                'message' => "Alguno de los valores no fueron establecidos.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }

        $cliente_id = 0;
        try{
            $cliente_id = $this->resolverClienteApi($data, $usuario);
        } catch(Exception $e) {
            Log::info("El aliado no pudo ser guardado y se continuarÃ¡ sin cliente: ".$e);
        }
        
        $params = array();
        $User = "";
        $Password = ""; 
        $PaymentTypes = "";
        $IntegrationID = $usuario->IntegrationID;
        $BusinessID = $usuario->BusinessID;

        if($usuario->productivo == 1) {
            $max = (Transaccion::where([['tipo', '=', '1'],['BusinessID','=',$usuario->BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', '1'],['BusinessID','=',$usuario->BusinessID],['productivo','=','1']])->max('folio') + 1);
            $User = $this->User;
            $Password = $this->Password;
            $PaymentTypes = "41";
        } else {
            $max = (Transaccion::where([['tipo', '=', '1'],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', '1'],['productivo','=','0']])->max('folio') + 1);
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
            $PaymentTypes = "401";
        }

        if($max == 0){
            return response()->json([
                'code' => "51",
                'message' => "Error interno del sistema, el folio no pudo ser asignado.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }

        // Set request params
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "PaymentTypes" => $PaymentTypes,
            "Id" => str_pad($max, 10, '0', STR_PAD_LEFT),
            "Description" => $request->Description,
            "Amount" => $amount,
            "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),
            "ExpirationDate" => $request->ExpirationDate,
        );

        $error = "";
        $error_code = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{
            $response = $this->postJsonControlado($this->urlPath, $params);
        } catch (RequestException $e){            
            Log::info("Error en la conexiÃ³n para generar la liga: ".$e);
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
            if(json_last_error() === JSON_ERROR_NONE) {
                $error = $response_decode->message;
                $error_code = $response_decode->code;
            } else {
                $error = "Error en la conexiÃ³n para generar la liga.";
                $error_code = "99";
            }
        }

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
        }

        $transaccion = new Transaccion();

        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $transaccion->fecha = $mytime->toDateTimeString();
            $transaccion->folio = $max;
            $transaccion->User = $User;
            $transaccion->Password = $Password;
            $transaccion->IntegrationID = $IntegrationID;
            $transaccion->BusinessID = $BusinessID;
            $transaccion->PaymentTypes = $PaymentTypes;
            $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
            $transaccion->Description = $request->Description;
            $transaccion->Amount = $amount;
            $transaccion->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);
            $transaccion->ExpirationDate = $request->ExpirationDate;
            $transaccion->ClientReference = $request->Reference;
            $transaccion->response = $response_body;
            $transaccion->url = $response_decode->url;
            $transaccion->code = $response_decode->code;
            $transaccion->message = $response_decode->message;
            $transaccion->responseReference = $response_decode->reference;
            $transaccion->referenceEmisor = $response_decode->referenceEmisor;
            $transaccion->tipo = 1;
            $transaccion->idusuario =  $usuario->id;
            $transaccion->productivo = $usuario->productivo;
            if($cliente_id > 0){
                $transaccion->idcliente = $cliente_id;
            }
            $transaccion->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            Log::info("Error al guardar la transacciÃ³n: ".$e);
            //Guardar interno con respuesta y mandar alerta para corregir folio
            $error = "Error interno del sistema, no se pudo guardar.";
            $error_code = "52";
        }

        if($error != ""){
            return response()->json([
                'code' => $error_code,
                'message' => $error,
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }

        return response()->json([
            'code' => $response_decode->code,
            'message' => $response_decode->message,
            'url' => $response_decode->url,
            'reference' => $response_decode->reference,
            'referenceEmisor' => $response_decode->referenceEmisor
        ], 200);
    }

    //Store generar liga domiciliaciÃ³n
    public function storeDom(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $this->validate($request,[            
            'PaymentTypes' => 'required',
            'Description' => 'required|string|max:100',
            'Amount' => 'required',            
            'ClientReference' => 'required|string|max:50',
            'ExpirationDate' => 'required'
        ],
        [            
            'PaymentTypes.required' => 'Debe ingresar el tipo de pago.',
            'Description.required' => 'Debe ingresar la descripciÃ³n.',
            'Amount.required' => 'Debe ingresar el monto.',            
            'ClientReference.required' => 'Debe ingresar la referencia del cliente.',
            'ExpirationDate.required' => 'Debe ingresar la fecha de expiraciÃ³n.'
        ]); 

        if (!$this->clienteAutorizado($request->idcliente)) {
            return $this->respuestaNoAutorizado($request);
        }
                               
        $amount = $request->Amount * 100;
        $date = Carbon::createFromFormat('Y-m-d', $request->ExpirationDate);
        $max = 0;
        $params = array();
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
            $max = (Transaccion::where([['tipo', '=', $request->tipo],['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') + 1);
            $User = $this->User;
            $Password = $this->Password;
        } else {
            $max = (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') + 1);
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
        }

        // Set request params
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "PaymentTypes" => $request->PaymentTypes,
            "Id" => str_pad($max, 10, '0', STR_PAD_LEFT),
            "Description" => $request->Description,
            "Amount" => $amount,
            "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),
            "ExpirationDate" => $date,
        );

        $error = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{        
            $response = $this->postJsonControlado($this->urlDomPath, $params);
        } catch (RequestException $e){            
            //$response  = Psr7\Message::toString($e->getRequest());
            //$response = Psr7\Message::toString($e->getResponse());
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
            Log::info("Error en la conexiÃ³n para generar la liga de domiciliaciÃ³n: ".$e);

            // Validar que la respuesta sea un json vÃ¡lido antes de asignar los valores de error
            if(json_last_error() === JSON_ERROR_NONE) {
                $error = $response_decode->message;
                $error_code = $response_decode->code;
            } else {
                $error = "Error en la conexiÃ³n";
                $error_code = "99";
            }           
        }

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
        }        

        $transaccion = new Transaccion();
        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $transaccion->fecha = $mytime->toDateTimeString();
            $transaccion->folio = $max;
            $transaccion->User = $User;
            $transaccion->Password = $Password;
            $transaccion->IntegrationID = $IntegrationID;
            $transaccion->BusinessID = $BusinessID;
            $transaccion->PaymentTypes = $request->PaymentTypes;
            $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
            $transaccion->Description = $request->Description;
            $transaccion->Amount = $amount;
            $transaccion->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);
            $transaccion->ExpirationDate = $request->ExpirationDate;
            $transaccion->ClientReference = $request->ClientReference;
            $transaccion->response = $response_body;
            $transaccion->url = $response_decode->url;
            $transaccion->code = $response_decode->code;
            $transaccion->message = $response_decode->message;
            $transaccion->responseReference = $response_decode->reference;
            $transaccion->referenceEmisor = $response_decode->referenceEmisor;
            $transaccion->idcliente = $request->idcliente;
            $transaccion->tipo = $request->tipo;
            $transaccion->frecuencia = $request->frecuencia;
            $transaccion->ProximoCargo = $request->ProximoCargo;
            $transaccion->idusuario =  \Auth::user()->id;
            $transaccion->productivo = \Auth::user()->productivo;
            $transaccion->save();            
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            $error = $e->getMessage();
        }

        return [                
            'error' => $error,
            'msg' => "El registro se realizÃ³ con Ã©xito."
        ];
    }

     //Store generar liga de pago de domiciliaciÃ³n API
     public function storeDomAPI(Request $request)
     {       
         $max = 0;                    
         $data = $request->json()->all();
         
            
         if(!array_key_exists('User', $data) || !array_key_exists('Password', $data)) {
            return response()->json([
                'code' => "02",
                'message' => "El usuario y la contraseÃ±a son obligatorios.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }

         if($data['User']=="" || $data['Password']==""){
            return response()->json([
                'code' => "02",
                'message' => "El usuario y la contraseÃ±a son obligatorios.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }
         
         $usuario = User::where([['usuario','LIKE',$data['User']],['token','LIKE',$data['Password']]])->first();
         if($usuario == null){
             return response()->json([
                 'code' => "01",
                 'message' => "El usuario no pudo ser identificado.",
                 'url' => '',
                 'reference' => '',
                 'referenceEmisor' => ''
             ], 400);
         }

         $amount = 0;
         if(!array_key_exists('Amount', $data) || !is_numeric($data['Amount'])) {
            return response()->json([
                'code' => "03",
                'message' => "El monto es obligatorio.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }
         try {
            $amount = (float) $data['Amount'] * 100;
            if($amount < 5000) {
                return response()->json([
                    'code' => "06",
                    'message' => "Importe menor al permitido.",
                    'url' => '',
                    'reference' => '',
                    'referenceEmisor' => ''
                ], 400);
            }
            if($amount > 5000000) {
                return response()->json([
                    'code' => "07",
                    'message' => "Importe mayor al permitido.",
                    'url' => '',
                    'reference' => '',
                    'referenceEmisor' => ''
                ], 400);
            }
         } catch(Exception $e) {
            return response()->json([
                'code' => "03",
                'message' => "Alguno de los valores no fueron establecidos.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }
         
         if(!array_key_exists('ExpirationDate', $data)) {
            return response()->json([
                'code' => "03",
                'message' => "La fecha de vencimiento es obligatoria.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }
         try {
            $date = Carbon::createFromFormat('Y-m-d', $data['ExpirationDate']);
         } catch(Exception $e) {
            return response()->json([
                'code' => "08",
                'message' => "La fecha de expiraciÃ³n no es vÃ¡lida.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }
         try {            
            //Comparar si la fecha ExpirationDate es menor que la actual
            if($date->lessThan(Carbon::now('America/Hermosillo'))) {
                return response()->json([
                    'code' => "08",
                    'message' => "La fecha de expiraciÃ³n no es vÃ¡lida.",
                    'url' => '',
                    'reference' => '',
                    'referenceEmisor' => ''
                ], 400);
            }
         } catch(Exception $e) {
            return response()->json([
                'code' => "08",
                'message' => "La fecha de expiraciÃ³n no es vÃ¡lida.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }

         if(!array_key_exists('Frecuencia', $data) || !array_key_exists('Reference', $data) || !array_key_exists('Description', $data)) {
            return response()->json([
                'code' => "03",
                'message' => "Alguno de los valores no fueron establecidos.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }

        $cliente_id = 0;
        try{
            $cliente_id = $this->resolverClienteApi($data, $usuario);
        } catch(Exception $e) {
            Log::info("El aliado no pudo ser guardado y se continuarÃ¡ sin cliente: ".$e);
        }
         
         $params = array();
         $User = "";
         $Password = "";                   
         $IntegrationID = "";
         $BusinessID = "";
         $PaymentTypes = "";
         $Frecuencia = "";
         $ProximoCargo = Carbon::now('America/Hermosillo');         

         if($data['Frecuencia'] == 'semanal') {
            $Frecuencia = 1;
            $ProximoCargo = $ProximoCargo->addWeek(); // AÃ±adir 1 semana
         } else if($data['Frecuencia'] == 'mensual') {
            $Frecuencia = 2;
            $ProximoCargo = $ProximoCargo->addMonth(); // AÃ±adir 1 mes
         } else if($data['Frecuencia'] == 'bimestral') { 
            $Frecuencia = 3;
            $ProximoCargo = $ProximoCargo->addMonths(2); // AÃ±adir 2 meses
         } else if($data['Frecuencia'] == 'semestral') { 
            $Frecuencia = 4;
            $ProximoCargo = $ProximoCargo->addMonths(6); // AÃ±adir 6 meses
         } else if($data['Frecuencia'] == 'anual') { 
            $Frecuencia = 5;
            $ProximoCargo = $ProximoCargo->addYear(); // AÃ±adir 1 aÃ±o
         }  else if($data['Frecuencia'] == 'personalizado') { 
            $Frecuencia = 6;
            //Agrear a $ProximoCargo la fecha que venga en la variable $data['ProximoCargo'] si existe, y si no existe responder error
            if(array_key_exists('ProximoCargo', $data) && $data['ProximoCargo'] != ""){
                $ProximoCargo = Carbon::createFromFormat('Y-m-d', $data['ProximoCargo']);
            } else {
                return response()->json([
                    'code' => "04",
                    'message' => "La frecuencia personalizada no pudo ser identificada.",
                    'url' => '',
                    'reference' => '',
                    'referenceEmisor' => ''
                ], 400);
            }
         } else { 
            return response()->json([
                'code' => "05",
                'message' => "La frecuencia no pudo ser identificada.",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }
         
         //Cambiar el BusinessID al del usuario si no es tesorerÃ­a
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
            $PaymentTypes = "41";
            $max = (Transaccion::where([['tipo', '=', '2'],['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', '2'],['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') + 1);            
         } else {
            $IntegrationID = $usuario->IntegrationID;
            $BusinessID = $usuario->BusinessID;
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
            $PaymentTypes = "401";
            $max = (Transaccion::where([['tipo', '=', '2'],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', '2'],['productivo','=','0']])->max('folio') + 1);             
         }
 
         if($max == 0){
             return response()->json([
                 'code' => "50",
                 'message' => "El folio no pudo ser asignado.",
                 'url' => '',
                 'reference' => '',
                 'referenceEmisor' => ''
             ], 400);
         }
 
         // Set request params
         $params = array(
             "User" => $User,
             "Password" => $Password,
             "IntegrationID" => $IntegrationID,
             "BusinessID" => $BusinessID,
             "PaymentTypes" => $PaymentTypes,
             "Id" => str_pad($max, 10, '0', STR_PAD_LEFT),
             "Description" => $data['Description'],
             "Amount" => $amount,
             "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),
             "ExpirationDate" => $date,
         );
 
         $error = "";
         $error_code = "";
         $response = "";
         $response_body = "";
         $response_decode = "";
 
         try{
             $response = $this->postJsonControlado($this->urlDomPath, $params);
         } catch (RequestException $e){            
             $response  = $e->getResponse();
             $response_body = (string) $response->getBody();
             $response_decode = json_decode($response_body);
             if(json_last_error() === JSON_ERROR_NONE) {
                 $error = $response_decode->message;
                 $error_code = $response_decode->code;
             } else {
                 $error = "Error en la conexiÃ³n";
                 $error_code = "50";
             }
         }
 
         if($response_decode == "") {
             $response_body = (string) $response->getBody();
             $response_decode = json_decode($response_body);
         }
 
         $transaccion = new Transaccion();
 
         try{
             DB::beginTransaction();
             $mytime = Carbon::now('America/Hermosillo');
             $transaccion->fecha = $mytime->toDateTimeString();
             $transaccion->folio = $max;
             $transaccion->User = $User;
             $transaccion->Password = $Password;
             $transaccion->IntegrationID = $IntegrationID;
             $transaccion->BusinessID = $BusinessID;
             $transaccion->PaymentTypes = $PaymentTypes;
             $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
             $transaccion->Description = $data['Description'];
             $transaccion->Amount = $amount;
             $transaccion->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);
             $transaccion->ExpirationDate = $request->ExpirationDate;
             $transaccion->ClientReference = $data['Reference'];
             $transaccion->response = $response_body;
             $transaccion->url = $response_decode->url;
             $transaccion->code = $response_decode->code;
             $transaccion->message = $response_decode->message;
             $transaccion->responseReference = $response_decode->reference;
             $transaccion->referenceEmisor = $response_decode->referenceEmisor;
             $transaccion->tipo = 2;
             $transaccion->frecuencia = $Frecuencia;
             $transaccion->ProximoCargo = $ProximoCargo;
             $transaccion->idusuario =  $usuario->id;
             $transaccion->productivo = $usuario->productivo;
             //Si el objecto $cliente se creo correctamente se asigna el id del cliente a la variable $transaccion->idcliente
             if($cliente_id > 0){
                $transaccion->idcliente = $cliente_id;
             }
             $transaccion->save();
             DB::commit();
         } catch (Exception $e){
             DB::rollBack();
             //Guardar interno con respuesta y mandar alerta para corregir folio
             $error = "Error interno del sistema.";
             $error_code = "50";
         }
 
         if($error != ""){
             return response()->json([
                 'code' => $error_code,
                 'message' => $error,
                 'url' => '',
                 'reference' => '',
                 'referenceEmisor' => ''
             ], 400);
         }
 
         return response()->json([
             'code' => $response_decode->code,
             'message' => $response_decode->message,
             'url' => $response_decode->url,
             'reference' => $response_decode->reference,
             'referenceEmisor' => $response_decode->referenceEmisor
         ], 200);
     }

    public function storeSpei(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $this->validate($request,[
            'idcliente' => 'required',
            'Description' => 'required|string|max:100',
            'Amount' => 'required',            
            'ClientReference' => 'required|string|max:50',
            'ExpirationDate' => 'required'
        ],
        [            
            'idcliente.required' => 'El cliente es requerido.',
            'Description.required' => 'Debe ingresar la descripciÃ³n.',
            'Amount.required' => 'Debe ingresar el monto.',
            'ClientReference.required' => 'Debe ingresar la referencia del cliente.',
            'ExpirationDate.required' => 'Debe ingresar la fecha de expiraciÃ³n.'
        ]); 

        if (!$this->clienteAutorizado($request->idcliente)) {
            return $this->respuestaNoAutorizado($request);
        }
                               
        $amount = $request->Amount * 100;
        $date = Carbon::createFromFormat('Y-m-d', $request->ExpirationDate);
        $cliente = Cliente::find($request->idcliente);
        $persona = Persona::find($request->idcliente);
        $nombre = $cliente->razon_social;
        $email = $persona->email;

        $max = 0;
        $params = array();

        $User = "";
        $Password = "";
        $IntegrationID = \Auth::user()->IntegrationID;
        $BusinessID = \Auth::user()->BusinessID;

        if(\Auth::user()->productivo == 1) {
            $max = (Transaccion::where([['tipo', '=', $request->tipo],['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') + 1);
            $User = $this->User;
            $Password = $this->Password;
        } else {
            $max = (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') + 1);
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
        }

        // Set request params
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "Description" => $request->Description,
            "Account" => str_pad($max, 15, '0', STR_PAD_LEFT),
            "CustomerEmail" => $email,
            "CustomerName" => $nombre,
            "ExpirationDate" => $date,
        );

        $error = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{        
            $response = $this->postJsonControlado($this->urlSpeiPath, $params);
        } catch (RequestException $e){            
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
            $error = "Error al consultar servicio de generaciÃ³n de CLABE.";
            $error_code = "55";
        }

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
        }        

        $transaccion = new Transaccion();

        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $transaccion->fecha = $mytime->toDateTimeString();
            $transaccion->folio = $max;
            $transaccion->User = $User;
            $transaccion->Password = $Password;
            $transaccion->IntegrationID = $IntegrationID;
            $transaccion->BusinessID = $BusinessID;
            $transaccion->PaymentTypes = "0";
            $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
            $transaccion->Description = $request->Description;
            $transaccion->Amount = $amount;
            $transaccion->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);
            $transaccion->ExpirationDate = $request->ExpirationDate;
            $transaccion->ClientReference = $request->ClientReference;
            $transaccion->response = $response_body;
            $transaccion->message = $response_decode->Message;
            $transaccion->responseReference = $response_decode->Folio;
            $transaccion->referenceEmisor = $response_decode->Account;
            $transaccion->Error = $response_decode->Error;
            $transaccion->Date = $response_decode->Date;
            $transaccion->Clabe = $response_decode->Clabe;
            $transaccion->idcliente = $request->idcliente;
            $transaccion->tipo = $request->tipo;
            $transaccion->idusuario =  \Auth::user()->id;
            $transaccion->productivo = \Auth::user()->productivo;
            $transaccion->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            $error = $e->getMessage();
        }

        if($response_decode->Error != null) {
            $error_code = $response_decode->Error;
            $error = $response_decode->Message;
         }

        return [
            'error' => $error,
            'msg' => "El registro se realizÃ³ con Ã©xito."
        ];
    }

     //Store generar liga de pago API
     public function storeSpeiAPI(Request $request)
     {       
         $max = 0;                    
         $data = $request->json()->all();
         $email = "";
         if(array_key_exists('Email', $data) && $data['Email'] != "") {
            $email = trim($data['Email']);
         } else if(array_key_exists('email', $data) && $data['email'] != "") {
            $email = trim($data['email']);
         }

         if($email == "") {
            return response()->json([
                'code' => "50",
                'message' => "No se recibiÃ³ correo electrÃ³nico. ",
                'url' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
         }
         if(!$this->apiTieneCredenciales($data)) {
            return $this->apiLigaError("02", "El usuario y la contraseÃ±a son obligatorios.");
         }
         if(!array_key_exists('Amount', $data) || !is_numeric($data['Amount'])) {
            return $this->apiLigaError("03", "El monto es obligatorio.");
         }
         if(!$this->apiTieneCampos($data, ['ExpirationDate'])) {
            return $this->apiLigaError("03", "La fecha de vencimiento es obligatoria.");
         }
         if(!$this->apiTieneCampos($data, ['Reference', 'Description'])) {
            return $this->apiLigaError("03", "Alguno de los valores no fueron establecidos.");
         }

         try {
            $amount = (float) $data['Amount'] * 100;
            $date = Carbon::createFromFormat('Y-m-d', $data['ExpirationDate']);
         } catch(Exception $e) {
            return $this->apiLigaError("03", "Alguno de los valores no fueron establecidos.");
         }
         $date = $date->addDays(1);
         $usuario = User::where([['usuario','LIKE',$data['User']],['token','LIKE',$data['Password']]])->first();         

         if($usuario == null){
             return response()->json([
                 'code' => "50",
                 'message' => "Error interno del sistema, el usuario no pudo ser identificado. ",
                 'url' => '',
                 'reference' => '',
                 'referenceEmisor' => ''
             ], 400);
         }
 
         $cliente_id = 0;
         try{
            $cliente_id = $this->resolverClienteApi($data, $usuario);
         } catch(Exception $e) {
            Log::info("El aliado no pudo ser guardado y se continuarÃ¡ sin cliente: ".$e);
         }

         $params = array();
         $User = "";
         $Password = ""; 
         $PaymentTypes = "";
         $IntegrationID = $usuario->IntegrationID;
         $BusinessID = $usuario->BusinessID;
 
         if($usuario->productivo == 1) {
             $max = (Transaccion::where([['tipo', '=', '3'],['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', '3'],['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') + 1);
             $User = $this->User;
             $Password = $this->Password;             
         } else {
             $max = (Transaccion::where([['tipo', '=', '3'],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', '3'],['productivo','=','0']])->max('folio') + 1);
             $User = $this->UserSandbox;
             $Password = $this->PasswordSandbox;             
         }
 
         if($max == 0){
             return response()->json([
                 'code' => "50",
                 'message' => "Error interno del sistema, el folio no pudo ser asignado.",
                 'url' => '',
                 'reference' => '',
                 'referenceEmisor' => ''
             ], 400);
         }
 
        // Set request params
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "Description" => $request->Description,
            "Account" => str_pad($max, 15, '0', STR_PAD_LEFT),
            "CustomerEmail" => $email,
            "CustomerName" => '',
            "ExpirationDate" => $date,
        );

         $error = "";
         $error_code = "";
         $response = "";
         $response_body = "";
         $response_decode = "";
 
         try{
             $response = $this->postJsonControlado($this->urlSpeiPath, $params);
         } catch (RequestException $e){    
             Log::info('Error CLABE'.$e->getMessage());
             $response  = $e->getResponse();
             $response_body = (string) $response->getBody();
             $response_decode = json_decode($response_body);
             if(json_last_error() === JSON_ERROR_NONE) {
                $error = $response_decode->message;
                $error_code = $response_decode->code;
            } else {
                $error = "Error en la conexiÃ³n del servicio para generaciÃ³n de CLABE.";
                $error_code = "55";
            }             
         }
 
         if($response_decode == "") {
             $response_body = (string) $response->getBody();
             $response_decode = json_decode($response_body);
         }
 
         $transaccion = new Transaccion();
 
         try{
             DB::beginTransaction();
             $mytime= Carbon::now('America/Hermosillo');
             $transaccion->fecha = $mytime->toDateTimeString();
             $transaccion->folio = $max;
             $transaccion->User = $User;
             $transaccion->Password = $Password;
             $transaccion->IntegrationID = $IntegrationID;
             $transaccion->BusinessID = $BusinessID;
             $transaccion->PaymentTypes = "0";
             $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
             $transaccion->Description = $request->Description;
             $transaccion->Amount = $amount;
             $transaccion->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);
             $transaccion->ExpirationDate = $date->toDateString();
             //$transaccion->ExpirationDate = '2023-04-01';
             $transaccion->ClientReference = $request->Reference;
             $transaccion->response = $response_body;
             $transaccion->message = $response_decode->Message;
             $transaccion->responseReference = $response_decode->Folio;
             $transaccion->referenceEmisor = $response_decode->Account;
             $transaccion->Error = $response_decode->Error;
             $transaccion->Date = $response_decode->Date;
             $transaccion->Clabe = $response_decode->Clabe;
             $transaccion->tipo = 3;
             $transaccion->idusuario =  $usuario->id;
             $transaccion->productivo = $usuario->productivo;
             if($cliente_id > 0){
                $transaccion->idcliente = $cliente_id;
             }
             $transaccion->save();
             DB::commit();
         } catch (Exception $e){
             DB::rollBack();
             //Guardar interno con respuesta y mandar alerta para corregir folio
             $error = "Error interno del sistema, favor de reportar al administrador.";
             $error_code = "50";
         }

         if($response_decode->Error != null) {
            $error_code = $response_decode->Error;
            $error = $response_decode->Message;
         }
 
         if($error != ""){
             return response()->json([
                 'code' => $error_code,
                 'message' => $error,
                 'clabe' => '',
                 'reference' => $response_decode->Folio,
                 'referenceEmisor' => $response_decode->Account
             ], 400);
         }
 
         return response()->json([
             'code' => 'success',
             'message' => $response_decode->Message,
             'clabe' => $response_decode->Clabe,
             'reference' => $response_decode->Folio,
             'referenceEmisor' => $response_decode->Account
         ], 200);
     }

    public function storePublic(Request $request)
    {        
        $this->validate($request,[            
            'PaymentTypes' => 'required',
            'Description' => 'required|string|max:100',
            'Amount' => 'required',
            'Reference' => 'required|string|max:15',
            'ClientReference' => 'required|string|max:50',
            'ExpirationDate' => 'required'
        ],
        [            
            'PaymentTypes.required' => 'Debe ingresar el tipo de pago.',
            'Description.required' => 'Debe ingresar la descripciÃ³n.',
            'Amount.required' => 'Debe ingresar el monto.',
            'Reference.required' => 'Debe ingresar la referencia.',
            'ClientReference.required' => 'Debe ingresar la referencia del cliente.',
            'ExpirationDate.required' => 'Debe ingresar la fecha de expiraciÃ³n.'
        ]);                  
        
        $max = (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') + 1);

        $date = Carbon::createFromFormat('Y-m-d', $request->ExpirationDate);

        // Set request params
        $params = array(
            "User" => $this->UserSandbox,
            "Password" => $this->PasswordSandbox,
            "IntegrationID" => $this->IntegrationID,
            "BusinessID" => $this->BusinessID,
            "PaymentTypes" => $request->PaymentTypes,
            "Id" => str_pad($max, 10, '0', STR_PAD_LEFT),
            "Description" => $request->Description,
            "Amount" => $request->Amount,
            "Reference" => str_pad($request->Reference, 15, '0', STR_PAD_LEFT),
            "ExpirationDate" => $date,
        );

        $error = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        /*try{*/
            $client = new Client();
            $response =  $client->request('POST', $this->urlPath, [RequestOptions::JSON => $params]);            
        /*} catch (RequestException $e){
            //$response  = Psr7\Message::toString($e->getRequest());
            //$response = Psr7\Message::toString($e->getResponse());
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
            $error = $response_decode->message;
        }*/

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
        }

        $transaccion = new Transaccion();

        try{
            DB::beginTransaction();                
            $mytime= Carbon::now('America/Hermosillo');
            $transaccion->fecha = $mytime->toDateTimeString();
            $transaccion->folio = $max;
            $transaccion->User = $this->UserSandbox;
            $transaccion->Password = $this->PasswordSandbox;
            $transaccion->IntegrationID = $this->IntegrationID;
            $transaccion->BusinessID = $this->BusinessID;
            $transaccion->PaymentTypes = $request->PaymentTypes;
            $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
            $transaccion->Description = $request->Description;
            $transaccion->Amount = $request->Amount;
            $transaccion->Reference = $request->Reference;
            $transaccion->ExpirationDate = $request->ExpirationDate;
            $transaccion->ClientReference = $request->ClientReference;
            $transaccion->response = $response_body;
            $transaccion->url = $response_decode->url;
            $transaccion->code = $response_decode->code;
            $transaccion->message = $response_decode->message;
            $transaccion->responseReference = $response_decode->reference;
            $transaccion->referenceEmisor = $response_decode->referenceEmisor;            
            $transaccion->idcliente = 1;
            $transaccion->tipo = 1;
            $transaccion->idusuario = 1;
            $transaccion->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            $error = $e->getMessage();
        }
              
        if(!$transaccion->id){
            return back()
            ->with('error', "Hubo un error al registrar la transacciÃ³n, favor de consultar con el proveedor. ".$error)
            ->withErrors(['transaccion' => trans('auth.failed')])
            ->withInput(request(['transaccion']));
         }else{
            if($response_decode->code == "success") {
                return redirect()->back()
                ->with('message', 'Su registro se ha realizado con Ã©xito con refencia '.$transaccion->Reference.'.');
            }            
         }   
         
         return back()
         ->with('error', $error)
         ->withErrors(['transaccion' => trans('auth.failed')])
         ->withInput(request(['transaccion']));
    }

    public function storeLector(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $this->validate($request,[            
            'Description' => 'required|string|max:100',
            'Amount' => 'required',            
            'ClientReference' => 'required|string|max:50'            
        ],
        [            
            'Description.required' => 'Debe ingresar la descripciÃ³n.',
            'Amount.required' => 'Debe ingresar el monto.',            
            'ClientReference.required' => 'Debe ingresar la referencia del cliente.'            
        ]); 

        if (!$this->clienteAutorizado($request->idcliente)) {
            return $this->respuestaNoAutorizado($request);
        }
                               
        $amount = $request->Amount * 100;        

        $max = 0;
        $params = array();

        $User = "";
        $Password = ""; 
        $IntegrationID = \Auth::user()->IntegrationID;
        $BusinessID = \Auth::user()->BusinessID;

        if(\Auth::user()->productivo == 1) {
            $max = (Transaccion::where([['tipo', '=', $request->tipo],['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') + 1);

            $User = $this->User;
            $Password = $this->Password;
        } else {
            $max = (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', $request->tipo],['productivo','=','0']])->max('folio') + 1);

            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
        }

        // Set request params
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,            
            "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),
            "Amount" => $amount,
            "Description" => $request->Description
        );

        $error = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{        
            $response = $this->postJsonControlado($this->urlLectorPath, $params);
        } catch (RequestException $e){            
            //$response  = Psr7\Message::toString($e->getRequest());
            //$response = Psr7\Message::toString($e->getResponse());
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
            $error = $response_decode->message;
        }

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
        }        

        $transaccion = new Transaccion();

        try{
            DB::beginTransaction();                

            $mytime= Carbon::now('America/Hermosillo');                        

            $transaccion->fecha = $mytime->toDateTimeString();
            $transaccion->folio = $max;
            $transaccion->User = $User;
            $transaccion->Password = $Password;
            $transaccion->IntegrationID = $IntegrationID;
            $transaccion->BusinessID = $BusinessID;
            $transaccion->PaymentTypes = "0";
            $transaccion->ExpirationDate = $mytime->toDateString();
            $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
            $transaccion->Description = $request->Description;
            $transaccion->Amount = $amount;
            $transaccion->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);
            $transaccion->ClientReference = $request->ClientReference;
            $transaccion->response = $response_body;
            $transaccion->codeQR = $response_decode->codeQR;
            $transaccion->code = $response_decode->code;
            $transaccion->message = $response_decode->message;
            $transaccion->responseReference = $response_decode->reference;
            $transaccion->referenceEmisor = $response_decode->referenceEmisor;            
            $transaccion->idcliente = $request->idcliente;
            $transaccion->tipo = $request->tipo;
            $transaccion->idusuario =  \Auth::user()->id;
            $transaccion->productivo = \Auth::user()->productivo;
            $transaccion->save();
            
            DB::commit();

        } catch (Exception $e){
            DB::rollBack();
            $error = $e->getMessage();
        }

        return [                
            'error' => $error,
            'msg' => "El registro se realizÃ³ con Ã©xito."
        ];
    }

    //Store generar liga de pago API
    public function storeLectorAPI(Request $request)
    {
        $max = 0;                    
        $data = $request->json()->all();

        if(!$this->apiTieneCredenciales($data)) {
            return $this->apiLectorError("02", "El usuario y la contraseÃ±a son obligatorios.");
        }
        if(!array_key_exists('Amount', $data) || !is_numeric($data['Amount'])) {
            return $this->apiLectorError("03", "El monto es obligatorio.");
        }
        if(!$this->apiTieneCampos($data, ['Reference', 'Description'])) {
            return $this->apiLectorError("03", "Alguno de los valores no fueron establecidos.");
        }

        $amount = (float) $data['Amount'] * 100;        
        $usuario = User::where([['usuario','LIKE',$data['User']],['token','LIKE',$data['Password']]])->first();

        if($usuario == null){
            return response()->json([
                'code' => "50",
                'message' => "Error interno del sistema, el usuario no pudo ser identificado. ",
                'codeQR' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }

        $params = array();
        $User = "";
        $Password = ""; 
        $PaymentTypes = "";
        $IntegrationID = $usuario->IntegrationID;
        $BusinessID = $usuario->BusinessID;

        if($usuario->productivo == 1) {
            $max = (Transaccion::where([['tipo', '=', '1'],['BusinessID','=',$usuario->BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', '1'],['BusinessID','=',$usuario->BusinessID],['productivo','=','1']])->max('folio') + 1);
            $User = $this->User;
            $Password = $this->Password;            
        } else {
            $max = (Transaccion::where([['tipo', '=', '1'],['productivo','=','0']])->max('folio') == null) ? 1 : (Transaccion::where([['tipo', '=', '1'],['productivo','=','0']])->max('folio') + 1);
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;            
        }

        if($max == 0){
            return response()->json([
                'code' => "50",
                'message' => "Error interno del sistema, el folio no pudo ser asignado.",
                'codeQR' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }

        // Set request params
        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "Reference" => str_pad($max, 15, '0', STR_PAD_LEFT),            
            "Amount" => $amount,
            "Description" => $request->Description            
        );

        $error = "";
        $error_code = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{
            $response = $this->postJsonControlado($this->urlLectorPath, $params);
        } catch (RequestException $e){            
            $response  = $e->getResponse();
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
            if(json_last_error() === JSON_ERROR_NONE) {
                $error = $response_decode->message;
                $error_code = $response_decode->code;
            } else {
                $error = "Error en la conexiÃ³n";
                $error_code = "99";
            }
        }

        if($response_decode == "") {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
        }

        $transaccion = new Transaccion();

        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $transaccion->fecha = $mytime->toDateTimeString();
            $transaccion->folio = $max;
            $transaccion->User = $User;
            $transaccion->Password = $Password;
            $transaccion->IntegrationID = $IntegrationID;
            $transaccion->BusinessID = $BusinessID;
            $transaccion->PaymentTypes = "0";
            $transaccion->ExpirationDate = $mytime->toDateString();
            $transaccion->IdReference = str_pad($max, 10, '0', STR_PAD_LEFT);
            $transaccion->Description = $request->Description;
            $transaccion->Amount = $amount;
            $transaccion->Reference = str_pad($max, 15, '0', STR_PAD_LEFT);            
            $transaccion->ClientReference = $request->Reference;
            $transaccion->response = $response_body;
            $transaccion->codeQR = $response_decode->codeQR;
            $transaccion->code = $response_decode->code;
            $transaccion->message = $response_decode->message;
            $transaccion->responseReference = $response_decode->reference;
            $transaccion->referenceEmisor = $response_decode->referenceEmisor;
            $transaccion->tipo = 4;
            $transaccion->idusuario =  $usuario->id;
            $transaccion->productivo = $usuario->productivo;
            $transaccion->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            //Guardar interno con respuesta y mandar alerta para corregir folio
            $error = "Error interno del sistema.";
            $error_code = "50";
        }

        if($error != ""){
            return response()->json([
                'code' => $error_code,
                'message' => $error,
                'codeQR' => '',
                'reference' => '',
                'referenceEmisor' => ''
            ], 400);
        }

        return response()->json([
            'code' => $response_decode->code,
            'message' => $response_decode->message,
            'codeQR' => $response_decode->reference,
            'reference' => $response_decode->reference,
            'referenceEmisor' => $response_decode->referenceEmisor
        ], 200);
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

        $transaccion = Transaccion::findOrFail($request->id);
        if (!$this->usuarioPuedeOperarRegistro($transaccion)) {
            return $this->respuestaNoAutorizado($request);
        }
        if (!$this->clienteAutorizado($request->idcliente)) {
            return $this->respuestaNoAutorizado($request);
        }
        $transaccion->ClientReference = $request->ClientReference;
        $transaccion->idcliente = $request->idcliente;
        $transaccion->save();
    }
    
    public function delete(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $transaccion = Transaccion::findOrFail($request->id);
        if (!$this->usuarioPuedeOperarRegistro($transaccion)) {
            return $this->respuestaNoAutorizado($request);
        }
        $transaccion->delete();
    }

    public function desactivar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $transaccion = Transaccion::findOrFail($request->id);
        if (!$this->usuarioPuedeOperarRegistro($transaccion)) {
            return $this->respuestaNoAutorizado($request);
        }
        $transaccion->condicion = '0';
        $transaccion->save();
    }

    public function activar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $transaccion = Transaccion::findOrFail($request->id);
        if (!$this->usuarioPuedeOperarRegistro($transaccion)) {
            return $this->respuestaNoAutorizado($request);
        }
        $transaccion->condicion = '1';        
        $transaccion->save();
        //Mail::to($transaccion->IdReference)->send(new TransaccionValidado($transaccion));
    }

    public function rechazar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $transaccion = Transaccion::findOrFail($request->id);
        if (!$this->usuarioPuedeOperarRegistro($transaccion)) {
            return $this->respuestaNoAutorizado($request);
        }

        if($transaccion->tipo == 2) {
            $respuesta = Respuesta::where([
                ['idtransaccion', '=', $request->id],
                ['status','LIKE','approved']])
                ->first();
                    
            $Token = $respuesta->number_tkn;        
    
            $max = 0;
            $params = array();
    
            $User = "";
            $Password = ""; 
            $IntegrationID = \Auth::user()->IntegrationID;
            $BusinessID = \Auth::user()->BusinessID;
    
            if(\Auth::user()->productivo == 1) {
                $max = (CancelacionDom::where([['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (CancelacionDom::where([['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') + 1);
                $User = $this->User;
                $Password = $this->Password;
            } else {
                $max = (CancelacionDom::where('productivo','=','0')->max('folio') == null) ? 1 : (CancelacionDom::where('productivo','=','0')->max('folio') + 1);
                $User = $this->UserSandbox;
                $Password = $this->PasswordSandbox;
            }
    
            // Set request params
            $params = array(
                "User" => $User,
                "Password" => $Password,
                "IntegrationID" => $IntegrationID,
                "BusinessID" => $BusinessID,
                "Token" => $Token,
                "Tkn_reference" => str_pad($max, 15, '0', STR_PAD_LEFT),  
            );
    
            $error = "";
            $response = "";
            $response_body = "";
            $response_decode = "";
    
            try{        
                $client = new Client();
                $response =  $client->request('POST', $this->urlDomCancel, [RequestOptions::JSON => $params]);            
            } catch (RequestException $e){            
                //$response  = Psr7\Message::toString($e->getRequest());
                //$response = Psr7\Message::toString($e->getResponse());
                $response  = $e->getResponse();
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body);
                $error = $response_decode->message;
            }
    
            if($response_decode == "") {
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body);
            }        
    
            try{            
                DB::beginTransaction();                
                $mytime = Carbon::now('America/Hermosillo');            
                $transaccion->condicion = '2';
                $transaccion->save();
                $cancelaciondom = new CancelacionDom();
                $cancelaciondom->fecha = $mytime->toDateTimeString();
                $cancelaciondom->folio = $max;
                $cancelaciondom->User = $User;
                $cancelaciondom->Password = $Password;
                $cancelaciondom->IntegrationID = $IntegrationID;
                $cancelaciondom->BusinessID = $BusinessID;
                $cancelaciondom->Token = $Token;
                $cancelaciondom->Tkn_reference = str_pad($max, 15, '0', STR_PAD_LEFT);
                $cancelaciondom->response = $response_body;
                $cancelaciondom->code = $response_decode->code;
                $cancelaciondom->message = $response_decode->message;
                $cancelaciondom->idusuario =  \Auth::user()->id;
                $cancelaciondom->productivo = \Auth::user()->productivo;
                $cancelaciondom->save();        
                DB::commit();
            } catch (Exception $e){
                DB::rollBack();
                $error = $e->getMessage();
            }
        } else if($transaccion->tipo == 4) {

            $transaccion = Transaccion::findOrFail($request->id);

            $max = 0;
            $params = array();
    
            $User = "";
            $Password = ""; 
            $IntegrationID = \Auth::user()->IntegrationID;
            $BusinessID = \Auth::user()->BusinessID;
            $Reference = $transaccion->responseReference;
    
            if(\Auth::user()->productivo == 1) {
                $max = (CancelacionLector::where([['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (CancelacionLector::where([['BusinessID','=',\Auth::user()->BusinessID],['productivo','=','1']])->max('folio') + 1);
                $User = $this->User;
                $Password = $this->Password;
            } else {
                $max = (CancelacionLector::where('productivo','=','0')->max('folio') == null) ? 1 : (CancelacionLector::where('productivo','=','0')->max('folio') + 1);
                $User = $this->UserSandbox;
                $Password = $this->PasswordSandbox;
            }
    
            // Set request params
            $params = array(
                "User" => $User,
                "Password" => $Password,
                "IntegrationID" => $IntegrationID,
                "BusinessID" => $BusinessID,
                "Reference" => $Reference
            );
    
            $error = "";
            $response = "";
            $response_body = "";
            $response_decode = "";
    
            try{        
                $client = new Client();
                $response =  $client->request('POST', $this->urlLectorCancel, [RequestOptions::JSON => $params]);            
            } catch (RequestException $e){            
                //$response  = Psr7\Message::toString($e->getRequest());
                //$response = Psr7\Message::toString($e->getResponse());
                $response  = $e->getResponse();
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body);
                $error = $response_decode->message;
            }
    
            if($response_decode == "") {
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body);
            }        
    
            try{            
                DB::beginTransaction();                
                $mytime = Carbon::now('America/Hermosillo');            
                $transaccion->condicion = '2';
                $transaccion->save();
                $cancelacionlector = new CancelacionLector();
                $cancelacionlector->fecha = $mytime->toDateTimeString();
                $cancelacionlector->folio = $max;
                $cancelacionlector->User = $User;
                $cancelacionlector->Password = $Password;
                $cancelacionlector->IntegrationID = $IntegrationID;
                $cancelacionlector->BusinessID = $BusinessID;
                $cancelacionlector->Reference = $Reference;
                $cancelacionlector->response = $response_body;
                $cancelacionlector->code = $response_decode->code;
                $cancelacionlector->message = $response_decode->message;
                $cancelacionlector->responseReference = $response_decode->reference;
                $cancelacionlector->idusuario =  \Auth::user()->id;
                $cancelacionlector->productivo = \Auth::user()->productivo;
                $cancelacionlector->save();
                DB::commit();
            } catch (Exception $e){
                DB::rollBack();
                $error = $e->getMessage();
            }
        }

        return [                
            'error' => $error,
            'msg' => "La cancelaciÃ³n se realizÃ³ con Ã©xito."
        ];

    }

    public function cancelarDomAPI(Request $request)
    {        
        $data = $request->json()->all();

        if(!$this->apiTieneCredenciales($data)) {
            return $this->apiCancelacionDomError("02", "El usuario y la contraseÃ±a son obligatorios.");
        }

        if(!$this->apiTieneCampos($data, ['ClientReference']) && !$this->apiTieneCampos($data, ['Token'])) {
            return $this->apiCancelacionDomError("03", "Debe ingresar la referencia del cliente o el token de domiciliaciÃ³n.");
        }

        $usuario = User::where([['usuario','LIKE',$data['User']],['token','LIKE',$data['Password']]])->first();
        if($usuario == null){
            return $this->apiCancelacionDomError("50", "Error interno del sistema, el usuario no pudo ser identificado.");
        }

        $transaccion = null;
        $respuesta = null;
        $Token = $data['Token'] ?? '';

        if($Token == '' && array_key_exists('ClientReference', $data)) {
            $transaccion = Transaccion::where([
                ['ClientReference','LIKE',$data['ClientReference']],
                ['tipo','=','2'],
                ['idusuario','=',$usuario->id]
            ])->first();

            if($transaccion == null){
                return $this->apiCancelacionDomError("51", "La transacciÃ³n no pudo ser identificada.");
            }

            $respuesta = Respuesta::where([
                ['idtransaccion', '=', $transaccion->id],
                ['status','LIKE','approved']
            ])->first();

            if($respuesta == null || $respuesta->number_tkn == ''){
                return $this->apiCancelacionDomError("52", "La respuesta aprobada no pudo ser identificada.");
            }

            $Token = $respuesta->number_tkn;
        }

        $max = 0;
        $User = "";
        $Password = "";
        $IntegrationID = "";
        $BusinessID = "";

        if($usuario->productivo == 1) {
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

            $max = (CancelacionDom::where([['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') == null) ? 1 : (CancelacionDom::where([['BusinessID','=',$BusinessID],['productivo','=','1']])->max('folio') + 1);
        } else {
            $IntegrationID = $usuario->IntegrationID;
            $BusinessID = $usuario->BusinessID;
            $User = $this->UserSandbox;
            $Password = $this->PasswordSandbox;
            $max = (CancelacionDom::where('productivo','=','0')->max('folio') == null) ? 1 : (CancelacionDom::where('productivo','=','0')->max('folio') + 1);
        }

        if($max == 0){
            return $this->apiCancelacionDomError("53", "El folio no pudo ser asignado.");
        }

        $params = array(
            "User" => $User,
            "Password" => $Password,
            "IntegrationID" => $IntegrationID,
            "BusinessID" => $BusinessID,
            "Token" => $Token,
            "Tkn_reference" => str_pad($max, 15, '0', STR_PAD_LEFT),
        );

        $error = "";
        $error_code = "";
        $response = "";
        $response_body = "";
        $response_decode = "";

        try{        
            $response = $this->postJsonControlado($this->urlDomCancel, $params);
        } catch (RequestException $e){            
            Log::info("Error en la conexiÃ³n para cancelar domiciliaciÃ³n: ".$e->getMessage());
            $response = $e->getResponse();
            if($response) {
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body);
                if(json_last_error() === JSON_ERROR_NONE) {
                    $error = $response_decode->message ?? $response_decode->Message ?? "Error al consultar servicio de cancelaciÃ³n.";
                    $error_code = $response_decode->code ?? $response_decode->Code ?? "54";
                } else {
                    $error = "Error al consultar servicio de cancelaciÃ³n.";
                    $error_code = "54";
                }
            } else {
                $error = "Error al consultar servicio de cancelaciÃ³n.";
                $error_code = "54";
            }
        }

        if($response_decode == "" && $response) {
            $response_body = (string) $response->getBody();
            $response_decode = json_decode($response_body);
        }        

        if($response_decode == "") {
            return $this->apiCancelacionDomError($error_code ?: "54", $error ?: "Error al consultar servicio de cancelaciÃ³n.");
        }

        try{
            DB::beginTransaction();
            $mytime = Carbon::now('America/Hermosillo');

            if($transaccion != null) {
                $transaccion->condicion = '2';
                $transaccion->save();
            }

            $cancelaciondom = new CancelacionDom();
            $cancelaciondom->fecha = $mytime->toDateTimeString();
            $cancelaciondom->folio = $max;
            $cancelaciondom->User = $User;
            $cancelaciondom->Password = $Password;
            $cancelaciondom->IntegrationID = $IntegrationID;
            $cancelaciondom->BusinessID = $BusinessID;
            $cancelaciondom->Token = $Token;
            $cancelaciondom->Tkn_reference = str_pad($max, 15, '0', STR_PAD_LEFT);
            $cancelaciondom->response = $response_body;
            $cancelaciondom->code = $response_decode->code ?? $response_decode->Code ?? null;
            $cancelaciondom->message = $response_decode->message ?? $response_decode->Message ?? null;
            $cancelaciondom->idusuario = $usuario->id;
            $cancelaciondom->productivo = $usuario->productivo;
            $cancelaciondom->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
            Log::info("Error al guardar cancelaciÃ³n de domiciliaciÃ³n API: ".$e->getMessage());
            return $this->apiCancelacionDomError("55", "No se pudo guardar la respuesta de cancelaciÃ³n.");
        }

        if($error != "") {
            return $this->apiCancelacionDomError($error_code ?: "54", $error);
        }

        return response()->json([
            'code' => $response_decode->code ?? $response_decode->Code ?? 'success',
            'message' => $response_decode->message ?? $response_decode->Message ?? 'CancelaciÃ³n registrada.',
            'reference' => str_pad($max, 15, '0', STR_PAD_LEFT)
        ], 200);
    }

    public function consultaClabe(Request $request)
    {        
        $consultaspei = new ConsultaSpei();

        if(empty($request->r)){
            try{
                DB::beginTransaction();
                $mytime= Carbon::now('America/Hermosillo');
                $consultaspei->fecha = $mytime->toDateTimeString();
                $consultaspei->reference = $request->r;
                $consultaspei->codigo = "50";
                $consultaspei->mensaje = "Error de sistema";
                $consultaspei->save();
                DB::commit();
            } catch (Exception $e){
                DB::rollBack();            
            }
            return response()->json([
                'codigo' => '50',
                'mensaje' => 'Error de sistema',
                'monto' => "",
                'clabe' => "",
                'transaccion' => "",
                'parcial' => 0

            ], 200);                        
        }

        if(strlen($request->r)!=18){
            try{
                DB::beginTransaction();
                $mytime= Carbon::now('America/Hermosillo');
                $consultaspei->fecha = $mytime->toDateTimeString();
                $consultaspei->reference = $request->r;
                $consultaspei->codigo = "15";
                $consultaspei->mensaje = "Referencia con error de formato";
                $consultaspei->save();
                DB::commit();
            } catch (Exception $e){
                DB::rollBack();            
            }
            return response()->json([
                'codigo' => '15',
                'mensaje' => 'Referencia con error de formato',
                'monto' => "",
                'clabe' => "",
                'transaccion' => "",
                'parcial' => 0

            ], 200);                        
        }

        try {
            $transaccion = Transaccion::where('Clabe', '=', $request->r)
                ->orderBy('created_at', 'desc')->first();

            if($transaccion == null){
                try{
                    DB::beginTransaction();
                    $mytime= Carbon::now('America/Hermosillo');
                    $consultaspei->fecha = $mytime->toDateTimeString();
                    $consultaspei->reference = $request->r;
                    $consultaspei->codigo = "40";
                    $consultaspei->mensaje = "Adquiriente invÃ¡lido";
                    $consultaspei->save();
                    DB::commit();
                } catch (Exception $e){
                    DB::rollBack();            
                }
                return response()->json([
                    'codigo' => '40',
                    'mensaje' => 'Adquiriente invÃ¡lido',
                    'monto' => "",
                    'clabe' => "",
                    'transaccion' => "",
                    'parcial' => 0
    
                ], 200);
            } else {                
                $mytime = Carbon::now('America/Hermosillo')->endOfDay();
                $ExpirationDate = new Carbon($transaccion->ExpirationDate);

                if($transaccion->condicion == 3){
                    try{
                        DB::beginTransaction();
                        $mytime= Carbon::now('America/Hermosillo');
                        $consultaspei->idtransaccion = $transaccion->id;
                        $consultaspei->fecha = $mytime->toDateTimeString();
                        $consultaspei->reference = $request->r;
                        $consultaspei->codigo = "13";
                        $consultaspei->mensaje = "Referencia sin adeudo";
                        $consultaspei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '13',
                        'mensaje' => 'Referencia sin adeudo',
                        'monto' => $transaccion->Amount,
                        'clabe' => $transaccion->Clabe,
                        'transaccion' => $transaccion->responseReference,
                        'parcial' => 1
                    ], 200);
                } else if($mytime->gt($ExpirationDate)){
                    try{
                        DB::beginTransaction();
                        $mytime= Carbon::now('America/Hermosillo');
                        $consultaspei->idtransaccion = $transaccion->id;
                        $consultaspei->fecha = $mytime->toDateTimeString();
                        $consultaspei->reference = $request->r;
                        $consultaspei->codigo = "14";
                        $consultaspei->mensaje = "Referencia fuera de vigencia";
                        $consultaspei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '14',
                        'mensaje' => 'Referencia fuera de vigencia',
                        'monto' => $transaccion->Amount,
                        'clabe' => $transaccion->Clabe,
                        'transaccion' => $transaccion->responseReference,
                        'parcial' => 1
                    ], 200);
                } else {
                    try{
                        DB::beginTransaction();
                        $mytime= Carbon::now('America/Hermosillo');
                        $consultaspei->idtransaccion = $transaccion->id;
                        $consultaspei->fecha = $mytime->toDateTimeString();
                        $consultaspei->reference = $request->r;
                        $consultaspei->codigo = "0";
                        $consultaspei->mensaje = "OperaciÃ³n exitosa";
                        $consultaspei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '0',
                        'mensaje' => 'OperaciÃ³n exitosa',
                        'monto' => $transaccion->Amount,
                        'clabe' => $transaccion->Clabe,
                        'transaccion' => $transaccion->responseReference,
                        'parcial' => 1
        
                    ], 200);
                }            
            }
        } catch (Exception $e){
            try{
                DB::beginTransaction();
                $mytime= Carbon::now('America/Hermosillo');
                $consultaspei->fecha = $mytime->toDateTimeString();
                if (isset($transaccion) && $transaccion !== null) {
                    $consultaspei->idtransaccion = $transaccion->id;
                }
                $consultaspei->reference = $request->r;
                $consultaspei->codigo = "50";
                $consultaspei->mensaje = "Error de sistema";
                $consultaspei->save();
                DB::commit();
            } catch (Exception $e){
                DB::rollBack();            
            }
            return response()->json([
                'codigo' => '50',
                'mensaje' => 'Error de sistema',
                'monto' => "",
                'clabe' => "",
                'transaccion' => "",
                'parcial' => 0

            ], 200); 
        }  
    }

    public function pagoClabe(Request $request)
    {     
        try {
            $pagospei = new PagoSpei();
            $mytime = Carbon::now('America/Hermosillo')->endOfDay();
            $fechaAutorizacion = Carbon::now('America/Hermosillo')->toDateString();
            $myspei= Carbon::now('America/Hermosillo');

            [$date_response, $payloadError] = $this->leerPayloadServicioJson($request, ['clabe', 'monto', 'fecha', 'transaccion']);
            if ($payloadError !== null || !is_numeric($date_response["monto"])) {
                Log::warning('PagoClabe rechazado: ' . ($payloadError ?: 'Monto invalido.'));

                return response()->json([
                    'codigo' => '50',
                    'autorizacion' => '0',
                    'mensaje' => 'Error de sistema',
                    'transaccion' => is_array($date_response) ? $this->valorServicio($date_response, 'transaccion') : '',
                    'fecha' => $fechaAutorizacion
                ], 200);
            }

            $pagoDuplicado = PagoSpei::where('transaccion', '=', $date_response["transaccion"])->first();
            if ($pagoDuplicado !== null) {
                return response()->json([
                    'codigo' => $pagoDuplicado->codigo,
                    'autorizacion' => $pagoDuplicado->autorizacion ?: '0',
                    'mensaje' => $pagoDuplicado->mensaje,
                    'transaccion' => $pagoDuplicado->transaccion,
                    'fecha' => $fechaAutorizacion
                ], 200);
            }

            if(strlen($date_response["clabe"])!=18){
                try{
                    DB::beginTransaction();            
                    $pagospei->fecha = $myspei->toDateTimeString();
                    $pagospei->clabe = $date_response["clabe"];
                    $pagospei->monto = $date_response["monto"];
                    $pagospei->fecha_peticion = $date_response["fecha"];
                    $pagospei->codigo = "15";
                    $pagospei->autorizacion = "0";
                    $pagospei->mensaje = "Referencia con error de formato";
                    $pagospei->transaccion = $date_response["transaccion"];
                    $pagospei->condicion = 0;
                    $pagospei->save();
                    DB::commit();
                } catch (Exception $e){
                    DB::rollBack();            
                }
                return response()->json([
                    'codigo' => '15',
                    'autorizacion' => '0',
                    'mensaje' => 'Referencia con error de formato',
                    'transaccion' =>  $date_response["transaccion"],
                    'fecha' => $fechaAutorizacion

                ], 200);                        
            }
        
            $transaccion = Transaccion::where('Clabe', '=', $date_response["clabe"])->first();

            if($transaccion == null){
                try{
                    DB::beginTransaction();                    
                    $pagospei->fecha = $myspei->toDateTimeString();
                    $pagospei->clabe = $date_response["clabe"];
                    $pagospei->monto = $date_response["monto"];
                    $pagospei->fecha_peticion = $date_response["fecha"];
                    $pagospei->codigo = "40";
                    $pagospei->mensaje = "Adquiriente invÃ¡lido";
                    $pagospei->transaccion = $date_response["transaccion"];
                    $pagospei->condicion = 0;
                    $pagospei->save();
                    DB::commit();
                } catch (Exception $e){
                    DB::rollBack();            
                }
                return response()->json([
                    'codigo' => '40',
                    'mensaje' => 'Adquiriente invÃ¡lido',
                    'transaccion' => $date_response["transaccion"],
                    'fecha' => $fechaAutorizacion
    
                ], 200);
            } else {                
                $ExpirationDate = new Carbon($transaccion->ExpirationDate);
                $ExpirationDateStart = $ExpirationDate->startOfDay();
                if($transaccion->condicion == 3){
                    try{
                        DB::beginTransaction();                        
                        $pagospei->fecha = $myspei->toDateTimeString();
                        $pagospei->idtransaccion = $transaccion->id;
                        $pagospei->clabe = $date_response["clabe"];
                        $pagospei->monto = $date_response["monto"];
                        $pagospei->fecha_peticion = $date_response["fecha"];
                        $pagospei->codigo = "13";
                        $pagospei->autorizacion = "0";
                        $pagospei->mensaje = "Referencia sin adeudo";
                        $pagospei->transaccion = $date_response["transaccion"];
                        $pagospei->condicion = 0;
                        $pagospei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '13',
                        'autorizacion' => '0',
                        'mensaje' => 'Referencia sin adeudo',
                        'transaccion' => $date_response["transaccion"],
                        'fecha' => $fechaAutorizacion
                    ], 200);
                } else if($mytime->gt($ExpirationDateStart)){
                    try{
                        DB::beginTransaction();                        
                        $pagospei->fecha = $myspei->toDateTimeString();
                        $pagospei->idtransaccion = $transaccion->id;
                        $pagospei->clabe = $date_response["clabe"];
                        $pagospei->monto = $date_response["monto"];
                        $pagospei->fecha_peticion = $date_response["fecha"];
                        $pagospei->codigo = "14";
                        $pagospei->autorizacion = "0";
                        $pagospei->mensaje = "Referencia fuera de vigencia";
                        $pagospei->transaccion = $date_response["transaccion"];
                        $pagospei->condicion = 0;
                        $pagospei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '14',
                        'autorizacion' => '0',
                        'mensaje' => 'Referencia fuera de vigencia',
                        'transaccion' => $date_response["transaccion"],
                        'fecha' => $fechaAutorizacion
                    ], 200);
                }  else if(intval($transaccion->Amount) != intval($date_response["monto"])){
                    try{
                        DB::beginTransaction();                        
                        $pagospei->fecha = $myspei->toDateTimeString();
                        $pagospei->idtransaccion = $transaccion->id;
                        $pagospei->clabe = $date_response["clabe"];
                        $pagospei->monto = $date_response["monto"];
                        $pagospei->fecha_peticion = $date_response["fecha"];
                        $pagospei->codigo = "30";
                        $pagospei->autorizacion = "0";
                        $pagospei->mensaje = "Monto invÃ¡lido";
                        $pagospei->transaccion = $date_response["transaccion"];
                        $pagospei->condicion = 0;
                        $pagospei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '30',
                        'autorizacion' => '0',
                        'mensaje' => 'Monto invÃ¡lido',
                        'transaccion' => $date_response["transaccion"],
                        'fecha' => $fechaAutorizacion
                    ], 200);
                } else {
                    $transaccion->condicion = 3;
                    $transaccion->save();
                    $autorizacion = $this->generateAuthNumber();
                    try{
                        DB::beginTransaction();                        
                        $pagospei->fecha = $myspei->toDateTimeString();
                        $pagospei->idtransaccion = $transaccion->id;
                        $pagospei->clabe = $date_response["clabe"];
                        $pagospei->monto = $date_response["monto"];
                        $pagospei->fecha_peticion = $date_response["fecha"];
                        $pagospei->codigo = "0";
                        $pagospei->autorizacion = $autorizacion;
                        $pagospei->mensaje = "OperaciÃ³n exitosa";
                        $pagospei->transaccion = $date_response["transaccion"];
                        $pagospei->condicion = 1;
                        $pagospei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    /*$usuario = User::find($transaccion->idusuario);
                    if($usuario->notificaPago){
                        $monto = (((float) $date_response["monto"]) / 100.00);
                        // Set request params
                        $params = array(
                            "folio" => $transaccion->ClientReference,
                            "monto" => ((float) $monto),
                            "reference" => '',
                            "clabe" => $date_response["clabe"],
                            "foliocpagos" =>  $date_response["transaccion"],
                            "auth" => $autorizacion,
                            "amount" => $monto
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
                            $response_body = (string) $response->getBody();
                            $response_decode = json_decode($response_body);
                            $error_code = $response_decode->code;
                            $error_msg = $response_decode->message;
                        }
                        if($response_decode == "") {
                            $response_body = (string) $response->getBody();
                            $response_decode = json_decode($response_body);
                            $response_code = $response_decode->code;
                            $response_msg = $response_decode->message;
                        }
                        if($response_code == "success"){
                            $pagospei->enviada = 1;
                            $pagospei->save();
                        }
                    }*/
                    return response()->json([
                        'codigo' => '0',
                        'autorizacion' => $autorizacion,
                        'mensaje' => 'OperaciÃ³n exitosa',                                             
                        'transaccion' => $date_response["transaccion"],
                        'fecha' => $fechaAutorizacion
                    ], 200);
                }            
            }
        } catch (Exception $e){           
            return response()->json([
                'codigo' => '50',
                'autorizacion' => '0',
                'mensaje' => 'Error de sistema',
                'transaccion' => $date_response["transaccion"],
                'fecha' => ''
            ], 200);
        }
    }

    public function revisarStatus() {
        $pagos = PagoSpei::join('transacciones','transacciones.id','pagospei.idtransaccion')
        ->join('users','users.id','transacciones.idusuario')
        ->select('pagospei.id as id')
        ->where([
            ['users.notificaPago','=','1'],
            ['pagospei.condicion','=','1'],
            ['pagospei.autorizacion','<>','0'],
            ['pagospei.codigo','=','0'],
            ['pagospei.enviada','=','0']])->get();
        
        foreach($pagos as $pago){            
            $this->consultaStatus($pago->id);                        
        }

        /*$pagosligas = Respuesta::join('transacciones','transacciones.id','respuestas.idtransaccion')
        ->join('users','users.id','transacciones.idusuario')
        ->select('respuestas.id as id')
        ->where([
            ['users.notificaPago','=','1'],
            ['respuestas.status','=','approved'],
            ['respuestas.enviada','=','0']])->get();
        
        foreach($pagosligas as $pagoliga){            
            $this->consultaStatusLiga($pagoliga->id);                        
        }*/
    }

    public function consultaStatus($idpago)
    {
        $pagospei = PagoSpei::find($idpago);
        $transaccion = Transaccion::find($pagospei->idtransaccion);
        $usuario = User::find($transaccion->idusuario);
    
        if($usuario->notificaPago){
            $monto = (((float) $pagospei->monto) / 100.00);
            // Set request params
            $params = array(
                "folio" => $transaccion->ClientReference,
                "monto" => ((float) $monto),
                "reference" => '',
                "clabe" => $pagospei->clabe,
                "foliocpagos" =>  $pagospei->transaccion,
                "auth" => $pagospei->autorizacion,
                "amount" => $monto
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
                Log::info('FallÃ³ el registro del pago spei '.$pagospei->id);
                return;
            }
            if($response_decode == "") {
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body);
                $response_code = $response_decode->code;
                $response_msg = $response_decode->message;
            }
            if($response_code == "success"){
                Log::info('Si se recibiÃ³ respuesta exitosa del pago '.$pagospei->id);
                $pagospei->enviada = 1;
                $pagospei->save();
            }
        }      
    }

    public function consultaStatusLiga($idpago)
    {
        $respuesta = Respuesta::find($idpago);
        $transaccion = Transaccion::find($respuesta->idtransaccion);
        $usuario = User::find($transaccion->idusuario);
    
        if($usuario->notificaPago){
            $monto = ((float) $respuesta->amount);
            // Set request params
            $params = array(
                "folio" => $transaccion->ClientReference,
                "monto" =>$monto,
                "reference" => $respuesta->reference,
                "foliocpagos" =>  $respuesta->foliocpagos,
                "auth" => $respuesta->auth,
                "cc_type" => $respuesta->cc_type,
                "cc_name" => $respuesta->cc_name,
                "cc_number" => $respuesta->cc_number,
                "cc_expmonth" => $respuesta->cc_expmonth,
                "cc_expyear" => $respuesta->cc_expyear,
                "amount" => $monto,
                "id_url" => $respuesta->id_url,
                "email" => $respuesta->email,
                "payment_type" => $respuesta->payment_type
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
                Log::info('FallÃ³ el registro de la liga de pago '.$respuesta->id);
                return;
            }
            if($response_decode == "") {
                $response_body = (string) $response->getBody();
                $response_decode = json_decode($response_body);
                $response_code = $response_decode->code;
                $response_msg = $response_decode->message;
            }
            if($response_code == "success"){
                Log::info('Si se recibiÃ³ respuesta exitosa del pago '.$respuesta->id);
                $respuesta->enviada = 1;
                $respuesta->save();
            }
        }
      
    }

    public function cancelaClabe(Request $request)
    {        
        try {
            $cancelaspei = new CancelaSpei();                        
            $myspei= Carbon::now('America/Hermosillo');

            [$date_response, $payloadError] = $this->leerPayloadServicioJson($request, ['clabe', 'fecha', 'monto', 'transaccion', 'autorizacion']);
            if ($payloadError !== null || !is_numeric($date_response["monto"])) {
                Log::warning('CancelaClabe rechazado: ' . ($payloadError ?: 'Monto invalido.'));

                return response()->json([
                    'codigo' => '50',
                    'mensaje' => 'Error de sistema',
                ], 200);
            }

            $cancelacionDuplicada = CancelaSpei::where('transaccion', '=', $date_response["transaccion"])
                ->where('autorizacion', '=', $date_response["autorizacion"])
                ->first();
            if ($cancelacionDuplicada !== null) {
                return response()->json([
                    'codigo' => $cancelacionDuplicada->codigo,
                    'mensaje' => $cancelacionDuplicada->mensaje,
                ], 200);
            }

            if(strlen($date_response["clabe"])!=18){
                try{
                    DB::beginTransaction();            
                    $cancelaspei->fecha = $myspei->toDateTimeString();
                    $cancelaspei->clabe = $date_response["clabe"];
                    $cancelaspei->fecha_peticion = $date_response["fecha"];
                    $cancelaspei->monto = $date_response["monto"];
                    $cancelaspei->transaccion = $date_response["transaccion"];
                    $cancelaspei->autorizacion = $date_response["autorizacion"];
                    $cancelaspei->codigo = "15";                    
                    $cancelaspei->mensaje = "Referencia con error de formato";                    
                    $cancelaspei->save();
                    DB::commit();
                } catch (Exception $e){
                    DB::rollBack();            
                }
                return response()->json([
                    'codigo' => '15',                    
                    'mensaje' => 'Referencia con error de formato',
                ], 200);                        
            }
        
            $transaccion = Transaccion::where('Clabe', '=', $date_response["clabe"])->first();
            $pagospei = PagoSpei::where(
                [
                    ['clabe', '=', $date_response["clabe"]],
                    ['autorizacion', '=', $date_response["autorizacion"]]
                ])->first();

            if($transaccion == null){
                try{
                    DB::beginTransaction();                    
                    $cancelaspei->fecha = $myspei->toDateTimeString();
                    $cancelaspei->clabe = $date_response["clabe"];
                    $cancelaspei->fecha_peticion = $date_response["fecha"];
                    $cancelaspei->monto = $date_response["monto"];
                    $cancelaspei->transaccion = $date_response["transaccion"];
                    $cancelaspei->autorizacion = $date_response["autorizacion"];
                    $cancelaspei->codigo = "40";
                    $cancelaspei->mensaje = "Adquiriente invÃ¡lido";                    
                    $cancelaspei->save();
                    DB::commit();
                } catch (Exception $e){
                    DB::rollBack();            
                }
                return response()->json([
                    'codigo' => '40',
                    'mensaje' => 'Adquiriente invÃ¡lido',
    
                ], 200);
            } else {                
                if($pagospei == null){
                    try{
                        DB::beginTransaction();
                        $cancelaspei->fecha = $myspei->toDateTimeString();
                        $cancelaspei->idtransaccion = $transaccion->id;
                        $cancelaspei->clabe = $date_response["clabe"];
                        $cancelaspei->fecha_peticion = $date_response["fecha"];
                        $cancelaspei->monto = $date_response["monto"];
                        $cancelaspei->transaccion = $date_response["transaccion"];
                        $cancelaspei->autorizacion = $date_response["autorizacion"];
                        $cancelaspei->codigo = "40";
                        $cancelaspei->mensaje = "Pago no encontrado";
                        $cancelaspei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();
                    }
                    return response()->json([
                        'codigo' => '40',
                        'mensaje' => 'Pago no encontrado',
                    ], 200);
                }

                $fechapago = new Carbon($pagospei->fecha);
                $ExpirationDate = $fechapago->endOfDay();
                if($transaccion->condicion == 1){
                    try{
                        DB::beginTransaction();                        
                        $cancelaspei->fecha = $myspei->toDateTimeString();
                        $cancelaspei->idtransaccion = $transaccion->id;
                        $cancelaspei->clabe = $date_response["clabe"];
                        $cancelaspei->fecha_peticion = $date_response["fecha"];
                        $cancelaspei->monto = $date_response["monto"];
                        $cancelaspei->transaccion = $date_response["transaccion"];
                        $cancelaspei->autorizacion = $date_response["autorizacion"];
                        $cancelaspei->codigo = "13";
                        $cancelaspei->mensaje = "Referencia aÃºn no pagada";
                        $cancelaspei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '13',                        
                        'mensaje' => 'Referencia aÃºn no pagada',
                    ], 200);
                } else if($transaccion->condicion == 2){
                    try{
                        DB::beginTransaction();                        
                        $cancelaspei->fecha = $myspei->toDateTimeString();
                        $cancelaspei->idtransaccion = $transaccion->id;
                        $cancelaspei->clabe = $date_response["clabe"];
                        $cancelaspei->fecha_peticion = $date_response["fecha"];
                        $cancelaspei->monto = $date_response["monto"];
                        $cancelaspei->transaccion = $date_response["transaccion"];
                        $cancelaspei->autorizacion = $date_response["autorizacion"];
                        $cancelaspei->codigo = "14";
                        $cancelaspei->mensaje = "Referencia cancelada previamente";
                        $cancelaspei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '14',
                        'mensaje' => 'Referencia cancelada previamente',
                    ], 200);
                } else if($myspei->gt($ExpirationDate)){
                    try{
                        DB::beginTransaction();                        
                        $cancelaspei->fecha = $myspei->toDateTimeString();
                        $cancelaspei->idtransaccion = $transaccion->id;
                        $cancelaspei->clabe = $date_response["clabe"];
                        $cancelaspei->fecha_peticion = $date_response["fecha"];
                        $cancelaspei->monto = $date_response["monto"];
                        $cancelaspei->transaccion = $date_response["transaccion"];
                        $cancelaspei->autorizacion = $date_response["autorizacion"];
                        $cancelaspei->codigo = "60";
                        $cancelaspei->mensaje = "CancelaciÃ³n fuera de periodo";
                        $cancelaspei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '60',                        
                        'mensaje' => 'CancelaciÃ³n fuera de periodo',
                    ], 200);
                }  else {
                    if($transaccion != null && $transaccion->condicion==3) {
                        $transaccion->condicion = 1;
                        $transaccion->save();
                    }
                    if($pagospei != null) {
                        $pagospei->condicion = 2;
                        $pagospei->save();
                    }
                    try{
                        DB::beginTransaction();                        
                        $cancelaspei->fecha = $myspei->toDateTimeString();
                        $cancelaspei->idtransaccion = $transaccion->id;
                        $cancelaspei->clabe = $date_response["clabe"];
                        $cancelaspei->fecha_peticion = $date_response["fecha"];
                        $cancelaspei->monto = $date_response["monto"];
                        $cancelaspei->transaccion = $date_response["transaccion"];
                        $cancelaspei->autorizacion = $date_response["autorizacion"];
                        $cancelaspei->codigo = "0";
                        $cancelaspei->mensaje = "CancelaciÃ³n exitosa";                        
                        $cancelaspei->save();
                        DB::commit();
                    } catch (Exception $e){
                        DB::rollBack();            
                    }
                    return response()->json([
                        'codigo' => '0',                        
                        'mensaje' => 'CancelaciÃ³n exitosa',
                    ], 200);
                }
            }
        } catch (Exception $e){           
            return response()->json([
                'codigo' => '50',                
                'mensaje' => 'Error de sistema',                
            ], 200);
        }
    }    


    public function generateAuthNumber() {
        $number = mt_rand(10000000, 99999999);        
        if ($this->authNumberExists($number)) {
            return $this->generateAuthNumber();
        }        
        return $number;
    }
    
    public function authNumberExists($number) {
        return PagoSpei::where('autorizacion','=',$number)->exists();
    }


    private function getImportFilePath($importId)
    {
        return 'imports/transacciones_' . $importId . '.json';
    }

    private function normalizeImportText($value)
    {
        return trim((string) $value);
    }

    private function parseImportAmount($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9\.,-]/', '', (string) $value);

        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (substr_count($normalized, ',') > 0 && substr_count($normalized, '.') > 0) {
            $normalized = str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function parseImportDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value))->format('Y-m-d');
            } catch (Exception $e) {
                return null;
            }
        }

        $rawValue = trim((string) $value);
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $rawValue);
                if ($date->format($format) !== $rawValue) {
                    continue;
                }

                return $date->format('Y-m-d');
            } catch (Exception $e) {
            }
        }

        return null;
    }

    private function calculateImportProximoCargo($expirationDate, $frecuencia)
    {
        if ($expirationDate === null || $frecuencia === null || (int) $frecuencia <= 0) {
            return null;
        }

        try {
            $baseDate = Carbon::createFromFormat('Y-m-d', $expirationDate);
        } catch (Exception $e) {
            return null;
        }

        $frecuencia = (int) $frecuencia;

        if ($frecuencia === 1) {
            $baseDate->addDays(7);
        } elseif ($frecuencia === 2) {
            $baseDate->addMonth();
        } elseif ($frecuencia === 3) {
            $baseDate->addMonths(2);
        } elseif ($frecuencia === 4) {
            $baseDate->addMonths(6);
        } elseif ($frecuencia === 5) {
            $baseDate->addYear();
        } else {
            return null;
        }

        return $baseDate->format('Y-m-d');
    }

    private function mapFrecuenciaImportacion($value)
    {
        $raw = mb_strtolower(trim((string) $value));

        $map = [
            '1' => 1,
            'semanal' => 1,
            '2' => 2,
            'mensual' => 2,
            '3' => 3,
            'bimestral' => 3,
            '4' => 4,
            'semestral' => 4,
            '5' => 5,
            'anual' => 5,
        ];

        return $map[$raw] ?? null;
    }

    private function resolveClienteIdImportacion($cliente)
    {
        $cliente = trim((string) $cliente);

        $objCliente = Cliente::where('razon_social', $cliente)->first();
        if ($objCliente != null) {
            return $objCliente->id;
        }

        $persona = Persona::where('nombre', $cliente)->first();
        if ($persona != null) {
            return $persona->id;
        }

        return null;
    }

    private function loadImportState($importId)
    {
        $path = $this->getImportFilePath($importId);
        if (!Storage::disk('local')->exists($path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($path), true);
    }

    private function saveImportState($importId, $state)
    {
        Storage::disk('local')->put($this->getImportFilePath($importId), json_encode($state));
    }

    public function iniciarImportacion(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $this->validate($request, [
            'archivo' => 'required|file|mimes:xlsx,xls',
            'tipo' => 'required|integer|in:1,2',
        ]);

        $tipo = (int) $request->tipo;
        $file = $request->file('archivo');
        $importId = Str::uuid()->toString();
        $storageDir = storage_path('app/imports');

        if (!file_exists($storageDir)) {
            mkdir($storageDir, 0777, true);
        }

        $storedFileName = 'origen_' . $importId . '.' . $file->getClientOriginalExtension();
        $storedFilePath = $file->storeAs('imports', $storedFileName, 'local');

        $spreadsheet = IOFactory::load(storage_path('app/' . $storedFilePath));
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, true);

        if (count($rows) < 1) {
            return response()->json(['error' => 'El archivo no contiene informaciÃ³n.'], 422);
        }

        $headers = array_shift($rows);
        $headerMap = [];
        foreach ($headers as $column => $header) {
            $headerMap[trim((string) $header)] = $column;
        }

        $required = ['Cliente', 'Forma de pago', 'DescripciÃ³n', 'Monto', 'Fecha ExpiraciÃ³n', 'Referencia'];
        if ($tipo === 2) {
            $required[] = 'Frecuencia';
        }

        $faltantes = [];
        foreach ($required as $column) {
            if (!array_key_exists($column, $headerMap)) {
                $faltantes[] = $column;
            }
        }

        if (count($faltantes) > 0) {
            return response()->json(['error' => 'Faltan columnas requeridas: ' . implode(', ', $faltantes)], 422);
        }

        $items = [];
        $excelRow = 1;
        foreach ($rows as $row) {
            $excelRow++;
            $hasData = false;
            foreach ($required as $column) {
                $value = $row[$headerMap[$column]] ?? null;
                if (trim((string) $value) !== '') {
                    $hasData = true;
                    break;
                }
            }

            if (!$hasData) {
                continue;
            }

            $items[] = [
                'excel_row' => $excelRow,
                'cliente' => $this->normalizeImportText($row[$headerMap['Cliente']] ?? ''),
                'payment_types' => $this->normalizeImportText($row[$headerMap['Forma de pago']] ?? ''),
                'description' => $this->normalizeImportText($row[$headerMap['DescripciÃ³n']] ?? ''),
                'amount_raw' => $row[$headerMap['Monto']] ?? '',
                'expiration_raw' => $row[$headerMap['Fecha ExpiraciÃ³n']] ?? '',
                'client_reference' => $this->normalizeImportText($row[$headerMap['Referencia']] ?? ''),
                'frecuencia_raw' => $tipo === 2 ? $this->normalizeImportText($row[$headerMap['Frecuencia']] ?? '') : '',
                'status' => 'pendiente',
                'detalle' => '',
                'url' => '',
            ];
        }

        $state = [
            'id' => $importId,
            'tipo' => $tipo,
            'archivo_origen' => $storedFilePath,
            'created_by' => \Auth::user()->id,
            'total' => count($items),
            'processed' => 0,
            'generated' => 0,
            'errors' => 0,
            'error_cliente' => 0,
            'error_monto' => 0,
            'error_fecha' => 0,
            'error_pago' => 0,
            'error_frecuencia' => 0,
            'cancelled_omitted' => 0,
            'status' => 'in_progress',
            'items' => $items,
        ];

        $this->saveImportState($importId, $state);

        return response()->json([
            'import_id' => $importId,
            'total' => $state['total'],
        ]);
    }

    public function procesarImportacion(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $this->validate($request, [
            'import_id' => 'required|string',
        ]);

        $importId = $request->import_id;
        $state = $this->loadImportState($importId);

        if ($state == null) {
            return response()->json(['error' => 'No se encontrÃ³ la importaciÃ³n.'], 404);
        }

        if ((int) $state['created_by'] !== (int) \Auth::user()->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        if ($state['status'] === 'cancelled' || $state['status'] === 'completed') {
            return response()->json($state);
        }

        $nextIndex = null;
        foreach ($state['items'] as $index => $item) {
            if ($item['status'] === 'pendiente') {
                $nextIndex = $index;
                break;
            }
        }

        if ($nextIndex === null) {
            $state['status'] = 'completed';
            $this->saveImportState($importId, $state);
            return response()->json($state);
        }

        $item = $state['items'][$nextIndex];
        $errors = [];

        $clienteId = $this->resolveClienteIdImportacion($item['cliente']);
        if ($clienteId == null) {
            $errors[] = 'Cliente no encontrado';
            $state['error_cliente']++;
        }

        if (!in_array((string) $item['payment_types'], ['41', '102'])) {
            $errors[] = 'Forma de pago invÃ¡lida';
            $state['error_pago']++;
        }

        $amount = $this->parseImportAmount($item['amount_raw']);
        if ($amount == null || $amount < 50) {
            $errors[] = 'Monto invÃ¡lido (mÃ­nimo 50)';
            $state['error_monto']++;
        }

        $expirationDate = $this->parseImportDate($item['expiration_raw']);
        if ($expirationDate == null) {
            $errors[] = 'Fecha de expiraciÃ³n invÃ¡lida';
            $state['error_fecha']++;
        } else {
            $today = Carbon::now('America/Hermosillo')->format('Y-m-d');
            if ($expirationDate < $today) {
                $errors[] = 'Fecha de expiraciÃ³n no puede ser pasada';
                $state['error_fecha']++;
            }
        }

        $frecuencia = 0;
        if ((int) $state['tipo'] === 2) {
            $frecuencia = $this->mapFrecuenciaImportacion($item['frecuencia_raw']);
            if ($frecuencia == null) {
                $errors[] = 'Frecuencia invÃ¡lida';
                $state['error_frecuencia']++;
            }
        }

        if ($item['description'] === '') {
            $errors[] = 'DescripciÃ³n obligatoria';
        }

        if ($item['client_reference'] === '') {
            $errors[] = 'Referencia obligatoria';
        }

        if (count($errors) > 0) {
            $state['items'][$nextIndex]['status'] = 'error';
            $state['items'][$nextIndex]['detalle'] = implode(' | ', $errors);
            $state['errors']++;
            $state['processed']++;
            if ($state['processed'] >= $state['total']) {
                $state['status'] = 'completed';
            }
            $this->saveImportState($importId, $state);
            return response()->json($state);
        }

        $payload = [
            'idcliente' => $clienteId,
            'PaymentTypes' => $item['payment_types'],
            'Description' => $item['description'],
            'Amount' => $amount,
            'ExpirationDate' => $expirationDate,
            'ClientReference' => $item['client_reference'],
            'tipo' => $state['tipo'],
            'frecuencia' => $frecuencia,
            'ProximoCargo' => $this->calculateImportProximoCargo($expirationDate, $frecuencia),
        ];

        $internalRequest = Request::create('/transaccion/importar/fila', 'POST', $payload);
        $internalRequest->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = ((int) $state['tipo'] === 1)
            ? $this->store($internalRequest)
            : $this->storeDom($internalRequest);

        $error = is_array($response) ? ($response['error'] ?? '') : '';
        if ($error === '') {
            $last = Transaccion::where('idusuario', \Auth::user()->id)->where('idcliente', $clienteId)->where('tipo', $state['tipo'])->orderBy('id', 'desc')->first();
            $state['items'][$nextIndex]['status'] = 'generada';
            $state['items'][$nextIndex]['detalle'] = 'Generada correctamente';
            $state['items'][$nextIndex]['url'] = $last != null ? $last->url : '';
            $state['generated']++;
        } else {
            $state['items'][$nextIndex]['status'] = 'error';
            $state['items'][$nextIndex]['detalle'] = $error;
            $state['errors']++;
        }

        $state['processed']++;
        if ($state['processed'] >= $state['total']) {
            $state['status'] = 'completed';
        }

        $this->saveImportState($importId, $state);

        return response()->json($state);
    }

    public function cancelarImportacion(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $this->validate($request, [
            'import_id' => 'required|string',
        ]);

        $state = $this->loadImportState($request->import_id);
        if ($state == null) {
            return response()->json(['error' => 'No se encontrÃ³ la importaciÃ³n.'], 404);
        }

        if ((int) $state['created_by'] !== (int) \Auth::user()->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        if ($state['status'] !== 'completed') {
            foreach ($state['items'] as $index => $item) {
                if ($item['status'] === 'pendiente') {
                    $state['items'][$index]['status'] = 'omitida';
                    $state['items'][$index]['detalle'] = 'Omitida por cancelaciÃ³n';
                    $state['processed']++;
                    $state['cancelled_omitted']++;
                }
            }
            $state['status'] = 'cancelled';
            $this->saveImportState($request->import_id, $state);
        }

        return response()->json($state);
    }

    public function estatusImportacion(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $importId = $request->import_id;
        $state = $this->loadImportState($importId);

        if ($state == null) {
            return response()->json(['error' => 'No se encontrÃ³ la importaciÃ³n.'], 404);
        }

        if ((int) $state['created_by'] !== (int) \Auth::user()->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        return response()->json($state);
    }

    public function descargarLogImportacion(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $importId = $request->import_id;
        $state = $this->loadImportState($importId);

        if ($state == null) {
            return response()->json(['error' => 'No se encontrÃ³ la importaciÃ³n.'], 404);
        }

        if ((int) $state['created_by'] !== (int) \Auth::user()->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $inputPath = storage_path('app/' . $state['archivo_origen']);
        $spreadsheet = IOFactory::load($inputPath);
        $sheet = $spreadsheet->getActiveSheet();

        $lastColumn = $sheet->getHighestColumn();
        $resultColumn = ++$lastColumn;
        $sheet->setCellValue($resultColumn . '1', 'Resultado');

        foreach ($state['items'] as $item) {
            $result = $item['status'] === 'generada' && $item['url'] !== ''
                ? $item['url']
                : $item['detalle'];
            $sheet->setCellValue($resultColumn . $item['excel_row'], $result);
        }

        $outputPath = storage_path('app/imports/log_' . $importId . '.xlsx');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputPath);

        return response()->download($outputPath, 'log_importacion_ligas.xlsx')->deleteFileAfterSend(true);
    }

    public function exportar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $tipo = (int) $request->tipo;
        $query = Transaccion::leftjoin('clientes', 'clientes.id', 'transacciones.idcliente')
            ->leftjoin('users', 'users.id', 'transacciones.idusuario')
            ->select(
                'transacciones.folio',
                'transacciones.fecha',
                'transacciones.PaymentTypes',
                'transacciones.IdReference',
                'transacciones.Description',
                'transacciones.Amount',
                'transacciones.Reference',
                'transacciones.ClientReference',
                'transacciones.responseReference',
                'transacciones.url',
                'transacciones.ExpirationDate',
                'transacciones.Date',
                'transacciones.Clabe',
                'clientes.razon_social',
                'users.usuario',
                'transacciones.frecuencia',
                'transacciones.ProximoCargo',
                'transacciones.condicion'
            )
            ->where('transacciones.tipo', $tipo)
            ->orderBy('transacciones.id', 'desc');

        $this->aplicarScopePropietario($query, 'transacciones');

        $headings = [
            'Folio',
            'Fecha',
            'Tipo',
            'Id Referencia',
            'Descripcion',
            'Monto',
            'Referencia',
            'Referencia Interna',
            'Referencia Respuesta',
            'URL',
            'Expiracion',
            'Date',
            'CLABE',
            'Cliente',
            'Usuario',
            'Frecuencia',
            'Proximo Cargo',
            'Status',
        ];

        return response()->streamDownload(function () use ($query, $headings) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new Exception('No fue posible abrir el stream de salida para la exportacion.');
            }

            // BOM UTF-8 para que Excel interprete acentos y caracteres latinos correctamente.
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $headings);

            foreach ($query->cursor() as $transaccion) {
                fputcsv($handle, [
                    $transaccion->folio,
                    $transaccion->fecha,
                    $transaccion->PaymentTypes,
                    $transaccion->IdReference,
                    $transaccion->Description,
                    $transaccion->Amount,
                    $transaccion->Reference,
                    $transaccion->ClientReference,
                    $transaccion->responseReference,
                    $transaccion->url,
                    $transaccion->ExpirationDate,
                    $transaccion->Date,
                    $transaccion->Clabe,
                    $transaccion->razon_social,
                    $transaccion->usuario,
                    $transaccion->frecuencia,
                    $transaccion->ProximoCargo,
                    $transaccion->condicion,
                ]);
            }

            fclose($handle);
        }, 'transacciones.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportarReporte(Request $request)
    {
        if (!$request->ajax()) return redirect('/');        
        $atributos = [];        
        $fechaInicio = $request->fechaInicio;
        $fechaFin = $request->fechaFin;
        $tipo =  $request->tipo;
        $idcliente = $request->idcliente;                                  

        $transaccionExport = new ReporteExport();

        if(!$this->usuarioEsAdministrador()) {
            array_push($atributos, [['transacciones.idusuario' => \Auth::user()->id]]);
            array_push($atributos, [['transacciones.productivo' => \Auth::user()->productivo]]);
        }
        array_push($atributos, [['transacciones.tipo' => $tipo]]);
        array_push($atributos, [['respuestas.status' => 'approved']]);
        if($idcliente > 0) array_push($atributos, [['transacciones.idcliente' => $idcliente]]);

        $transaccionExport->atributos($atributos);
        if($fechaInicio != 'null' && $fechaInicio != '') {
            $transaccionExport->fechas(Carbon::createFromFormat('Y-m-d',$fechaInicio)->startOfDay(), Carbon::createFromFormat('Y-m-d',$fechaFin)->endOfDay());
        }
        return Excel::download($transaccionExport, 'Reporte Transacciones.xlsx');
    }
}
