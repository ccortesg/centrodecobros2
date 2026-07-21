<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaccion extends Model
{
    protected $table = 'transacciones';
    protected $primaryKey = 'id';
    
    protected $fillable = [
                        'folio',
                        'fecha',                        
                        'User',
                        'Password',
                        'IntegrationID',
                        'BusinessID',
                        'PaymentTypes',
                        'IdReference',
                        'Description',
                        'Amount',
                        'Reference',
                        'ExpirationDate',
                        'ClientReference',
                        'response',
                        'url',
                        'code',
                        'message',
                        'responseReference',
                        'referenceEmisor',
                        'Error',
                        'Date',
                        'Clabe',
                        'codeQR',
                        'idusuario',
                        'idcliente',
                        'tipo',
                        'frecuencia',
                        'ProximoCargo',
                        'ProximoCargoBase',
                        'intentos',
                        'domiciliation_status',
                        'cancellation_reason',
                        'cancellation_idempotency_key',
                        'cancellation_attempts',
                        'cancellation_requested_at',
                        'cancellation_last_attempt_at',
                        'cancelled_at',
                        'condicion',
                        'productivo'
                        ];

}
