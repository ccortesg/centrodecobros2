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
use App\ConsultaSpei;

use App\Exports\ConsultaSpeiExport;
use Excel;
use Exception;

class ConsultaSpeiController extends Controller
{
    private function criteriosPermitidos()
    {
        return ['reference', 'codigo', 'mensaje', 'ClientReference'];
    }

    private function consultaPerteneceUsuario(ConsultaSpei $consulta)
    {
        if ($this->usuarioEsAdministrador()) {
            return true;
        }

        return $this->usuarioPuedeOperarRegistro(Transaccion::find($consulta->idtransaccion));
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');               
        
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $offset = $this->offsetPaginacion($request->offset);        

        $query = ConsultaSpei::leftjoin('transacciones', 'transacciones.id','consultaspei.idtransaccion')
        ->leftjoin('clientes', 'clientes.id','transacciones.idcliente')
        ->select('consultaspei.id','consultaspei.idtransaccion','consultaspei.fecha','consultaspei.reference','consultaspei.fecha','consultaspei.codigo',
        'consultaspei.mensaje','consultaspei.parcial','clientes.razon_social as nombre_cliente');
        
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
                $query->where('consultaspei.'.$criterio, 'like', '%'. $buscar . '%');    
            }
        }

        $consultaspei = $query->orderBy('consultaspei.id', 'desc')->paginate($offset);

        return [       
            'pagination' => [
                'total'        => $consultaspei->total(),
                'current_page' => $consultaspei->currentPage(),
                'per_page'     => $consultaspei->perPage(),
                'last_page'    => $consultaspei->lastPage(),
                'from'         => $consultaspei->firstItem(),
                'to'           => $consultaspei->lastItem(),
            ],    
            'consultaspei' => $consultaspei
        ];
    }    

    public function store(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $consultaspei = new ConsultaSpei();

        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $consultaspei->fecha = $mytime->toDateTimeString();
            $consultaspei->reference = $request->reference;
            $consultaspei->fecha = $request->fecha;
            $consultaspei->codigo = $request->codigo;
            $consultaspei->mensaje = $request->mensaje;
            $consultaspei->parcial = $request->parcial;
            $consultaspei->save();
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
            $consultaspei = ConsultaSpei::findOrFail($request->id);
            if (!$this->consultaPerteneceUsuario($consultaspei)) {
                DB::rollBack();
                return $this->respuestaNoAutorizado($request);
            }
            $consultaspei->reference = $request->reference;
            $consultaspei->codigo = $request->codigo;
            $consultaspei->mensaje = $request->mensaje;
            $consultaspei->parcial = $request->parcial;
            $consultaspei->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
        }
    }
    
    public function delete(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $consultaspei = ConsultaSpei::findOrFail($request->id);
        if (!$this->consultaPerteneceUsuario($consultaspei)) {
            return $this->respuestaNoAutorizado($request);
        }
        $consultaspei->delete();
    }

    public function exportar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $consultaSpeiExport = new ConsultaSpeiExport();        
        return Excel::download($consultaSpeiExport, 'consultaspei.xlsx');
    }
}
