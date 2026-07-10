<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WebhookUserSetting extends Model
{
    protected $table = 'webhook_user_settings';

    protected $fillable = [
        'idusuario',
        'mode',
        'hmac_enabled',
        'hmac_secret',
        'hmac_secret_fingerprint',
        'hmac_rotated_at',
    ];

    protected $hidden = [
        'hmac_secret',
    ];

    protected $casts = [
        'hmac_enabled' => 'boolean',
        'hmac_secret' => 'encrypted',
        'hmac_rotated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'idusuario');
    }
}
