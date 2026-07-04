<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    protected $table = 'user_activity_logs';

    protected $fillable = [
        'occurred_at',
        'idusuario',
        'usuario',
        'idrol',
        'action',
        'success',
        'module_key',
        'module_name',
        'route_path',
        'ip_address',
        'user_agent',
        'session_id_hash',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'success' => 'boolean',
        'metadata' => 'array',
    ];
}
