<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CancelacionDom extends Model
{
    protected $table = 'cancelacionesDom';
    protected $primaryKey = 'id';
    
    protected $fillable = [
                        'folio',
                        'fecha',
                        'User',
                        'Password',
                        'IntegrationID',
                        'BusinessID',
                        'Token',
                        'Tkn_reference',                        
                        'response',                        
                        'code',
                        'message',
                        'idusuario',
                        'productivo'                        
                        ];

}
