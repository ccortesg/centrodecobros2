<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $table = 'webhook_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'idusuario',
        'idtransaccion',
        'event_type',
        'source_type',
        'source_id',
        'source_context',
        'idempotency_key',
        'payload',
        'status',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'encrypted:array',
        'occurred_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_event_id');
    }
}
