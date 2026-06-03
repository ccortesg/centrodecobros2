<?php

namespace App\Exports;

use App\Cliente;
use Maatwebsite\Excel\Concerns\FromCollection;

class ClienteExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = Cliente::query();

        if (!\Auth::check() || (int) \Auth::user()->idrol !== 1) {
            $query->where('idusuario', '=', \Auth::user()->id);
        }

        return $query->get();
    }
}
