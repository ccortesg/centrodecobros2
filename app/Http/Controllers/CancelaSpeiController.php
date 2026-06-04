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
use App\CancelaSpei;

use App\Exports\CancelaSpeiExport;
use Excel;
use Exception;

class CancelaSpeiController extends Controller
{
    private function criteriosPermitidos()
    {
        return ['clabe', 'monto', 'transaccion', 'codigo', 'mensaje', 'autorizacion'];
    }

    private function filtroBinarioValido($value)
    {
        return in_array((string) $value, ['0', '1', '99'], true);
    }

    private function cancelacionPerteneceUsuario(CancelaSpei $cancelacion)
    {
        if ($this->usuarioEsAdministrador()) {
            return true;
        }

        return $this->usuarioPuedeOperarRegistro(Transaccion::find($cancelacion->idtransaccion));
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');               
        
        $buscar = $request->buscar;
        $criterio = $request->criterio;
        $offset = $this->offsetPaginacion($request->offset);
        $enviada = $request->enviada ?? 99;

        $query = CancelaSpei::leftjoin('transacciones', 'transacciones.id','cancelaspei.idtransaccion')
        ->leftjoin('clientes', 'clientes.id','transacciones.idcliente')
        ->select('cancelaspei.id','cancelaspei.idtransaccion','cancelaspei.fecha','cancelaspei.clabe','cancelaspei.fecha','cancelaspei.monto','cancelaspei.mensaje',
        'cancelaspei.transaccion','cancelaspei.codigo','clientes.razon_social as nombre_cliente','cancelaspei.fecha_peticion','cancelaspei.autorizacion',
        'cancelaspei.enviada');
        
        $this->aplicarScopePropietario($query, 'transacciones');
        
        if ($buscar!=''){
            if (!$this->criterioPermitido($criterio, $this->criteriosPermitidos())) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Criterio de búsqueda no permitido.',
                ], 422);
            }

            $query->where('cancelaspei.'.$criterio, 'like', '%'. $buscar . '%');    
        }

        if (!$this->filtroBinarioValido($enviada)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Filtro de enviada no permitido.',
            ], 422);
        }

        if ((string) $enviada !== '99') {
            $query->where('cancelaspei.enviada', '=', (int) $enviada);
        }

        $cancelaspei = $query->orderBy('cancelaspei.id', 'desc')->paginate($offset);

        return [       
            'pagination' => [
                'total'        => $cancelaspei->total(),
                'current_page' => $cancelaspei->currentPage(),
                'per_page'     => $cancelaspei->perPage(),
                'last_page'    => $cancelaspei->lastPage(),
                'from'         => $cancelaspei->firstItem(),
                'to'           => $cancelaspei->lastItem(),
            ],    
            'cancelaspei' => $cancelaspei
        ];
    }    

    public function store(Request $request)
    {        
        if (!$request->ajax()) return redirect('/');

        $cancelaspei = new CancelaSpei();

        try{
            DB::beginTransaction();
            $mytime= Carbon::now('America/Hermosillo');
            $cancelaspei->fecha = $mytime->toDateTimeString();
            $cancelaspei->clabe = $request->clabe;
            $cancelaspei->fecha_peticion = $request->fecha_peticion;
            $cancelaspei->monto = $request->monto;
            $cancelaspei->transaccion = $request->transaccion;
            $cancelaspei->codigo = $request->codigo;
            $cancelaspei->autorizacion = $request->autorizacion;
            $cancelaspei->mensaje = $request->mensaje;
            $cancelaspei->save();
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
            $cancelaspei = CancelaSpei::findOrFail($request->id);
            if (!$this->cancelacionPerteneceUsuario($cancelaspei)) {
                DB::rollBack();
                return $this->respuestaNoAutorizado($request);
            }
            $cancelaspei->clabe = $request->clabe;
            $cancelaspei->fecha_peticion = $request->fecha_peticion;
            $cancelaspei->monto = $request->monto;
            $cancelaspei->transaccion = $request->transaccion;
            $cancelaspei->codigo = $request->codigo;
            $cancelaspei->autorizacion = $request->autorizacion;
            $cancelaspei->mensaje = $request->mensaje;
            $cancelaspei->save();
            DB::commit();
        } catch (Exception $e){
            DB::rollBack();
        }
    }
    
    public function delete(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $cancelaspei = CancelaSpei::findOrFail($request->id);
        if (!$this->cancelacionPerteneceUsuario($cancelaspei)) {
            return $this->respuestaNoAutorizado($request);
        }
        $cancelaspei->delete();
    }

    public function exportar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $cancelaSpeiExport = new CancelaSpeiExport();        
        return Excel::download($cancelaSpeiExport, 'cancelaspei.xlsx');
    }
}
