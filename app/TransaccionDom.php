<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TransaccionDom extends Model
{
    protected $table = 'transaccionesDom';
    protected $primaryKey = 'id';
    
    protected $fillable = [
                        'fecha',
                        'folio',
                        'idtransaccion',
                        'idcliente',
                        'User',
                        'Password',
                        'IntegrationID',
                        'BusinessID',
                        'Token',
                        'Reference',
                        'Amount',
                        'ExpMonth',
                        'ExpYear',
                        'response',
                        'code',
                        'message',
                        'response_reference',
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
                        'nb_street',
                        'cc_type',
                        'tp_operation',
                        'cc_name',
                        'cc_number',
                        'cc_expmonth',
                        'cc_expyear',
                        'response_amount',
                        'voucher',                        
                        'payment_type',                        
                        'response_token',
                        'idusuario',
                        'productivo'
                        ];

}
