<?php

namespace App\Services;

use App\CancelacionDom;
use App\Respuesta;
use App\Transaccion;
use App\TransaccionDom;
use App\WebhookEvent;

class SupportTechV11PayloadBuilder
{
    public const EVENTS = [
        'payment_link.payment.approved',
        'payment_link.payment.rejected',
        'domiciliation_link.payment.approved',
        'domiciliation_link.payment.rejected',
        'domiciliation.activated',
        'domiciliation.activation_failed',
        'recurring_charge.approved',
        'recurring_charge.rejected',
        'recurring_charge.error',
        'domiciliation.cancelled',
        'domiciliation.cancellation_failed',
        'webhook.endpoint.test',
    ];

    private const RECURRING_EVENTS = [
        'domiciliation_link.payment.approved',
        'domiciliation_link.payment.rejected',
        'domiciliation.activated',
        'domiciliation.activation_failed',
        'recurring_charge.approved',
        'recurring_charge.rejected',
        'recurring_charge.error',
        'domiciliation.cancelled',
        'domiciliation.cancellation_failed',
    ];

    private const MONETARY_EVENTS = [
        'payment_link.payment.approved',
        'payment_link.payment.rejected',
        'domiciliation_link.payment.approved',
        'domiciliation_link.payment.rejected',
        'recurring_charge.approved',
        'recurring_charge.rejected',
        'recurring_charge.error',
    ];

    public function build(WebhookEvent $event): array
    {
        if (!in_array($event->event_type, self::EVENTS, true)) {
            throw new \RuntimeException('El evento no pertenece al contrato de donaciones V1.1.');
        }

        if ($event->event_type === 'webhook.endpoint.test') {
            return [
                'schema_version' => '1.1',
                'resource_type' => 'donation',
                'provider_status' => 'test',
                'code' => 'test',
                'message' => 'Prueba de conectividad SOPORTETECH V1.1.',
            ];
        }

        $transaction = $event->idtransaccion ? Transaccion::find($event->idtransaccion) : null;
        if (!$transaction) {
            throw new \RuntimeException('No se encontro la transaccion fuente para construir V1.1.');
        }

        $source = $this->source($event);
        $legacy = (array) data_get($event->payload, 'legacy_payload', []);
        $data = [
            'schema_version' => '1.1',
            'resource_type' => 'donation',
            'client_reference' => $this->clientReference((string) $transaction->ClientReference),
            'supporttech_transaction_id' => (int) $transaction->id,
            'provider_reference' => $this->providerReference($transaction, $source),
            'provider_status' => $this->providerStatus($transaction, $source, $legacy),
            'code' => $this->value($source, 'code', $legacy['code'] ?? $transaction->code),
            'message' => $this->sanitizeMessage($this->value($source, 'message', $legacy['message'] ?? $transaction->message)),
        ];

        if (in_array($event->event_type, self::MONETARY_EVENTS, true)) {
            $data['currency'] = 'MXN';
            $data['amount_minor'] = $this->amountMinor($transaction, $source);
        }

        if (in_array($event->event_type, self::RECURRING_EVENTS, true)) {
            $data['subscription_reference'] = 'st:domiciliation:' . $transaction->id;
        }

        if (str_starts_with($event->event_type, 'recurring_charge.')) {
            $data['charge_reference'] = 'st:charge:' . ($event->source_id ?: $event->id);
            $data['rejected_attempts'] = max(0, (int) $transaction->intentos);
        }

        if (str_starts_with($event->event_type, 'domiciliation.cancellation')) {
            $payload = (array) data_get($event->payload, 'legacy_payload', []);
            $data['reason_code'] = $payload['reason_code']
                ?? $transaction->cancellation_reason
                ?? ((int) $transaction->intentos >= (int) config('webhooks.max_rejected_attempts', 3)
                    ? 'max_rejected_attempts'
                    : 'other');
            $data['rejected_attempts'] = max(0, (int) $transaction->intentos);
            if ($event->event_type === 'domiciliation.cancelled') {
                $cancelledAt = $transaction->cancelled_at ?: $event->occurred_at;
                $data['cancelled_at'] = \Carbon\Carbon::parse($cancelledAt)->toIso8601String();
            }
        }

        $brand = trim((string) $this->value($source, 'cc_type'));
        if ($brand !== '') {
            $data['card_brand'] = substr($brand, 0, 30);
        }
        $lastFour = $this->lastFour($this->value($source, 'cc_number'));
        if ($lastFour !== null) {
            $data['card_last_four'] = $lastFour;
        }

        return array_filter($data, static fn ($value) => $value !== null && $value !== '');
    }

    private function source(WebhookEvent $event)
    {
        return match ($event->source_type) {
            'respuesta' => Respuesta::find($event->source_id),
            'transaccionDom' => TransaccionDom::find($event->source_id),
            'cancelacionesDom' => CancelacionDom::find($event->source_id),
            default => null,
        };
    }

    private function clientReference(string $reference): string
    {
        $reference = trim($reference);
        if (preg_match('/^dcc:donation:[1-9][0-9]*$/', $reference)) {
            return $reference;
        }
        if (ctype_digit($reference) && (int) $reference > 0) {
            return 'dcc:donation:' . (int) $reference;
        }

        throw new \RuntimeException('ClientReference no cumple el contrato dcc:donation:{id}.');
    }

    private function amountMinor(Transaccion $transaction, $source): int
    {
        $amount = $this->value($source, 'response_amount');
        if ($amount === null || $amount === '') {
            $amount = $this->value($source, 'amount');
        }
        if ($amount === null || $amount === '') {
            $amount = $this->value($source, 'Amount', $transaction->Amount);
        }

        if (!is_numeric($amount)) {
            throw new \RuntimeException('No se pudo determinar amount_minor.');
        }

        return max(0, (int) round((float) $amount));
    }

    private function providerReference(Transaccion $transaction, $source): ?string
    {
        return $this->nullableString(
            $this->value($source, 'response_reference')
                ?: $this->value($source, 'reference')
                ?: $this->value($source, 'Reference')
                ?: $transaction->responseReference
        );
    }

    private function providerStatus(Transaccion $transaction, $source, array $legacy): ?string
    {
        return $this->nullableString(
            $this->value($source, 'status')
                ?: $this->value($source, 'response')
                ?: ($legacy['provider_status'] ?? $legacy['status'] ?? null)
                ?: $transaction->condicion
        );
    }

    private function value($source, string $field, $default = null)
    {
        if ($source && isset($source->{$field})) {
            return $source->{$field};
        }

        return $default;
    }

    private function sanitizeMessage($message): ?string
    {
        $message = trim(strip_tags((string) $message));

        return $message === '' ? null : mb_substr($message, 0, 250);
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 191);
    }

    private function lastFour($value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return strlen($digits) < 4 ? null : substr($digits, -4);
    }
}
