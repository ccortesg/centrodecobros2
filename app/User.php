<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_ADMINISTRADOR = 1;
    public const ROLE_CLIENTE = 2;
    public const ROLE_CONSULTA_RESPUESTAS = 4;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'usuario',
        'password',
        'condicion',
        'idrol',
        'idusuario_vinculado',
        'token',
        'IntegrationID',
        'BusinessID',
        'productivo',
        'notificaPago',
        'ligaPago',        
        'recurrente',
        'ligaRecurrente'
    ];
    
    public $timestamps = false;

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'token',
    ];

    public function rol(){
        return $this->belongsTo('App\Rol');
    }

    public function persona(){
        return $this->belongsTo('App\Persona');
    }

    public function clienteVinculado()
    {
        return $this->belongsTo(self::class, 'idusuario_vinculado', 'id');
    }

    public function clienteVinculadoActivo()
    {
        if ((int) $this->idrol !== self::ROLE_CONSULTA_RESPUESTAS || !$this->idusuario_vinculado) {
            return null;
        }

        return $this->clienteVinculado()
            ->where('idrol', self::ROLE_CLIENTE)
            ->where('condicion', 1)
            ->first();
    }


}
