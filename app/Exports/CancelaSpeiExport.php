<?php

namespace App\Exports;

use App\CancelaSpei;
use Maatwebsite\Excel\Concerns\FromCollection;

class CancelaSpeiExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = CancelaSpei::leftJoin('transacciones', 'transacciones.id', '=', 'cancelaspei.idtransaccion')
            ->select('cancelaspei.*');

        if (!\Auth::check() || (int) \Auth::user()->idrol !== 1) {
            $query->where('transacciones.idusuario', '=', \Auth::user()->id)
                ->where('transacciones.productivo', '=', \Auth::user()->productivo);
        }

        return $query->get();
    }
}
