<?php

namespace App\Services;

use App\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FanoutWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private string $eventId;

    public function __construct(string $eventId)
    {
        $this->eventId = $eventId;
    }

    public function handle(WebhookFanoutService $fanout): void
    {
        $event = WebhookEvent::find($this->eventId);

        if ($event === null || in_array($event->status, ['delivered', 'cancelled'], true)) {
            return;
        }

        $fanout->fanout($event);
    }
}
