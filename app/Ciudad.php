<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ciudad extends Model
{
    protected $table = 'ciudades';
    protected $primaryKey = 'id';

    protected $fillable =[
        'idestado','nombre','condicion'
    ];
    public function estados(){
        return $this->belongsTo('App\Estado');
    }
}
