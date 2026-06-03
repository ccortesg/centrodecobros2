<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReporteTransaccionDomExport implements FromCollection, WithHeadings
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
                $transaccion->fecha,
                $transaccion->razon_social,
                $this->paymentTypeLabel($transaccion->PaymentTypes),
                $transaccion->Description,
                $transaccion->ClientReference,
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
            'Forma de pago',
            'Descripcion',
            'Referencia',
            'Monto',
        ];
    }

    private function paymentTypeLabel($paymentType): string
    {
        if ($paymentType === '401' || $paymentType === '41') {
            return 'Visa y Mastercard';
        }

        if ($paymentType === '1002' || $paymentType === '102') {
            return 'American Express';
        }

        return 'NA';
    }
}
