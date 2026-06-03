<?php

namespace App\Exports;

use App\Respuesta;
use Maatwebsite\Excel\Concerns\FromCollection;

class RespuestaExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = Respuesta::leftJoin('transacciones', 'transacciones.id', '=', 'respuestas.idtransaccion')
            ->select('respuestas.*');

        if (!\Auth::check() || (int) \Auth::user()->idrol !== 1) {
            $query->where('transacciones.idusuario', '=', \Auth::user()->id)
                ->where('transacciones.productivo', '=', \Auth::user()->productivo);
        }

        return $query->get();
    }
}
