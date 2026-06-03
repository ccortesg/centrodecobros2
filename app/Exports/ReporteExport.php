<?php

namespace App\Exports;

use App\Transaccion;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;

class ReporteExport implements FromCollection, WithHeadings
{
    public $atributos = [];
    public $fechaInicio;
    public $fechaFin;

    public function __construct()
    {                    
    }

    public function atributos($atributos) {
        $this->atributos = $atributos;
    }

    public function fechas($fechaInicio, $fechaFin) {
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = Transaccion::leftjoin('clientes','clientes.id','transacciones.idcliente')
        ->join('respuestas','respuestas.reference','transacciones.responseReference')
        ->leftjoin('users','users.id','transacciones.idusuario')
        ->select('transacciones.folio','transacciones.fecha','transacciones.Description','transacciones.Amount','transacciones.Reference',
        'transacciones.ClientReference','transacciones.responseReference','transacciones.Clabe',
        'clientes.razon_social','users.usuario','transacciones.frecuencia','transacciones.ProximoCargo','transacciones.condicion');        

        if(count($this->atributos) > 0) $query->where($this->atributos);

        if($this->fechaInicio != null) {
            $query->where(function ($query) {
                $query->whereBetween('transacciones.fecha', [$this->fechaInicio, $this->fechaFin]);
            });
        }

        $export = $query->get();

        return $export;
    }

    public function headings():array{
        return[
            'Folio',
            'Fecha',
            'Descripción',
            'Monto',            
            'Referencia',            
            'Referencia Interna',
            'Referencia Respuesta',            
            'CLABE',
            'Cliente',
            'Usuario',
            'Frecuencia',
            'Próximo Cargo',            
            'Status'
        ];
    } 
}
