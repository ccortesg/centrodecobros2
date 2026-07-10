<?php

namespace App\Services;

use App\WebhookDelivery;
use App\WebhookDeliveryAttempt;
use App\WebhookUserSetting;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class WebhookDeliveryService
{
    private WebhookSigner $signer;
    private AuditSanitizer $sanitizer;
    private ApiAuditLogger $auditLogger;
    private Client $client;

    public function __construct(
        WebhookSigner $signer,
        AuditSanitizer $sanitizer,
        ApiAuditLogger $auditLogger,
        Client $client
    ) {
        $this->signer = $signer;
        $this->sanitizer = $sanitizer;
        $this->auditLogger = $auditLogger;
        $this->client = $client;
    }

    public function send(WebhookDelivery $delivery): array
    {
        $delivery->loadMissing(['event', 'endpoint']);
        $event = $delivery->event;
        $endpoint = $delivery->endpoint;
        $rawBody = (string) $delivery->raw_body;
        $timestamp = Carbon::now('UTC')->timestamp;
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Soportetech-Event-Id' => $event->id,
            'X-Soportetech-Event-Type' => $event->event_type,
        ];

        $settings = $event->idusuario !== null
            ? WebhookUserSetting::where('idusuario', $event->idusuario)->first()
            : null;

        if ($settings && $settings->hmac_enabled) {
            $secret = (string) $settings->hmac_secret;
            if ($secret === '') {
                return $this->recordConfigurationFailure($delivery, $headers, $rawBody, 'El secreto HMAC no esta configurado.');
            }

            $headers['X-Soportetech-Timestamp'] = (string) $timestamp;
            $headers['X-Soportetech-Signature'] = $this->signer->signature(
                $secret,
                $timestamp,
                $event->id,
                $rawBody
            );
        }

        $startedAt = microtime(true);
        $response = null;
        $exception = null;

        try {
            $response = $this->client->request('POST', $endpoint->url, [
                'headers' => $headers,
                'body' => $rawBody,
                'connect_timeout' => (float) config('webhooks.connect_timeout', 5),
                'timeout' => (float) config('webhooks.timeout', 15),
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
        } catch (Throwable $caught) {
            $exception = $caught;
            if ($caught instanceof RequestException) {
                $response = $caught->getResponse();
            }
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $responseBody = $response ? (string) $response->getBody() : '';
        $statusCode = $response ? $response->getStatusCode() : null;
        $success = $this->acknowledged($endpoint->ack_mode, $statusCode, $responseBody, $exception);
        $retryable = !$success && $this->retryable($statusCode, $exception, $endpoint->ack_mode, $responseBody);
        $errorMessage = $this->errorMessage($statusCode, $responseBody, $exception);

        $payloadForAudit = json_decode($rawBody, true);
        if (!is_array($payloadForAudit)) {
            $payloadForAudit = ['raw_body' => $rawBody];
        }

        $this->auditLogger->recordOutgoing(
            $endpoint->url,
            $payloadForAudit,
            $response,
            $exception,
            $startedAt,
            [
                'provider' => 'Cliente',
                'source_context' => 'webhook_notification',
                'request_headers' => $headers,
                'idusuario' => $event->idusuario,
                'idtransaccion' => $event->idtransaccion,
                'correlation_reference' => $delivery->id,
            ]
        );

        WebhookDeliveryAttempt::create([
            'webhook_delivery_id' => $delivery->id,
            'attempted_at' => Carbon::now('America/Hermosillo'),
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'success' => $success,
            'request_headers' => $this->sanitizer->sanitizeHeaders($headers),
            'request_body' => $this->sanitizer->sanitizeRawBody($rawBody),
            'response_headers' => $response ? $this->sanitizer->sanitizeHeaders($response->getHeaders()) : null,
            'response_body' => $this->sanitizer->sanitizeRawBody($responseBody),
            'error_class' => $exception ? get_class($exception) : null,
            'error_message' => $errorMessage,
        ]);

        return [
            'success' => $success,
            'retryable' => $retryable,
            'status_code' => $statusCode,
            'error' => $errorMessage,
            'retry_after' => $this->retryAfter($response),
        ];
    }

    private function recordConfigurationFailure(
        WebhookDelivery $delivery,
        array $headers,
        string $rawBody,
        string $message
    ): array {
        WebhookDeliveryAttempt::create([
            'webhook_delivery_id' => $delivery->id,
            'attempted_at' => Carbon::now('America/Hermosillo'),
            'success' => false,
            'request_headers' => $this->sanitizer->sanitizeHeaders($headers),
            'request_body' => $this->sanitizer->sanitizeRawBody($rawBody),
            'error_class' => 'WebhookConfigurationException',
            'error_message' => $message,
        ]);

        return [
            'success' => false,
            'retryable' => false,
            'status_code' => null,
            'error' => $message,
            'retry_after' => null,
        ];
    }

    private function acknowledged(string $ackMode, ?int $statusCode, string $responseBody, ?Throwable $exception): bool
    {
        if ($exception !== null || $statusCode === null || $statusCode < 200 || $statusCode >= 300) {
            return false;
        }

        if ($ackMode === 'http_2xx') {
            return true;
        }

        $decoded = json_decode($responseBody, true);

        return is_array($decoded) && strtolower((string) ($decoded['code'] ?? '')) === 'success';
    }

    private function retryable(?int $statusCode, ?Throwable $exception, string $ackMode, string $responseBody): bool
    {
        if ($exception !== null || $statusCode === null) {
            return true;
        }

        if (in_array($statusCode, [408, 425, 429], true) || $statusCode >= 500) {
            return true;
        }

        return $statusCode >= 200
            && $statusCode < 300
            && $ackMode === 'legacy_code_success'
            && !$this->acknowledged($ackMode, $statusCode, $responseBody, null);
    }

    private function errorMessage(?int $statusCode, string $responseBody, ?Throwable $exception): ?string
    {
        if ($exception !== null) {
            return $this->sanitizer->sanitizeString($exception->getMessage());
        }

        $decoded = json_decode($responseBody, true);
        if (is_array($decoded)) {
            $message = $decoded['message'] ?? $decoded['msg'] ?? $decoded['error'] ?? null;
            if ($message !== null && $message !== '') {
                return $this->sanitizer->sanitizeString((string) $message);
            }
        }

        return $statusCode === null ? 'No se recibio respuesta.' : 'Respuesta HTTP ' . $statusCode . ' no confirmada.';
    }

    private function retryAfter(?ResponseInterface $response): ?int
    {
        if ($response === null || !$response->hasHeader('Retry-After')) {
            return null;
        }

        $value = trim($response->getHeaderLine('Retry-After'));

        if (ctype_digit($value)) {
            return max(1, min((int) $value, 86400));
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(1, min($timestamp - time(), 86400));
    }
}
