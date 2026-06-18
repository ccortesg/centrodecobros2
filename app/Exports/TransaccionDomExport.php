<?php

namespace App\Exports;

use App\TransaccionDom;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;

class TransaccionDomExport implements FromCollection
{
    private $buscar;
    private $criterio;
    private $status;
    private $fechaInicio;
    private $fechaFin;

    public function __construct($buscar = '', $criterio = 'Reference', $status = '99', $fechaInicio = '', $fechaFin = '')
    {
        $this->buscar = $buscar ?? '';
        $this->criterio = $criterio ?? 'Reference';
        $this->status = $status ?? '99';
        $this->fechaInicio = $fechaInicio ?? '';
        $this->fechaFin = $fechaFin ?? '';
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = TransaccionDom::leftJoin('transacciones', 'transacciones.id', '=', 'transaccionesDom.idtransaccion')
            ->leftJoin('clientes', 'clientes.id', '=', 'transaccionesDom.idcliente')
            ->select('transaccionesDom.*');

        if (!\Auth::check() || (int) \Auth::user()->idrol !== 1) {
            $query->where('transaccionesDom.idusuario', '=', \Auth::user()->id)
                ->where('transaccionesDom.productivo', '=', \Auth::user()->productivo);
        }

        if ($this->buscar !== '') {
            if ($this->criterio === 'cliente_nombre') {
                $query->where('clientes.razon_social', 'like', '%' . $this->buscar . '%');
            } elseif ($this->criterio === 'ClientReference') {
                $query->where('transacciones.ClientReference', 'like', '%' . $this->buscar . '%');
            } else {
                $query->where('transaccionesDom.' . $this->criterio, 'like', '%' . $this->buscar . '%');
            }
        }

        if ((string) $this->status !== '99') {
            $query->where('transaccionesDom.status', '=', $this->status);
        }

        if ($this->fechaInicio !== '' && $this->fechaFin !== '') {
            $query->whereBetween('transaccionesDom.fecha', [
                Carbon::createFromFormat('Y-m-d', $this->fechaInicio)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $this->fechaFin)->endOfDay(),
            ]);
        } elseif ($this->fechaInicio !== '') {
            $query->where('transaccionesDom.fecha', '>=', Carbon::createFromFormat('Y-m-d', $this->fechaInicio)->startOfDay());
        } elseif ($this->fechaFin !== '') {
            $query->where('transaccionesDom.fecha', '<=', Carbon::createFromFormat('Y-m-d', $this->fechaFin)->endOfDay());
        }

        return $query->get();
    }
}
