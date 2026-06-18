<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Ciudad;

class CiudadController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $buscar = $request->buscar ?? '';
        $criterio = $request->criterio ?? 'nombre';
        $offset = $this->offsetPaginacion($request->offset);
        $status = $request->status;

        if (!$this->criterioPermitido($criterio, ['nombre', 'nombre_estado'])) {
            return response()->json(['message' => 'Criterio de busqueda invalido.'], 422);
        }

        $query = Ciudad::join('estados','ciudades.idestado','=','estados.id')
            ->select('ciudades.id','ciudades.idestado','ciudades.nombre','estados.nombre as nombre_estado','ciudades.condicion')
            ->orderBy('ciudades.id', 'desc');

        if ($status !== null && $status !== '' && (string) $status !== '99') {
            if (!in_array((string) $status, ['0', '1'], true)) {
                return response()->json(['message' => 'Status invalido.'], 422);
            }

            $query->where('ciudades.condicion', '=', (int) $status);
        }

        if ($buscar !== ''){
            if($criterio=='nombre_estado'){
                $query->where('estados.nombre', 'like', '%'. $buscar . '%');
            } else {
                $query->where('ciudades.'.$criterio, 'like', '%'. $buscar . '%');
            }
        }

        $ciudades = $query->paginate($offset);
        
        return [
            'pagination' => [
                'total'        => $ciudades->total(),
                'current_page' => $ciudades->currentPage(),
                'per_page'     => $ciudades->perPage(),
                'last_page'    => $ciudades->lastPage(),
                'from'         => $ciudades->firstItem(),
                'to'           => $ciudades->lastItem(),
            ],
            'ciudades' => $ciudades
        ];
    }

    public function listarCiudad(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $buscar = $request->buscar;
        $criterio = $request->criterio;
        
        if ($buscar==''){
            $ciudades = Ciudad::join('estados','ciudades.idestado','=','estados.id')
            ->select('ciudades.id','ciudades.idestado','ciudades.nombre','estados.nombre as nombre_estado','ciudades.condicion')
            ->orderBy('ciudades.id', 'desc')->paginate(10);
        }
        else{
            $ciudades = Ciudad::join('estados','ciudades.idestado','=','estados.id')
            ->select('ciudades.id','ciudades.idestado','ciudades.nombre','estados.nombre as nombre_estado','ciudades.condicion')
            ->where('ciudades.'.$criterio, 'like', '%'. $buscar . '%')
            ->orderBy('ciudades.id', 'desc')->paginate(10);
        }
        

        return ['ciudades' => $ciudades];
    }

    public function selectCiudad(Request $request){
        if (!$request->ajax()) return redirect('/');
        $ciudades = Ciudad::where('condicion','=','1')
        ->select('id','idestado','nombre')->orderBy('nombre', 'asc')->get();
        return ['ciudades' => $ciudades];
    }

    public function store(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $ciudad = new Ciudad();
        $ciudad->idestado = $request->idestado;
        $ciudad->nombre = $request->nombre;
        $ciudad->condicion = '1';
        $ciudad->save();
    }
    
    public function update(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $ciudad = Ciudad::findOrFail($request->id);
        $ciudad->idestado = $request->idestado;
        $ciudad->nombre = $request->nombre;
        $ciudad->condicion = '1';
        $ciudad->save();
    }

    public function desactivar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $ciudad = Ciudad::findOrFail($request->id);
        $ciudad->condicion = '0';
        $ciudad->save();
    }

    public function activar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $ciudad = Ciudad::findOrFail($request->id);
        $ciudad->condicion = '1';
        $ciudad->save();
    }

}
