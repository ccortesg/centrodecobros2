<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //protected $table = 'notifications';
    //protected $primaryKey = 'id';

    protected $fillable=['type','notifiable_id','notifiable_type','data'];
}
