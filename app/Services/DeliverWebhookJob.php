<?php

namespace App\Services;

use App\WebhookDelivery;
use App\WebhookEvent;
use App\WebhookUserSetting;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverWebhookJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 25;

    public int $uniqueFor = 86400;

    private string $deliveryId;

    public function __construct(string $deliveryId)
    {
        $this->deliveryId = $deliveryId;
    }

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    public function handle(WebhookRateLimiter $rateLimiter, WebhookDeliveryService $deliveryService): void
    {
        $delivery = WebhookDelivery::with(['event', 'endpoint'])->find($this->deliveryId);

        if ($delivery === null || in_array($delivery->status, ['delivered', 'dead', 'cancelled', 'shadow'], true)) {
            return;
        }

        $settings = $delivery->event->idusuario !== null
            ? WebhookUserSetting::where('idusuario', $delivery->event->idusuario)->first()
            : null;
        $mode = $settings->mode ?? 'legacy';

        if (!$delivery->endpoint || !$delivery->endpoint->active || (!$delivery->is_test && !in_array($mode, ['active', 'hybrid'], true))) {
            $delivery->update(['status' => 'cancelled', 'last_error' => 'Endpoint o cliente no activo.']);
            $this->syncEventStatus($delivery->event);
            return;
        }

        if ($delivery->is_test && !in_array($mode, ['shadow', 'active'], true)) {
            $delivery->update(['status' => 'cancelled', 'last_error' => 'El cliente debe estar en modo shadow o active.']);
            return;
        }

        $processingTimeout = max(30, (int) config('webhooks.processing_timeout', 60));
        $staleBefore = Carbon::now('America/Hermosillo')->subSeconds($processingTimeout);
        $claimed = WebhookDelivery::where('id', $delivery->id)
            ->where(function ($query) use ($staleBefore) {
                $query->whereIn('status', ['pending', 'retrying'])
                    ->orWhere(function ($query) use ($staleBefore) {
                        $query->where('status', 'processing')
                            ->where('updated_at', '<=', $staleBefore);
                    });
            })
            ->update(['status' => 'processing', 'updated_at' => Carbon::now('America/Hermosillo')]);

        if ($claimed !== 1) {
            return;
        }

        $delivery->refresh();

        try {
            $this->processClaimedDelivery($delivery, $rateLimiter, $deliveryService);
        } catch (Throwable $exception) {
            $current = WebhookDelivery::find($delivery->id);

            if ($current !== null && $current->status === 'processing') {
                $current->update([
                    'status' => 'retrying',
                    'next_attempt_at' => Carbon::now('America/Hermosillo')->addMinute(),
                    'last_error' => app(AuditSanitizer::class)->sanitizeString($exception->getMessage()),
                ]);
            }

            throw $exception;
        }
    }

    private function processClaimedDelivery(
        WebhookDelivery $delivery,
        WebhookRateLimiter $rateLimiter,
        WebhookDeliveryService $deliveryService
    ): void
    {
        $delay = $rateLimiter->acquire(
            $delivery->endpoint->host,
            (int) $delivery->endpoint->rate_limit_per_minute
        );

        if ($delay > 0) {
            $this->scheduleRetry($delivery, $delay, false);
            return;
        }

        $delivery->attempt_count = (int) $delivery->attempt_count + 1;
        $delivery->save();

        $result = $deliveryService->send($delivery);

        if ($result['success']) {
            $delivery->update([
                'status' => 'delivered',
                'next_attempt_at' => null,
                'delivered_at' => Carbon::now('America/Hermosillo'),
                'last_status_code' => $result['status_code'],
                'last_error' => null,
            ]);
            $this->syncEventStatus($delivery->event);
            return;
        }

        $maxAttempts = max(1, (int) config('webhooks.max_attempts', 8));
        if ($result['retryable'] && $delivery->attempt_count < $maxAttempts) {
            $retryDelay = $result['retry_after'] ?: $this->retryDelay($delivery->attempt_count);
            $delivery->last_status_code = $result['status_code'];
            $delivery->last_error = $result['error'];
            $delivery->save();
            $this->scheduleRetry($delivery, $retryDelay, true);
            return;
        }

        $delivery->update([
            'status' => 'dead',
            'next_attempt_at' => null,
            'last_status_code' => $result['status_code'],
            'last_error' => $result['error'],
        ]);
        $this->syncEventStatus($delivery->event);
    }

    public function failed(Throwable $exception): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if ($delivery !== null && !in_array($delivery->status, ['delivered', 'cancelled'], true)) {
            $delivery->update([
                'status' => 'dead',
                'next_attempt_at' => null,
                'last_error' => app(AuditSanitizer::class)->sanitizeString($exception->getMessage()),
            ]);

            if ($delivery->event !== null) {
                $this->syncEventStatus($delivery->event);
            }
        }
    }

    private function scheduleRetry(WebhookDelivery $delivery, int $delay, bool $countedAttempt): void
    {
        $delay = max(1, $delay);
        $delivery->update([
            'status' => 'retrying',
            'next_attempt_at' => Carbon::now('America/Hermosillo')->addSeconds($delay),
            'last_error' => $countedAttempt ? $delivery->last_error : 'Entrega diferida por limite de solicitudes.',
        ]);

        self::dispatch($delivery->id)
            ->onConnection(config('webhooks.connection', 'database'))
            ->onQueue(config('webhooks.queue', 'webhooks'))
            ->delay(now()->addSeconds($delay));
    }

    private function retryDelay(int $attempt): int
    {
        $delays = config('webhooks.retry_delays', [60, 300, 900, 3600, 10800, 21600, 43200]);

        return (int) ($delays[max(0, min($attempt - 1, count($delays) - 1))] ?? 43200);
    }

    private function syncEventStatus(WebhookEvent $event): void
    {
        $statuses = WebhookDelivery::where('webhook_event_id', $event->id)->pluck('status');

        if ($statuses->contains(function ($status) {
            return in_array($status, ['pending', 'processing', 'retrying'], true);
        })) {
            $event->update(['status' => 'queued']);
            return;
        }

        if ($statuses->contains('dead')) {
            $event->update(['status' => 'failed']);
            return;
        }

        if ($statuses->contains('delivered')) {
            $event->update(['status' => 'delivered']);
            $this->syncSourceSentFlag($event);
            return;
        }

        $event->update(['status' => 'cancelled']);
    }

    private function syncSourceSentFlag(WebhookEvent $event): void
    {
        if ($event->source_id === null) {
            return;
        }

        $table = [
            'respuesta' => 'respuestas',
            'transaccionDom' => 'transaccionesDom',
            'pagospei' => 'pagospei',
            'cancelaspei' => 'cancelaspei',
        ][$event->source_type] ?? null;

        if ($table !== null) {
            DB::table($table)->where('id', $event->source_id)->update(['enviada' => 1]);
        }
    }
}
