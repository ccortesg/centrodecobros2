<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'webhook_event_id',
        'webhook_endpoint_id',
        'status',
        'attempt_count',
        'next_attempt_at',
        'delivered_at',
        'last_status_code',
        'last_error',
        'raw_body',
        'body_hash',
        'is_test',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'next_attempt_at' => 'datetime',
        'delivered_at' => 'datetime',
        'raw_body' => 'encrypted',
        'is_test' => 'boolean',
    ];

    public function event()
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id')->withTrashed();
    }

    public function attempts()
    {
        return $this->hasMany(WebhookDeliveryAttempt::class, 'webhook_delivery_id');
    }
}
