<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CancelacionLector extends Model
{
    protected $table = 'cancelacionesLector';
    protected $primaryKey = 'id';
    
    protected $fillable = [
                        'folio',
                        'fecha',
                        'User',
                        'Password',
                        'IntegrationID',
                        'BusinessID',
                        'Reference',
                        'response',
                        'code',
                        'message',
                        'responseReference',
                        'idtransaccion',
                        'idusuario',
                        'productivo'                        
                        ];

}
