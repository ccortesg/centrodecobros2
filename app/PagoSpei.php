<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PagoSpei extends Model
{
    protected $table = 'pagospei';
    protected $primaryKey = 'id';
    
    protected $fillable = [
                        'idtransaccion',
                        'fecha',
                        'clabe',
                        'fecha_peticion',
                        'monto',
                        'transaccion',
                        'fecha',
                        'codigo',
                        'autorizacion',
                        'mensaje',                        
                        'response',
                        'condicion',
                        'enviada'
                        ];

}