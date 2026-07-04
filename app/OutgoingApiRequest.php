<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OutgoingApiRequest extends Model
{
    protected $table = 'outgoing_api_requests';

    protected $fillable = [
        'occurred_at',
        'provider',
        'source_context',
        'method',
        'url',
        'host',
        'status_code',
        'success',
        'duration_ms',
        'request_headers',
        'request_payload',
        'response_headers',
        'response_body',
        'error_class',
        'error_message',
        'idusuario',
        'idtransaccion',
        'correlation_reference',
        'productivo',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'success' => 'boolean',
        'request_headers' => 'array',
        'request_payload' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
    ];
}
