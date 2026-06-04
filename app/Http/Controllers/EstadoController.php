<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
//use Illuminate\Support\Facades\DB;
use App\Estado;

class EstadoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $buscar = $request->buscar ?? '';
        $criterio = $request->criterio ?? 'nombre';
        $offset = $this->offsetPaginacion($request->offset);
        $status = $request->status;

        if (!$this->criterioPermitido($criterio, ['nombre'])) {
            return response()->json(['message' => 'Criterio de busqueda invalido.'], 422);
        }

        $query = Estado::query();

        if ($status !== null && $status !== '' && (string) $status !== '99') {
            if (!in_array((string) $status, ['0', '1'], true)) {
                return response()->json(['message' => 'Status invalido.'], 422);
            }

            $query->where('condicion', '=', (int) $status);
        }

        if ($buscar !== ''){
            $query->where($criterio, 'like', '%'. $buscar . '%');
        }

        $estados = $query->orderBy('id', 'desc')->paginate($offset);


        return [
            'pagination' => [
                'total'        => $estados->total(),
                'current_page' => $estados->currentPage(),
                'per_page'     => $estados->perPage(),
                'last_page'    => $estados->lastPage(),
                'from'         => $estados->firstItem(),
                'to'           => $estados->lastItem(),
            ],
            'estados' => $estados
        ];
    }

    public function selectEstado(Request $request){
        if (!$request->ajax()) return redirect('/');
        $estados = Estado::where('condicion','=','1')
        ->select('id','nombre')->orderBy('nombre', 'asc')->get();
        return ['estados' => $estados];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $estado = new Estado();
        $estado->nombre = $request->nombre;
        $estado->condicion = '1';
        $estado->save();
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
        $estado = Estado::findOrFail($request->id);
        $estado->nombre = $request->nombre;
        $estado->condicion = '1';
        $estado->save();
    }

    public function desactivar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $estado = Estado::findOrFail($request->id);
        $estado->condicion = '0';
        $estado->save();
    }

    public function activar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');
        $estado = Estado::findOrFail($request->id);
        $estado->condicion = '1';
        $estado->save();
    }


}
