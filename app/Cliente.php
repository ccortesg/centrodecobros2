<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';
    //protected $primaryKey = 'id';

    protected $fillable = [
        'id', 'idciudad','rfc', 'razon_social', 'contacto', 'telefono_contacto', 'email_contacto', 'banco', 
        'cuenta', 'clabe', 'cuenta_sucursal', 'cuenta_ciudad', 'forma_pago', 'plazo', 'regimen', 'idusuario'
    ];

    public $timestamps = false;

    public function persona()
    {
        return $this->belongsTo('App\Persona');
    }
}
