<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WebhookDeliveryAttempt extends Model
{
    protected $table = 'webhook_delivery_attempts';

    protected $fillable = [
        'webhook_delivery_id',
        'attempted_at',
        'status_code',
        'duration_ms',
        'success',
        'request_headers',
        'request_body',
        'response_headers',
        'response_body',
        'error_class',
        'error_message',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'success' => 'boolean',
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
    ];
}
