<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

use App\User;
use App\Transaccion;
use App\PagoSpei;

use App\Exports\PagoSpeiExport;
use App\Exports\ReportePagoSpeiExport;
use Excel;
use Exception;

class PagoSpeiController extends Controller
{
    public $fechaInicio = "";
    public $fechaFin = "";

    private function criteriosPermitidos()
    {
        return ['clabe', 'monto', 'transaccion', 'codigo', 'mensaje', 'autorizacion', 'ClientReference'];
    }

    private function pagoPerteneceUsuario(PagoSpei $pago)
    {
        if ($this->usuarioEsAdministrador()) {
            return true;
        }

        return $this->usuarioPuedeOperarRegistro(Transaccion::find($pago->idtransaccion));
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');               
        
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $offset = $this->offsetPaginacion($request->offset);        

        $query = PagoSpei::leftjoin('transacciones', 'transacciones.id','pagospei.idtransaccion')
        ->leftjoin('clientes', 'clientes.id','transacciones.idcliente')
        ->select('pagospei.id','pagospei.idtransaccion','pagospei.fecha','pagospei.clabe','pagospei.fecha','pagospei.monto','pagospei.mensaje',
        'pagospei.transaccion','pagospei.codigo','clientes.razon_social as nombre_cliente','pagospei.fecha_peticion','pagospei.autorizacion',
        'pagospei.condicion','pagospei.enviada');
        
        $this->aplicarScopePropietario($query, 'transacciones');
        
        if ($buscar!=''){
            if (!$this->criterioPermitido($criterio, $this->criteriosPermitidos())) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Criterio de búsqueda no permitido.',
                ], 422);
            }

            if($criterio=='ClientReference') {
                $query->where('transacciones.ClientReference', 'like', '%'. $buscar . '%');
            } else {
                $query->where('pagospei.'.$criterio, 'like', '%'. $buscar . '%');    
            }            
        }

        $pagospei = $query->orderBy('pagospei.id', 'desc')->paginate($offset);

        return [       
            'pagination' => [
                'total'        => $pagospei->total(),
                'current_page' => $pagospei->currentPage(),
                'per_page'     => $pagospei->perPage(),
                'last_page'    => $pagospei->lastPage(),
                'from'         => $pagospei->firstItem(),
                'to'           => $pagospei->lastItem(),
            ],    
            'pagospei' => $pagospei
        ];
    }    

    public function store(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $pagospei = new PagoSpei();

        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $pagospei->fecha = $mytime->toDateTimeString();
            $pagospei->clabe = $request->clabe;
            $pagospei->fecha_peticion = $request->fecha_peticion;
            $pagospei->monto = $request->monto;
            $pagospei->transaccion = $request->transaccion;
            $pagospei->codigo = $request->codigo;
            $pagospei->autorizacion = $request->autorizacion;
            $pagospei->mensaje = $request->mensaje;
            $pagospei->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
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
            $pagospei = PagoSpei::findOrFail($request->id);
            if (!$this->pagoPerteneceUsuario($pagospei)) {
                DB::rollBack();
                return $this->respuestaNoAutorizado($request);
            }
            $pagospei->clabe = $request->clabe;
            $pagospei->fecha_peticion = $request->fecha_peticion;
            $pagospei->monto = $request->monto;
            $pagospei->transaccion = $request->transaccion;
            $pagospei->codigo = $request->codigo;
            $pagospei->autorizacion = $request->autorizacion;
            $pagospei->mensaje = $request->mensaje;
            $pagospei->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
        }
    }
    
    public function delete(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $pagospei = PagoSpei::findOrFail($request->id);
        if (!$this->pagoPerteneceUsuario($pagospei)) {
            return $this->respuestaNoAutorizado($request);
        }
        $pagospei->delete();
    }

    public function exportar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $pagoSpeiExport = new PagoSpeiExport();        
        return Excel::download($pagoSpeiExport, 'pagospei.xlsx');
    }

    public function exportarReporteSpei(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $transacciones = $this->buildReportePagoSpeiQuery(
            $request->idcliente,
            $request->fechaInicio,
            $request->fechaFin
        )->orderBy('transacciones.id', 'desc')->get();

        return Excel::download(new ReportePagoSpeiExport($transacciones), 'reporteSpei.xlsx');
    }

    public function reportePagoSpei(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $transacciones = $this->buildReportePagoSpeiQuery(
            $request->idcliente,
            $request->fechaInicio,
            $request->fechaFin
        )->orderBy('transacciones.id', 'desc')->get();

        return [            
            'transacciones' => $transacciones
        ];
    }

    private function buildReportePagoSpeiQuery($idcliente, $fechaInicio, $fechaFin)
    {
        $query = PagoSpei::join('transacciones','transacciones.id','pagospei.idtransaccion')
            ->leftjoin('clientes','clientes.id','transacciones.idcliente')
            ->leftjoin('users','users.id','transacciones.idusuario')
            ->select('transacciones.id','transacciones.folio','transacciones.fecha','transacciones.IdReference','transacciones.Description',
            'transacciones.Amount','transacciones.Reference','transacciones.ExpirationDate','transacciones.ClientReference',
            'transacciones.Date','transacciones.Clabe','transacciones.idusuario','transacciones.idcliente','clientes.razon_social',
            'users.usuario','transacciones.tipo','transacciones.condicion','transacciones.productivo',
            'pagospei.created_at as fechaPago');

        if ((int) $idcliente > 0) {
            $query->where('transacciones.idcliente', '=', $idcliente);
        }

        $this->aplicarScopePropietario($query, 'transacciones');

        $query->where('transacciones.tipo', '=', '3');
        $query->where('pagospei.condicion', '=', '1');

        if ($fechaInicio != 'null' && $fechaInicio != '') {
            $query->whereBetween('pagospei.created_at', [
                Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $fechaFin)->endOfDay(),
            ]);
        }

        return $query;
    }
}
