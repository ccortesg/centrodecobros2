<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PagoRecibido extends Model
{
    protected $table = 'pagos_recibidos';
    protected $primaryKey = 'id';

    protected $fillable = [
        'source_type',
        'source_id',
        'status',
        'idusuario',
    ];
}
