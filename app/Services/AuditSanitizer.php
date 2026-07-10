<?php

namespace App\Services;

class AuditSanitizer
{
    private const SECRET_PLACEHOLDER = '[secreto omitido]';
    private const MAX_STRING_LENGTH = 2000;
    private const MAX_DEPTH = 6;
    private const MAX_ARRAY_ITEMS = 100;

    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'x-soportetech-signature',
        'csrf',
        'password',
        'passwd',
        'token',
        'remember_token',
        'number_tkn',
        'number-tkn',
        'tkn_reference',
        'tkn-reference',
        'clabe',
        'cc_number',
        'cc-number',
        'cc_mask',
        'cc-mask',
        'card',
        'secret',
        'api_key',
        'apikey',
        'access_key',
        'private_key',
        'bearer',
    ];

    public function sanitizeHeaders($headers): array
    {
        return $this->sanitizeArray($this->normalizeHeaders($headers));
    }

    public function sanitizePayload($payload)
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->sanitizeValue($decoded);
            }
        }

        return $this->sanitizeValue($payload);
    }

    public function sanitizeRawBody($body)
    {
        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode((string) $body, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->sanitizeValue($decoded);
        }

        return $this->truncateString((string) $body);
    }

    public function sanitizeString($value): string
    {
        return $this->truncateString((string) $value);
    }

    private function sanitizeValue($value, int $depth = 0, $key = null)
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            $rawValue = is_array($value) || is_object($value)
                ? json_encode($value)
                : (string) $value;

            return $this->maskSensitiveValue((string) $rawValue, (string) $key);
        }

        if ($depth >= self::MAX_DEPTH) {
            return '[valor truncado por profundidad]';
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value, $depth);
        }

        if (is_object($value)) {
            return $this->sanitizeArray((array) $value, $depth);
        }

        if (is_string($value)) {
            return $this->truncateString($this->maskInlineSecrets($value));
        }

        return $value;
    }

    private function sanitizeArray(array $values, int $depth = 0): array
    {
        $sanitized = [];
        $count = 0;

        foreach ($values as $key => $value) {
            $count++;

            if ($count > self::MAX_ARRAY_ITEMS) {
                $sanitized['__truncated'] = 'Se omitieron elementos adicionales.';
                break;
            }

            $sanitized[$key] = $this->sanitizeValue($value, $depth + 1, $key);
        }

        return $sanitized;
    }

    private function normalizeHeaders($headers): array
    {
        if (!is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $key => $value) {
            if (is_array($value) && count($value) === 1) {
                $normalized[$key] = reset($value);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function isSensitiveKey($key): bool
    {
        $normalized = strtolower(str_replace(['_', ' '], ['-', '-'], (string) $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || strpos($normalized, $sensitiveKey) !== false) {
                return true;
            }
        }

        return false;
    }

    private function maskSensitiveValue(string $value, string $key): string
    {
        if ($value === '') {
            return '';
        }

        $normalized = strtolower($key);

        if (strpos($normalized, 'clabe') !== false || strpos($normalized, 'cc_number') !== false || strpos($normalized, 'card') !== false) {
            return $this->maskNumericValue($value);
        }

        return self::SECRET_PLACEHOLDER;
    }

    private function maskNumericValue(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return self::SECRET_PLACEHOLDER;
        }

        $lastFour = substr($digits, -4);

        return str_repeat('*', max(strlen($digits) - 4, 4)) . $lastFour;
    }

    private function maskInlineSecrets(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i', 'Bearer ' . self::SECRET_PLACEHOLDER, $value);
        $value = preg_replace('/(authorization|password|token|cookie)\s*[:=]\s*[^,\s\}]+/i', '$1=' . self::SECRET_PLACEHOLDER, $value);

        return $value;
    }

    private function truncateString(string $value): string
    {
        if (strlen($value) <= self::MAX_STRING_LENGTH) {
            return $value;
        }

        return substr($value, 0, self::MAX_STRING_LENGTH) . '... [truncado]';
    }
}
