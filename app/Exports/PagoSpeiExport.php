<?php

namespace App\Exports;

use App\PagoSpei;
use Maatwebsite\Excel\Concerns\FromCollection;

class PagoSpeiExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = PagoSpei::leftJoin('transacciones', 'transacciones.id', '=', 'pagospei.idtransaccion')
            ->select('pagospei.*');

        if (!\Auth::check() || (int) \Auth::user()->idrol !== 1) {
            $query->where('transacciones.idusuario', '=', \Auth::user()->id)
                ->where('transacciones.productivo', '=', \Auth::user()->productivo);
        }

        return $query->get();
    }
}
