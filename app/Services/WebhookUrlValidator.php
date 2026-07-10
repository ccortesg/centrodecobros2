<?php

namespace App\Services;

class WebhookUrlValidator
{
    public function validate(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'La URL del endpoint no tiene un formato valido.';
        }

        $parts = parse_url($url);

        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return 'La URL del endpoint debe utilizar HTTPS.';
        }

        if (trim((string) ($parts['host'] ?? '')) === '') {
            return 'La URL del endpoint debe incluir un host valido.';
        }

        if (isset($parts['fragment'])) {
            return 'La URL del endpoint no debe contener fragmentos.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'La URL del endpoint no debe incluir credenciales.';
        }

        return null;
    }
}
