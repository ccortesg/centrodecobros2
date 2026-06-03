<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportePagoSpeiExport implements FromCollection, WithHeadings
{
    private Collection $transacciones;

    public function __construct(Collection $transacciones)
    {
        $this->transacciones = $transacciones;
    }

    public function collection()
    {
        return $this->transacciones->map(function ($transaccion) {
            return [
                $transaccion->folio,
                $transaccion->fechaPago,
                $transaccion->razon_social,
                $transaccion->Description,
                $transaccion->Clabe,
                round(((float) $transaccion->Amount) / 100, 2),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Folio',
            'Fecha',
            'Cliente',
            'Descripcion',
            'Clabe',
            'Monto',
        ];
    }
}
