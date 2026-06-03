<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    protected $table = 'estados';
    protected $primaryKey = 'id';

    protected $fillable = ['nombre','condicion'];

    public function ciudades()
    {
        return $this->hasMany('App\Ciudad');
    }
}
