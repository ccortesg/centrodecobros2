<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookEndpoint extends Model
{
    use SoftDeletes;

    protected $table = 'webhook_endpoints';

    protected $fillable = [
        'idusuario',
        'name',
        'url',
        'url_hash',
        'host',
        'active',
        'payload_mode',
        'ack_mode',
        'rate_limit_per_minute',
    ];

    protected $casts = [
        'url' => 'encrypted',
        'active' => 'boolean',
        'rate_limit_per_minute' => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(WebhookEndpointSubscription::class, 'webhook_endpoint_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'idusuario');
    }
}
