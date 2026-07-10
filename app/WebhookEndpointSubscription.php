<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WebhookEndpointSubscription extends Model
{
    protected $table = 'webhook_endpoint_subscriptions';

    protected $fillable = [
        'webhook_endpoint_id',
        'event_type',
        'source_filter',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function endpoint()
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
