<?php

namespace Tests\Unit;

use App\Services\WebhookSigner;
use App\Services\WebhookUrlValidator;
use PHPUnit\Framework\TestCase;

class WebhookSecurityContractTest extends TestCase
{
    public function test_hmac_signature_uses_the_exact_documented_canonical_value(): void
    {
        $signer = new WebhookSigner();
        $secret = 'shared-secret-with-at-least-32-characters';
        $timestamp = 1783706400;
        $eventId = '018f47f0-e137-7c20-b105-9b2cfcb12345';
        $rawBody = '{"event":"payment_link.payment.approved","amount":150.25}';
        $canonical = $timestamp . '.' . $eventId . '.' . $rawBody;

        $this->assertSame(
            'sha256=' . hash_hmac('sha256', $canonical, $secret),
            $signer->signature($secret, $timestamp, $eventId, $rawBody)
        );
        $this->assertMatchesRegularExpression(
            '/^sha256=[0-9a-f]{64}$/',
            $signer->signature($secret, $timestamp, $eventId, $rawBody)
        );
    }

    public function test_signature_changes_when_raw_body_timestamp_or_event_id_changes(): void
    {
        $signer = new WebhookSigner();
        $secret = str_repeat('s', 32);
        $base = $signer->signature($secret, 100, 'event-1', '{"value":1}');

        $this->assertNotSame($base, $signer->signature($secret, 101, 'event-1', '{"value":1}'));
        $this->assertNotSame($base, $signer->signature($secret, 100, 'event-2', '{"value":1}'));
        $this->assertNotSame($base, $signer->signature($secret, 100, 'event-1', '{"value":1 }'));
    }

    public function test_url_validator_requires_https_but_allows_internal_https_hosts(): void
    {
        $validator = new WebhookUrlValidator();

        $this->assertNull($validator->validate('https://app.donarconcausa.org.mx/api/webhooks/pagos'));
        $this->assertNull($validator->validate('https://payments.internal.local/webhooks'));
        $this->assertNotNull($validator->validate('http://app.donarconcausa.org.mx/webhooks'));
        $this->assertNotNull($validator->validate('https://user:pass@app.donarconcausa.org.mx/webhooks'));
        $this->assertNotNull($validator->validate('https://app.donarconcausa.org.mx/webhooks#fragment'));
        $this->assertNotNull($validator->validate('not-a-url'));
    }
}
