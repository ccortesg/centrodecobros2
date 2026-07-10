<?php

namespace Tests\Feature;

use App\Services\WebhookSigner;
use App\Services\WebhookEventPublisher;
use App\Services\DeliverWebhookJob;
use App\Services\WebhookDeliveryService;
use App\Services\WebhookRateLimiter;
use App\WebhookDelivery;
use App\WebhookDeliveryAttempt;
use App\WebhookEndpoint;
use App\WebhookEndpointSubscription;
use App\WebhookEvent;
use App\WebhookUserSetting;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class WebhookNotificationFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedDatabase();

        config([
            'webhooks.enabled' => true,
            'webhooks.connection' => 'sync',
            'queue.default' => 'sync',
        ]);
    }

    public function test_admin_can_manage_webhook_configuration_and_deliveries(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->actingAs($this->adminUser())
            ->get('/integraciones/webhooks/configuracion');

        $response->assertOk()->assertJsonPath('selected_user_id', 2);
        $this->assertSame([2, 3], collect($response->json('users'))->pluck('id')->all());

        $this->withHeaders($this->ajaxHeaders())
            ->actingAs($this->adminUser())
            ->get('/integraciones/webhooks/entregas')
            ->assertOk();
    }

    public function test_client_cannot_manage_webhook_configuration_or_deliveries(): void
    {
        $this->actingAs($this->clientAUser())
            ->getJson('/integraciones/webhooks/configuracion')
            ->assertStatus(403);

        $this->actingAs($this->clientAUser())
            ->getJson('/integraciones/webhooks/entregas')
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_manage_webhooks(): void
    {
        $this->getJson('/integraciones/webhooks/configuracion')
            ->assertStatus(401);

        $this->getJson('/integraciones/webhooks/entregas')
            ->assertStatus(401);
    }

    public function test_client_cannot_create_an_endpoint(): void
    {
        $this->actingAs($this->clientAUser())
            ->postJson('/integraciones/webhooks/endpoints', $this->endpointPayload(
                'https://app.donarconcausa.org.mx/webhooks',
                [['event_type' => 'payment_link.payment.approved', 'source_filter' => 'all']]
            ))
            ->assertStatus(403);
    }

    public function test_client_cannot_access_webhook_configuration_with_ajax_header(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->actingAs($this->clientAUser())
            ->get('/integraciones/webhooks/configuracion')
            ->assertStatus(403);
    }

    public function test_admin_can_store_hmac_settings_without_persisting_plaintext_secret(): void
    {
        $secret = 'donar-con-causa-shared-secret-2026-value';

        $this->actingAs($this->adminUser())
            ->postJson('/integraciones/webhooks/configuracion', [
                'user_id' => 2,
                'mode' => 'shadow',
                'hmac_enabled' => true,
                'hmac_secret' => $secret,
                'rotate_secret' => false,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('generated_secret', $secret);

        $setting = WebhookUserSetting::where('idusuario', 2)->firstOrFail();
        $rawSecret = DB::table('webhook_user_settings')->where('idusuario', 2)->value('hmac_secret');

        $this->assertSame($secret, $setting->hmac_secret);
        $this->assertNotSame($secret, $rawSecret);
        $this->assertSame(substr(hash('sha256', $secret), 0, 12), $setting->hmac_secret_fingerprint);
    }

    public function test_endpoint_requires_https_and_rejects_test_event_subscription(): void
    {
        $payload = $this->endpointPayload('http://app.donarconcausa.org.mx/webhooks', [
            ['event_type' => 'payment_link.payment.approved', 'source_filter' => 'all'],
        ]);

        $this->actingAs($this->adminUser())
            ->postJson('/integraciones/webhooks/endpoints', $payload)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $payload['url'] = 'https://app.donarconcausa.org.mx/webhooks';
        $payload['subscriptions'] = [
            ['event_type' => 'webhook.endpoint.test', 'source_filter' => 'all'],
        ];

        $this->actingAs($this->adminUser())
            ->postJson('/integraciones/webhooks/endpoints', $payload)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_existing_endpoint_cannot_be_reassigned_to_another_client(): void
    {
        $endpoint = $this->createEndpoint('payment_link.payment.approved');
        $payload = $this->endpointPayload(
            'https://app.donarconcausa.org.mx/webhooks/centro-de-cobros',
            [['event_type' => 'payment_link.payment.approved', 'source_filter' => 'all']]
        );
        $payload['user_id'] = 3;

        $this->actingAs($this->adminUser())
            ->putJson('/integraciones/webhooks/endpoints/' . $endpoint->id, $payload)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertSame(2, (int) $endpoint->fresh()->idusuario);
    }

    public function test_shadow_mode_creates_idempotent_event_and_delivery_without_http_request(): void
    {
        $this->configureClient('shadow', false);
        $this->createEndpoint('payment_link.payment.approved');
        DB::table('transacciones')->where('id', 100)->update(['responseReference' => 'SHADOW-PAYMENT']);

        $payload = [
            'reference' => 'SHADOW-PAYMENT',
            'response' => 'approved',
            'amount' => 10000,
            'cc_number' => '************4242',
        ];

        $this->postJson('/Service/EntregarPagoLiga', $payload)->assertOk();
        $this->postJson('/Service/EntregarPagoLiga', $payload)->assertOk();

        $this->assertSame(1, WebhookEvent::where('event_type', 'payment_link.payment.approved')->count());
        $this->assertSame(1, WebhookDelivery::where('status', 'shadow')->count());
        $this->assertSame(0, WebhookDeliveryAttempt::count());
    }

    public function test_shadow_preserves_legacy_callbacks_while_active_replaces_them(): void
    {
        $setting = $this->configureClient('shadow', false);
        $publisher = app(WebhookEventPublisher::class);

        $this->assertTrue($publisher->shouldUseLegacy($this->clientAUser()));

        $setting->update(['mode' => 'active']);

        $this->assertFalse($publisher->shouldUseLegacy($this->clientAUser()));
    }

    public function test_manual_recurrent_rejection_is_published_for_the_transaction_owner(): void
    {
        $this->configureClient('shadow', false);
        $this->createEndpoint(
            'recurring_charge.rejected',
            'legacy_exact',
            'http_2xx',
            'manual'
        );
        DB::table('respuestas')->where('idtransaccion', 200)->update([
            'number_tkn' => 'MOCK_REJECTED_CHARGE',
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->actingAs($this->adminUser())
            ->post('/transaccionDom/registrar', ['idtransaccion' => 200])
            ->assertOk();

        $this->assertDatabaseHas('transaccionesDom', [
            'idtransaccion' => 200,
            'code' => '05',
        ]);
        $this->assertDatabaseHas('webhook_events', [
            'idusuario' => 2,
            'idtransaccion' => 200,
            'event_type' => 'recurring_charge.rejected',
            'source_context' => 'manual',
        ]);
        $this->assertDatabaseHas('webhook_deliveries', ['status' => 'shadow']);
    }

    public function test_api_recurrent_rejection_is_published_before_returning_provider_result(): void
    {
        $this->configureClient('shadow', false);
        $this->createEndpoint(
            'recurring_charge.rejected',
            'legacy_exact',
            'http_2xx',
            'api'
        );
        DB::table('respuestas')->where('idtransaccion', 200)->update([
            'number_tkn' => 'MOCK_REJECTED_CHARGE',
        ]);

        $this->postJson('/CargoDomiciliacion', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'ClientReference' => 'DOM-A',
            'Amount' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('webhook_events', [
            'idusuario' => 2,
            'idtransaccion' => 200,
            'event_type' => 'recurring_charge.rejected',
            'source_context' => 'api',
        ]);
    }

    public function test_admin_cancellation_is_published_for_the_domiciliation_owner(): void
    {
        $this->configureClient('shadow', false);
        $this->createEndpoint('domiciliation.cancelled');

        $this->withHeaders($this->ajaxHeaders())
            ->actingAs($this->adminUser())
            ->put('/transaccion/rechazar', ['id' => 200])
            ->assertOk()
            ->assertJsonPath('error', '');

        $this->assertDatabaseHas('cancelacionesDom', [
            'idtransaccion' => 200,
            'idusuario' => 1,
        ]);
        $this->assertDatabaseHas('webhook_events', [
            'idusuario' => 2,
            'idtransaccion' => 200,
            'event_type' => 'domiciliation.cancelled',
            'source_type' => 'cancelacionesDom',
        ]);
    }

    public function test_active_hmac_delivery_signs_the_exact_unmodified_body_and_is_idempotent(): void
    {
        $secret = 'donar-con-causa-shared-secret-2026-value';
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"code":"success"}'),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));

        $this->configureClient('active', true, $secret);
        $this->createEndpoint('payment_link.payment.approved', 'soportetech_v1', 'http_2xx');
        DB::table('transacciones')->where('id', 100)->update(['responseReference' => 'HMAC-PAYMENT']);

        $payload = [
            'reference' => 'HMAC-PAYMENT',
            'response' => 'approved',
            'amount' => 15550,
            'cc_name' => 'NOMBRE RECIBIDO',
            'cc_number' => '************4242',
            'cc_expmonth' => '12',
            'cc_expyear' => '30',
        ];

        $this->postJson('/Service/EntregarPagoLiga', $payload)->assertOk();
        $this->postJson('/Service/EntregarPagoLiga', $payload)->assertOk();

        $this->assertCount(1, $history);
        $delivery = WebhookDelivery::firstOrFail();
        $event = WebhookEvent::firstOrFail();
        $request = $history[0]['request'];
        $rawBody = (string) $request->getBody();
        $decoded = json_decode($rawBody, true);
        $timestamp = (int) $request->getHeaderLine('X-Soportetech-Timestamp');

        $this->assertSame('delivered', $delivery->status);
        $this->assertSame($delivery->raw_body, $rawBody);
        $this->assertSame($event->id, $request->getHeaderLine('X-Soportetech-Event-Id'));
        $this->assertSame('payment_link.payment.approved', $request->getHeaderLine('X-Soportetech-Event-Type'));
        $this->assertSame('************4242', $decoded['data']['cc_number']);
        $this->assertSame('NOMBRE RECIBIDO', $decoded['data']['cc_name']);
        $this->assertSame(
            app(WebhookSigner::class)->signature($secret, $timestamp, $event->id, $rawBody),
            $request->getHeaderLine('X-Soportetech-Signature')
        );
        $this->assertSame(1, WebhookDeliveryAttempt::count());
        $this->assertSame(
            '[secreto omitido]',
            WebhookDeliveryAttempt::firstOrFail()->request_headers['X-Soportetech-Signature']
        );
    }

    public function test_delivery_detail_remains_available_after_endpoint_is_deleted(): void
    {
        $this->configureClient('shadow', false);
        $endpoint = $this->createEndpoint('payment_link.payment.approved');
        DB::table('transacciones')->where('id', 100)->update(['responseReference' => 'DELETED-ENDPOINT']);

        $this->postJson('/Service/EntregarPagoLiga', [
            'reference' => 'DELETED-ENDPOINT',
            'response' => 'approved',
            'amount' => 10000,
        ])->assertOk();

        $delivery = WebhookDelivery::firstOrFail();

        $this->actingAs($this->adminUser())
            ->deleteJson('/integraciones/webhooks/endpoints/' . $endpoint->id)
            ->assertOk();

        $this->actingAs($this->adminUser())
            ->getJson('/integraciones/webhooks/entregas/' . $delivery->id)
            ->assertOk()
            ->assertJsonPath('delivery.endpoint.name', 'Donar con Causa');
    }

    public function test_infrastructure_exception_returns_claimed_delivery_to_retrying(): void
    {
        $this->configureClient('active', false);
        $endpoint = $this->createEndpoint('payment_link.payment.approved');
        $event = WebhookEvent::create([
            'id' => '018f47f0-e137-7c20-b105-9b2cfcb12345',
            'idusuario' => 2,
            'idtransaccion' => 100,
            'event_type' => 'payment_link.payment.approved',
            'source_type' => 'respuesta',
            'source_id' => 1,
            'source_context' => 'webhook',
            'idempotency_key' => 'infrastructure-failure-test',
            'payload' => ['legacy_payload' => ['reference' => 'TEST']],
            'status' => 'queued',
            'occurred_at' => now(),
        ]);
        $delivery = WebhookDelivery::create([
            'id' => '018f47f0-e137-7c20-b105-9b2cfcb54321',
            'webhook_event_id' => $event->id,
            'webhook_endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'raw_body' => '{"reference":"TEST"}',
            'body_hash' => hash('sha256', '{"reference":"TEST"}'),
            'is_test' => false,
        ]);

        $rateLimiter = Mockery::mock(WebhookRateLimiter::class);
        $rateLimiter->shouldReceive('acquire')->once()->andReturn(0);
        $deliveryService = Mockery::mock(WebhookDeliveryService::class);
        $deliveryService->shouldReceive('send')->once()->andThrow(new RuntimeException('temporary infrastructure failure'));

        try {
            (new DeliverWebhookJob($delivery->id))->handle($rateLimiter, $deliveryService);
            $this->fail('La excepcion de infraestructura debio propagarse al worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('temporary infrastructure failure', $exception->getMessage());
        }

        $delivery->refresh();
        $this->assertSame('retrying', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->next_attempt_at);
        $this->assertSame('temporary infrastructure failure', $delivery->last_error);
    }

    public function test_stale_processing_delivery_can_be_reclaimed_after_worker_termination(): void
    {
        config(['webhooks.processing_timeout' => 60]);
        $this->configureClient('active', false);
        $endpoint = $this->createEndpoint('payment_link.payment.approved');
        $event = WebhookEvent::create([
            'id' => '018f47f0-e137-7c20-b105-9b2cfcb99991',
            'idusuario' => 2,
            'idtransaccion' => 100,
            'event_type' => 'payment_link.payment.approved',
            'source_type' => 'respuesta',
            'source_id' => 1,
            'source_context' => 'webhook',
            'idempotency_key' => 'stale-processing-test',
            'payload' => ['legacy_payload' => ['reference' => 'STALE']],
            'status' => 'queued',
            'occurred_at' => now(),
        ]);
        $delivery = WebhookDelivery::create([
            'id' => '018f47f0-e137-7c20-b105-9b2cfcb99992',
            'webhook_event_id' => $event->id,
            'webhook_endpoint_id' => $endpoint->id,
            'status' => 'processing',
            'attempt_count' => 0,
            'raw_body' => '{"reference":"STALE"}',
            'body_hash' => hash('sha256', '{"reference":"STALE"}'),
            'is_test' => false,
        ]);
        DB::table('webhook_deliveries')->where('id', $delivery->id)->update([
            'updated_at' => now()->subMinutes(2),
        ]);

        $rateLimiter = Mockery::mock(WebhookRateLimiter::class);
        $rateLimiter->shouldReceive('acquire')->once()->andReturn(0);
        $deliveryService = Mockery::mock(WebhookDeliveryService::class);
        $deliveryService->shouldReceive('send')->once()->andReturn([
            'success' => true,
            'retryable' => false,
            'status_code' => 200,
            'error' => null,
            'retry_after' => null,
        ]);

        (new DeliverWebhookJob($delivery->id))->handle($rateLimiter, $deliveryService);

        $delivery->refresh();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_admin_shell_contains_webhook_modules(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/main')
            ->assertOk()
            ->assertSee('Webhook Configuration')
            ->assertSee('Webhook Deliveries');
    }

    public function test_client_shell_does_not_contain_webhook_modules(): void
    {
        $this->actingAs($this->clientAUser())
            ->get('/main')
            ->assertOk()
            ->assertDontSee('Webhook Configuration')
            ->assertDontSee('Webhook Deliveries');
    }

    private function configureClient(string $mode, bool $hmacEnabled, ?string $secret = null): WebhookUserSetting
    {
        return WebhookUserSetting::create([
            'idusuario' => 2,
            'mode' => $mode,
            'hmac_enabled' => $hmacEnabled,
            'hmac_secret' => $secret,
            'hmac_secret_fingerprint' => $secret ? substr(hash('sha256', $secret), 0, 12) : null,
        ]);
    }

    private function createEndpoint(
        string $eventType,
        string $payloadMode = 'legacy_exact',
        string $ackMode = 'http_2xx',
        string $sourceFilter = 'all'
    ): WebhookEndpoint {
        $url = 'https://app.donarconcausa.org.mx/webhooks/centro-de-cobros';
        $endpoint = WebhookEndpoint::create([
            'idusuario' => 2,
            'name' => 'Donar con Causa',
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'host' => 'app.donarconcausa.org.mx',
            'active' => true,
            'payload_mode' => $payloadMode,
            'ack_mode' => $ackMode,
            'rate_limit_per_minute' => 25,
        ]);

        WebhookEndpointSubscription::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type' => $eventType,
            'source_filter' => $sourceFilter,
            'active' => true,
        ]);

        return $endpoint;
    }

    private function endpointPayload(string $url, array $subscriptions): array
    {
        return [
            'user_id' => 2,
            'name' => 'Donar con Causa',
            'url' => $url,
            'active' => true,
            'payload_mode' => 'soportetech_v1',
            'ack_mode' => 'http_2xx',
            'rate_limit_per_minute' => 25,
            'subscriptions' => $subscriptions,
        ];
    }
}
