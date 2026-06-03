<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConsultaSpei extends Model
{
    protected $table = 'consultaspei';
    protected $primaryKey = 'id';
    
    protected $fillable = [
                        'idtransaccion',
                        'fecha',
                        'reference',
                        'codigo',
                        'mensaje',
                        'parcial',
                        'response'                        
                        ];

}
