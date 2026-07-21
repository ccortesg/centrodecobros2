<?php

namespace Tests\Feature;

use App\Services\FanoutWebhookEventJob;
use App\Services\DeliverWebhookJob;
use App\Services\WebhookFanoutService;
use App\WebhookEndpoint;
use App\WebhookEndpointSubscription;
use App\WebhookEvent;
use App\WebhookUserSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class WebhookReplayResponseCommandTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedDatabase();
        Queue::fake();
        config([
            'webhooks.enabled' => true,
            'webhooks.connection' => 'database',
        ]);

        DB::table('transacciones')->where('id', 100)->update([
            'ClientReference' => 'dcc:donation:1111',
            'Amount' => 5000,
        ]);
        DB::table('respuestas')->where('id', 1)->update([
            'status' => 'approved',
            'amount' => 5000,
            'enviada' => 0,
        ]);

        WebhookUserSetting::create([
            'idusuario' => 2,
            'mode' => 'active',
            'hmac_enabled' => true,
            'hmac_secret' => 'replay-shared-secret-with-at-least-32-characters',
        ]);
        $endpoint = WebhookEndpoint::create([
            'idusuario' => 2,
            'name' => 'Donar con Causa legacy firmado',
            'url' => 'https://app.donarconcausa.org.mx/api/aplicaPago',
            'url_hash' => hash('sha256', 'https://app.donarconcausa.org.mx/api/aplicaPago'),
            'host' => 'app.donarconcausa.org.mx',
            'active' => true,
            'channel' => 'donation',
            'payload_mode' => 'legacy_exact',
            'ack_mode' => 'legacy_code_success',
            'rate_limit_per_minute' => 25,
        ]);
        WebhookEndpointSubscription::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => 'payment_link.payment.approved',
            'source_filter' => 'all',
            'active' => true,
        ]);
    }

    public function test_dry_run_does_not_publish_and_replay_is_idempotent(): void
    {
        $this->artisan('webhooks:replay-response', [
            'response-id' => 1,
            '--dry-run' => true,
        ])->assertSuccessful();
        $this->assertDatabaseCount('webhook_events', 0);
        Queue::assertNothingPushed();

        $this->artisan('webhooks:replay-response', ['response-id' => 1])->assertSuccessful();
        $this->artisan('webhooks:replay-response', ['response-id' => 1])->assertSuccessful();

        $this->assertDatabaseCount('webhook_events', 1);
        $this->assertDatabaseHas('webhook_events', [
            'idempotency_key' => 'payment_link.payment.approved:respuesta:1',
            'idtransaccion' => 100,
            'source_type' => 'respuesta',
            'source_id' => 1,
        ]);
        Queue::assertPushed(FanoutWebhookEventJob::class, 1);
    }

    public function test_replay_builds_canonical_peso_payload_without_sensitive_card_data(): void
    {
        DB::table('respuestas')->where('id', 1)->update([
            'cc_name' => 'NO ENVIAR',
            'cc_number' => '4111111111114242',
            'cc_expmonth' => '12',
            'cc_expyear' => '30',
        ]);

        $this->artisan('webhooks:replay-response', ['response-id' => 1])->assertSuccessful();
        $event = WebhookEvent::firstOrFail();
        app(WebhookFanoutService::class)->fanout($event);

        $delivery = $event->deliveries()->firstOrFail();
        $body = json_decode((string) $delivery->raw_body, true);
        $this->assertSame('dcc:donation:1111', $body['folio']);
        $this->assertSame(50.0, $body['monto']);
        $this->assertSame(100, $body['idtransaccion']);
        $this->assertArrayNotHasKey('cc_name', $body);
        $this->assertArrayNotHasKey('cc_number', $body);
        $this->assertArrayNotHasKey('cc_expmonth', $body);
        $this->assertArrayNotHasKey('cc_expyear', $body);
    }

    public function test_replay_rejects_non_approved_or_non_canonical_responses(): void
    {
        DB::table('respuestas')->where('id', 1)->update(['status' => 'rejected']);
        $this->artisan('webhooks:replay-response', ['response-id' => 1])->assertFailed();

        DB::table('respuestas')->where('id', 1)->update(['status' => 'approved']);
        DB::table('transacciones')->where('id', 100)->update(['ClientReference' => '1111']);
        $this->artisan('webhooks:replay-response', ['response-id' => 1])->assertFailed();

        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_force_requeues_a_dead_delivery_without_creating_another_event(): void
    {
        $this->artisan('webhooks:replay-response', ['response-id' => 1])->assertSuccessful();
        $event = WebhookEvent::firstOrFail();
        app(WebhookFanoutService::class)->fanout($event);
        $delivery = $event->deliveries()->firstOrFail();
        $delivery->update(['status' => 'dead', 'last_error' => 'timeout']);

        $this->artisan('webhooks:replay-response', [
            'response-id' => 1,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame('retrying', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->last_error);
        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(DeliverWebhookJob::class);
    }
}
