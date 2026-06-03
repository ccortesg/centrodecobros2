<?php

namespace App\Exports;

use App\TransaccionDom;
use Maatwebsite\Excel\Concerns\FromCollection;

class TransaccionDomExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = TransaccionDom::query();

        if (!\Auth::check() || (int) \Auth::user()->idrol !== 1) {
            $query->where('idusuario', '=', \Auth::user()->id)
                ->where('productivo', '=', \Auth::user()->productivo);
        }

        return $query->get();
    }
}
