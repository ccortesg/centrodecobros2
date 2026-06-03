<?php

namespace App\Exports;

use App\ConsultaSpei;
use Maatwebsite\Excel\Concerns\FromCollection;

class ConsultaSpeiExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = ConsultaSpei::leftJoin('transacciones', 'transacciones.id', '=', 'consultaspei.idtransaccion')
            ->select('consultaspei.*');

        if (!\Auth::check() || (int) \Auth::user()->idrol !== 1) {
            $query->where('transacciones.idusuario', '=', \Auth::user()->id)
                ->where('transacciones.productivo', '=', \Auth::user()->productivo);
        }

        return $query->get();
    }
}
