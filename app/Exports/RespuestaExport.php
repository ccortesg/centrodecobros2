<?php

namespace App\Exports;

use App\Respuesta;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;

class RespuestaExport implements FromCollection
{
    private $tipo;
    private $buscar;
    private $criterio;
    private $status;
    private $fechaInicio;
    private $fechaFin;

    public function __construct($tipo = null, $buscar = '', $criterio = 'Reference', $status = '99', $fechaInicio = '', $fechaFin = '')
    {
        $this->tipo = $tipo;
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
        $query = Respuesta::leftJoin('transacciones', 'transacciones.id', '=', 'respuestas.idtransaccion')
            ->leftJoin('clientes', 'clientes.id', '=', 'transacciones.idcliente')
            ->select('respuestas.*');

        if (!\Auth::check() || (int) \Auth::user()->idrol !== 1) {
            $query->where('transacciones.idusuario', '=', \Auth::user()->id)
                ->where('transacciones.productivo', '=', \Auth::user()->productivo);
        }

        if ($this->tipo !== null && $this->tipo !== '') {
            $query->where('transacciones.tipo', '=', $this->tipo);
        }

        if ($this->buscar !== '') {
            if ($this->criterio === 'cliente_nombre') {
                $query->where('clientes.razon_social', 'like', '%' . $this->buscar . '%');
            } elseif (in_array($this->criterio, ['ClientReference', 'Reference', 'responseReference'], true)) {
                $query->where('transacciones.' . $this->criterio, 'like', '%' . $this->buscar . '%');
            } elseif ($this->criterio === 'autorizacion') {
                $query->where('respuestas.auth', 'like', '%' . $this->buscar . '%');
            } else {
                $query->where('respuestas.' . $this->criterio, 'like', '%' . $this->buscar . '%');
            }
        }

        if ((string) $this->status !== '99') {
            $query->where('respuestas.status', '=', $this->status);
        }

        if ($this->fechaInicio !== '' && $this->fechaFin !== '') {
            $query->whereBetween('respuestas.fecha', [
                Carbon::createFromFormat('Y-m-d', $this->fechaInicio)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $this->fechaFin)->endOfDay(),
            ]);
        } elseif ($this->fechaInicio !== '') {
            $query->where('respuestas.fecha', '>=', Carbon::createFromFormat('Y-m-d', $this->fechaInicio)->startOfDay());
        } elseif ($this->fechaFin !== '') {
            $query->where('respuestas.fecha', '<=', Carbon::createFromFormat('Y-m-d', $this->fechaFin)->endOfDay());
        }

        return $query->get();
    }
}
