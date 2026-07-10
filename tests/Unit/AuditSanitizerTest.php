<?php

namespace Tests\Unit;

use App\Services\AuditSanitizer;
use PHPUnit\Framework\TestCase;

class AuditSanitizerTest extends TestCase
{
    public function test_it_masks_sensitive_headers_and_payload_fields(): void
    {
        $sanitizer = new AuditSanitizer();

        $headers = $sanitizer->sanitizeHeaders([
            'Authorization' => ['Bearer abc123'],
            'X-CSRF-TOKEN' => ['csrf-value'],
            'X-Soportetech-Signature' => ['sha256=super-secret-signature'],
            'Content-Type' => ['application/json'],
        ]);

        $payload = $sanitizer->sanitizePayload([
            'User' => 'cliente',
            'Password' => 'secret-password',
            'Clabe' => '012345678901234567',
            'nested' => [
                'number_tkn' => 'token-card',
                'cc_number' => '4111111111111111',
            ],
        ]);

        $this->assertSame('[secreto omitido]', $headers['Authorization']);
        $this->assertSame('[secreto omitido]', $headers['X-CSRF-TOKEN']);
        $this->assertSame('[secreto omitido]', $headers['X-Soportetech-Signature']);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('cliente', $payload['User']);
        $this->assertSame('[secreto omitido]', $payload['Password']);
        $this->assertStringEndsWith('4567', $payload['Clabe']);
        $this->assertStringNotContainsString('012345678901234567', $payload['Clabe']);
        $this->assertSame('[secreto omitido]', $payload['nested']['number_tkn']);
        $this->assertStringEndsWith('1111', $payload['nested']['cc_number']);
    }

    public function test_it_truncates_long_strings(): void
    {
        $sanitizer = new AuditSanitizer();

        $payload = $sanitizer->sanitizePayload([
            'message' => str_repeat('A', 2500),
        ]);

        $this->assertLessThan(2100, strlen($payload['message']));
        $this->assertStringContainsString('[truncado]', $payload['message']);
    }
}
