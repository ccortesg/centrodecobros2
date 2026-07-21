<?php

namespace App\Services;

use App\User;
use App\WebhookEvent;
use App\WebhookUserSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WebhookEventPublisher
{
    private WebhookFanoutService $fanout;

    public function __construct(WebhookFanoutService $fanout)
    {
        $this->fanout = $fanout;
    }

    public function modeFor(?User $user): string
    {
        if (!config('webhooks.enabled', false)) {
            return 'legacy';
        }

        if ($user === null) {
            return 'orphan';
        }

        try {
            return (string) (WebhookUserSetting::where('idusuario', $user->id)->value('mode') ?: 'legacy');
        } catch (Throwable $exception) {
            Log::warning('No se pudo consultar el modo de notificaciones webhook; se conserva el flujo legacy.', [
                'idusuario' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return 'legacy';
        }
    }

    public function shouldUseLegacy(?User $user, ?string $eventType = null): bool
    {
        $mode = $this->modeFor($user);
        if (in_array($mode, ['legacy', 'shadow'], true)) {
            return true;
        }
        if ($mode !== 'hybrid' || $user === null || $eventType === null) {
            return false;
        }

        return !\App\WebhookEndpoint::query()
            ->where('idusuario', $user->id)
            ->where('active', true)
            ->whereHas('subscriptions', function ($query) use ($eventType) {
                $query->where('active', true)->where('event_type', $eventType);
            })
            ->exists();
    }

    public function publish(?User $user, string $eventType, array $legacyPayload, array $context = []): ?WebhookEvent
    {
        if (!config('webhooks.enabled', false) || !array_key_exists($eventType, config('webhooks.events', []))) {
            return null;
        }

        $mode = $this->modeFor($user);
        if (in_array($mode, ['legacy', 'disabled'], true)) {
            return null;
        }

        try {
            $sourceType = $context['source_type'] ?? null;
            $sourceId = isset($context['source_id']) ? (int) $context['source_id'] : null;
            $idempotencyKey = $context['idempotency_key'] ?? $this->idempotencyKey($eventType, $sourceType, $sourceId);
            $occurredAt = $context['occurred_at'] ?? Carbon::now('America/Hermosillo');

            $event = WebhookEvent::firstOrCreate(
                ['idempotency_key' => substr((string) $idempotencyKey, 0, 191)],
                [
                    'id' => (string) Str::uuid(),
                    'idusuario' => $user->id ?? null,
                    'idtransaccion' => $context['idtransaccion'] ?? null,
                    'event_type' => $eventType,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'source_context' => $context['source_context'] ?? null,
                    'payload' => [
                        'legacy_payload' => $legacyPayload,
                        'source_payload' => $context['source_payload'] ?? $legacyPayload,
                    ],
                    'status' => $mode === 'orphan' ? 'orphan' : 'created',
                    'occurred_at' => $occurredAt,
                ]
            );

            if ($mode === 'orphan') {
                return $event;
            }

            if ($mode === 'shadow') {
                $this->fanout->fanout($event, true);
                return $event;
            }

            FanoutWebhookEventJob::dispatch($event->id)
                ->onConnection(config('webhooks.connection', 'database'))
                ->onQueue(config('webhooks.queue', 'webhooks'));

            return $event;
        } catch (Throwable $exception) {
            Log::warning('No se pudo publicar evento webhook.', [
                'event_type' => $eventType,
                'idusuario' => $user->id ?? null,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function idempotencyKey(string $eventType, ?string $sourceType, ?int $sourceId): string
    {
        if ($sourceType !== null && $sourceId !== null) {
            return $eventType . ':' . $sourceType . ':' . $sourceId;
        }

        return $eventType . ':' . Str::uuid();
    }
}
