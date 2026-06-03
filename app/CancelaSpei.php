<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CancelaSpei extends Model
{
    protected $table = 'cancelaspei';
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
                        'enviada'
                        ];

}