<?php

namespace App\Services;

class WebhookSigner
{
    public function signature(string $secret, int $timestamp, string $eventId, string $rawBody): string
    {
        $canonical = $timestamp . '.' . $eventId . '.' . $rawBody;

        return 'sha256=' . hash_hmac('sha256', $canonical, $secret);
    }
}
