<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Archivo extends Model
{
    protected $table = 'archivos';
    protected $primaryKey = 'id';

    protected $fillable = ['idtransaccion','nombre','hasname','extension'];

    public function transaccion()
    {
        return $this->hasOne('App\Transaccion');
    }
}
