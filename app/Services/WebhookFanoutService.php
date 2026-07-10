<?php

namespace App\Services;

use App\WebhookDelivery;
use App\WebhookEndpoint;
use App\WebhookEvent;
use Illuminate\Support\Str;

class WebhookFanoutService
{
    public function fanout(WebhookEvent $event, bool $shadow = false): int
    {
        if ($event->idusuario === null) {
            $event->status = 'orphan';
            $event->save();
            return 0;
        }

        $endpoints = WebhookEndpoint::with(['subscriptions' => function ($query) use ($event) {
            $query->where('active', true)
                ->where('event_type', $event->event_type);
        }])
            ->where('idusuario', $event->idusuario)
            ->where('active', true)
            ->get()
            ->filter(function (WebhookEndpoint $endpoint) use ($event) {
                return $endpoint->subscriptions->contains(function ($subscription) use ($event) {
                    return $subscription->source_filter === 'all'
                        || $subscription->source_filter === (string) $event->source_context;
                });
            });

        $created = 0;

        foreach ($endpoints as $endpoint) {
            $rawBody = $this->rawBody($event, $endpoint);
            $delivery = WebhookDelivery::firstOrCreate(
                [
                    'webhook_event_id' => $event->id,
                    'webhook_endpoint_id' => $endpoint->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'status' => $shadow ? 'shadow' : 'pending',
                    'attempt_count' => 0,
                    'raw_body' => $rawBody,
                    'body_hash' => hash('sha256', $rawBody),
                    'is_test' => $event->event_type === 'webhook.endpoint.test',
                ]
            );

            if ($delivery->wasRecentlyCreated) {
                $created++;
            }

            if (!$shadow && in_array($delivery->status, ['pending', 'retrying'], true)) {
                DeliverWebhookJob::dispatch($delivery->id)
                    ->onConnection(config('webhooks.connection', 'database'))
                    ->onQueue(config('webhooks.queue', 'webhooks'));
            }
        }

        $event->status = $endpoints->isEmpty() ? 'no_subscribers' : ($shadow ? 'shadow' : 'queued');
        $event->save();

        return $created;
    }

    public function createTestDelivery(WebhookEvent $event, WebhookEndpoint $endpoint): WebhookDelivery
    {
        $rawBody = $this->rawBody($event, $endpoint);

        return WebhookDelivery::create([
            'id' => (string) Str::uuid(),
            'webhook_event_id' => $event->id,
            'webhook_endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'raw_body' => $rawBody,
            'body_hash' => hash('sha256', $rawBody),
            'is_test' => true,
        ]);
    }

    private function rawBody(WebhookEvent $event, WebhookEndpoint $endpoint): string
    {
        $payload = $event->payload ?: [];

        if ($endpoint->payload_mode === 'soportetech_v1') {
            $body = [
                'event_id' => $event->id,
                'event_type' => $event->event_type,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'source' => $event->source_context,
                'data' => $payload['source_payload'] ?? $payload['legacy_payload'] ?? [],
            ];
        } else {
            $body = $payload['legacy_payload'] ?? [];
        }

        return json_encode(
            $body,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }
}
