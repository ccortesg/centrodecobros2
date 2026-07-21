<?php

namespace App\Console\Commands;

use App\Respuesta;
use App\Services\DeliverWebhookJob;
use App\Services\WebhookEventPublisher;
use App\Transaccion;
use App\User;
use App\WebhookEndpoint;
use Illuminate\Console\Command;

class WebhookReplayResponse extends Command
{
    protected $signature = 'webhooks:replay-response
        {response-id : ID de la respuesta aprobada}
        {--dry-run : Validar y mostrar el evento sin publicarlo}
        {--force : Permitir una respuesta enviada y reencolar entregas fallidas}';

    protected $description = 'Republica de forma idempotente un pago unico aprobado al webhook de donaciones.';

    public function handle(WebhookEventPublisher $publisher): int
    {
        $responseId = filter_var($this->argument('response-id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($responseId === false) {
            $this->error('response-id debe ser un entero positivo.');
            return self::FAILURE;
        }

        $response = Respuesta::find($responseId);
        if ($response === null) {
            $this->error('No se encontro la respuesta solicitada.');
            return self::FAILURE;
        }
        if (strtolower((string) $response->status) !== 'approved') {
            $this->error('Solo se pueden republicar respuestas aprobadas.');
            return self::FAILURE;
        }
        if ((int) $response->enviada === 1 && !$this->option('force')) {
            $this->error('La respuesta ya esta marcada como enviada. Use --force solo tras verificar idempotencia en el receptor.');
            return self::FAILURE;
        }

        $transaction = Transaccion::find($response->idtransaccion);
        if ($transaction === null || (int) $transaction->tipo !== 1) {
            $this->error('La respuesta no pertenece a una liga de pago unico.');
            return self::FAILURE;
        }
        if (!preg_match('/^dcc:donation:[1-9][0-9]*$/', (string) $transaction->ClientReference)) {
            $this->error('ClientReference no cumple el contrato dcc:donation:{id}.');
            return self::FAILURE;
        }

        $amount = round(((float) $transaction->Amount) / 100, 2);
        if ($amount <= 0) {
            $this->error('El monto de la transaccion no es valido.');
            return self::FAILURE;
        }

        $user = User::find($transaction->idusuario);
        if ($user === null) {
            $this->error('No se encontro el propietario de la transaccion.');
            return self::FAILURE;
        }

        $payload = [
            'folio' => (string) $transaction->ClientReference,
            'monto' => $amount,
            'idtransaccion' => $transaction->id,
            'reference' => (string) $response->reference,
            'status' => 'approved',
        ];
        $idempotencyKey = 'payment_link.payment.approved:respuesta:'.$response->id;
        $mode = $publisher->modeFor($user);
        $hasDonationEndpoint = WebhookEndpoint::query()
            ->where('idusuario', $user->id)
            ->where('active', true)
            ->where('channel', 'donation')
            ->where('payload_mode', 'legacy_exact')
            ->whereHas('subscriptions', function ($query) {
                $query->where('active', true)
                    ->where('event_type', 'payment_link.payment.approved');
            })
            ->exists();

        $this->table(['Dato', 'Valor'], [
            ['response_id', $response->id],
            ['transaction_id', $transaction->id],
            ['client_reference', $transaction->ClientReference],
            ['amount_mxn', number_format($amount, 2, '.', '')],
            ['idempotency_key', $idempotencyKey],
            ['mode', $mode],
            ['donation_endpoint', $hasDonationEndpoint ? 'configured' : 'missing'],
        ]);

        if (!in_array($mode, ['active', 'hybrid'], true) || !$hasDonationEndpoint) {
            $this->error('El cliente requiere modo active|hybrid y un endpoint donation/legacy_exact suscrito al pago aprobado.');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Validacion completada. No se modificaron datos ni se encolaron entregas.');
            return self::SUCCESS;
        }

        $event = $publisher->publish($user, 'payment_link.payment.approved', $payload, [
            'idtransaccion' => $transaction->id,
            'source_type' => 'respuesta',
            'source_id' => $response->id,
            'source_context' => 'replay',
            'source_payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => $response->fecha,
        ]);

        if ($event === null) {
            $this->error('No se publico el evento. Verifique WEBHOOKS_ENABLED y que el cliente este en modo active o hybrid.');
            return self::FAILURE;
        }

        if ($this->option('force')) {
            $event->deliveries()
                ->where('status', 'dead')
                ->get()
                ->each(function ($delivery) {
                    $delivery->update([
                        'status' => 'retrying',
                        'next_attempt_at' => null,
                        'last_error' => null,
                    ]);
                    DeliverWebhookJob::dispatch($delivery->id)
                        ->onConnection(config('webhooks.connection', 'database'))
                        ->onQueue(config('webhooks.queue', 'webhooks'));
                });
        }

        $this->info('Evento '.$event->id.' publicado. La respuesta se marcara enviada solo despues del ACK exitoso.');

        return self::SUCCESS;
    }
}
