<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IncomingApiRequest extends Model
{
    protected $table = 'incoming_api_requests';

    protected $fillable = [
        'occurred_at',
        'method',
        'path',
        'route_action',
        'ip_address',
        'user_agent',
        'status_code',
        'success',
        'duration_ms',
        'request_headers',
        'request_payload',
        'response_body',
        'error_message',
        'idusuario',
        'idtransaccion',
        'correlation_reference',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'success' => 'boolean',
        'request_headers' => 'array',
        'request_payload' => 'array',
        'response_body' => 'array',
    ];
}
