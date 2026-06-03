<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Codigo extends Model
{
    protected $table = 'codigos';
    protected $primaryKey = 'id';

    protected $fillable = ['nombre'];

    public function transaccion()
    {
        return $this->hasOne('App\Transaccion');
    }

}
