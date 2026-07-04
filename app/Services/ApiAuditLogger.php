<?php

namespace App\Services;

use App\IncomingApiRequest;
use App\OutgoingApiRequest;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ApiAuditLogger
{
    private AuditSanitizer $sanitizer;

    public function __construct(AuditSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public function recordOutgoing(
        string $url,
        array $payload,
        ?ResponseInterface $response,
        ?Throwable $exception,
        float $startedAt,
        array $context = []
    ): void {
        try {
            $statusCode = $response ? $response->getStatusCode() : null;
            $host = parse_url($url, PHP_URL_HOST);
            $user = Auth::check() ? Auth::user() : null;

            $requestHeaders = $context['request_headers'] ?? ['Content-Type' => 'application/json'];
            if ($exception instanceof RequestException && $exception->getRequest()) {
                $requestHeaders = $exception->getRequest()->getHeaders();
            }

            OutgoingApiRequest::create([
                'occurred_at' => Carbon::now('America/Hermosillo'),
                'provider' => $context['provider'] ?? $this->providerFromUrl($url),
                'source_context' => $context['source_context'] ?? null,
                'method' => $context['method'] ?? 'POST',
                'url' => $url,
                'host' => $host,
                'status_code' => $statusCode,
                'success' => $statusCode !== null && $statusCode >= 200 && $statusCode < 300 && $exception === null,
                'duration_ms' => $this->durationMs($startedAt),
                'request_headers' => $this->sanitizer->sanitizeHeaders($requestHeaders),
                'request_payload' => $this->sanitizer->sanitizePayload($payload),
                'response_headers' => $response ? $this->sanitizer->sanitizeHeaders($response->getHeaders()) : null,
                'response_body' => $response ? $this->sanitizer->sanitizeRawBody((string) $response->getBody()) : null,
                'error_class' => $exception ? get_class($exception) : null,
                'error_message' => $exception ? $this->sanitizer->sanitizeString($exception->getMessage()) : null,
                'idusuario' => $context['idusuario'] ?? ($user->id ?? null),
                'idtransaccion' => $context['idtransaccion'] ?? null,
                'correlation_reference' => $context['correlation_reference'] ?? $this->referenceFromPayload($payload),
                'productivo' => $context['productivo'] ?? ($user->productivo ?? null),
            ]);
        } catch (Throwable $logException) {
            Log::warning('No se pudo guardar auditoria outgoing API.', [
                'error' => $logException->getMessage(),
            ]);
        }
    }

    public function recordIncoming(Request $request, $response, ?Throwable $exception, float $startedAt): void
    {
        try {
            $route = $request->route();
            $action = $route ? ($route->getActionName() ?: null) : null;
            $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : ($exception ? 500 : null);
            $user = $request->user();

            IncomingApiRequest::create([
                'occurred_at' => Carbon::now('America/Hermosillo'),
                'method' => $request->method(),
                'path' => trim($request->path(), '/'),
                'route_action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => $this->sanitizer->sanitizeString((string) $request->userAgent()),
                'status_code' => $statusCode,
                'success' => $statusCode !== null && $statusCode >= 200 && $statusCode < 400 && $exception === null,
                'duration_ms' => $this->durationMs($startedAt),
                'request_headers' => $this->sanitizer->sanitizeHeaders($request->headers->all()),
                'request_payload' => $this->sanitizer->sanitizePayload($this->requestPayload($request)),
                'response_body' => $this->responseBody($response),
                'error_message' => $exception ? $this->sanitizer->sanitizeString($exception->getMessage()) : null,
                'idusuario' => $user->id ?? null,
                'idtransaccion' => $this->transaccionIdFromRequest($request),
                'correlation_reference' => $this->referenceFromPayload($request->all()),
            ]);
        } catch (Throwable $logException) {
            Log::warning('No se pudo guardar auditoria incoming API.', [
                'error' => $logException->getMessage(),
            ]);
        }
    }

    private function providerFromUrl(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (stripos($host, 'pagadetodo') !== false) {
            return 'Pagadetodo';
        }

        return $host ?: 'Externo';
    }

    private function requestPayload(Request $request)
    {
        $content = (string) $request->getContent();

        if ($content !== '') {
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            return $content;
        }

        return $request->all();
    }

    private function responseBody($response)
    {
        if (!$response || !method_exists($response, 'getContent')) {
            return null;
        }

        return $this->sanitizer->sanitizeRawBody($response->getContent());
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function referenceFromPayload(array $payload): ?string
    {
        foreach (['Reference', 'reference', 'ClientReference', 'transaccion', 'Account'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return substr((string) $payload[$key], 0, 120);
            }
        }

        return null;
    }

    private function transaccionIdFromRequest(Request $request): ?int
    {
        $value = $request->input('idtransaccion') ?? $request->input('idtransaccion');

        return is_numeric($value) ? (int) $value : null;
    }
}
