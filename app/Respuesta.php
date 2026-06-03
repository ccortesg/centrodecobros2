<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Respuesta extends Model
{
    protected $table = 'respuestas';
    protected $primaryKey = 'id';
    
    protected $fillable = [
                        'idtransaccion',
                        'fecha',
                        'reference',
                        'status',
                        'foliocpagos',
                        'auth',
                        'cd_response',
                        'cd_error',
                        'nb_error',
                        'time',
                        'date',
                        'nb_company',
                        'nb_merchant',
                        'cc_type',
                        'tp_operation',
                        'cc_name',
                        'cc_number',
                        'cc_expmonth',
                        'cc_expyear',
                        'amount',
                        'id_url',
                        'email',
                        'payment_type',
                        'promocion',
                        'number_tkn',
                        'cc_mask',
                        'response',
                        'enviada'
                        ];

}
