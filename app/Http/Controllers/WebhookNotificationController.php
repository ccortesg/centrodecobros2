<?php

namespace App\Http\Controllers;

use App\Exports\IntegrationAuditExport;
use App\Services\AuditSanitizer;
use App\Services\DeliverWebhookJob;
use App\Services\UserActivityLogger;
use App\Services\WebhookFanoutService;
use App\Services\WebhookUrlValidator;
use App\User;
use App\WebhookDelivery;
use App\WebhookEndpoint;
use App\WebhookEndpointSubscription;
use App\WebhookEvent;
use App\WebhookUserSetting;
use Carbon\Carbon;
use Excel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookNotificationController extends Controller
{
    public function configuration(Request $request)
    {
        if (!$request->ajax()) {
            return redirect('/');
        }

        $users = User::leftJoin('personas', 'personas.id', '=', 'users.id')
            ->select('users.id', 'users.usuario', 'users.condicion', 'personas.nombre')
            ->where('users.idrol', '=', 2)
            ->orderBy('users.usuario')
            ->get();

        $requestedUserId = (int) $request->user_id;
        $userId = $users->contains('id', $requestedUserId)
            ? $requestedUserId
            : (int) ($users->first()->id ?? 0);
        $setting = WebhookUserSetting::where('idusuario', $userId)->first();
        $endpoints = WebhookEndpoint::with(['subscriptions' => function ($query) {
            $query->where('active', true)->orderBy('event_type');
        }])
            ->where('idusuario', $userId)
            ->orderBy('name')
            ->get();

        return [
            'system_enabled' => (bool) config('webhooks.enabled', false),
            'users' => $users,
            'selected_user_id' => $userId,
            'setting' => [
                'mode' => $setting->mode ?? 'legacy',
                'hmac_enabled' => (bool) ($setting->hmac_enabled ?? false),
                'hmac_configured' => $setting !== null && trim((string) $setting->hmac_secret) !== '',
                'hmac_secret_fingerprint' => $setting->hmac_secret_fingerprint ?? null,
                'hmac_rotated_at' => $setting && $setting->hmac_rotated_at
                    ? $setting->hmac_rotated_at->toIso8601String()
                    : null,
            ],
            'endpoints' => $endpoints,
            'events' => $this->eventCatalog(),
        ];
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('idrol', 2);
                }),
            ],
            'mode' => ['required', Rule::in(['legacy', 'shadow', 'hybrid', 'active', 'disabled'])],
            'hmac_enabled' => 'required|boolean',
            'hmac_secret' => 'nullable|string|min:32|max:255',
            'rotate_secret' => 'nullable|boolean',
        ]);

        $setting = WebhookUserSetting::firstOrNew(['idusuario' => $validated['user_id']]);
        $setting->mode = $validated['mode'];
        $setting->hmac_enabled = (bool) $validated['hmac_enabled'];
        $generatedSecret = null;

        if ($setting->hmac_enabled) {
            $mustSetSecret = !$setting->exists
                || trim((string) $setting->hmac_secret) === ''
                || (bool) ($validated['rotate_secret'] ?? false)
                || trim((string) ($validated['hmac_secret'] ?? '')) !== '';

            if ($mustSetSecret) {
                $generatedSecret = trim((string) ($validated['hmac_secret'] ?? ''));
                if ($generatedSecret === '') {
                    $generatedSecret = bin2hex(random_bytes(32));
                }
                $setting->hmac_secret = $generatedSecret;
                $setting->hmac_secret_fingerprint = substr(hash('sha256', $generatedSecret), 0, 12);
                $setting->hmac_rotated_at = Carbon::now('America/Hermosillo');
            }
        }

        $setting->save();

        app(UserActivityLogger::class)->log($request, 'webhook_settings_updated', true, null, [
            'target_user_id' => (int) $validated['user_id'],
            'mode' => $validated['mode'],
            'hmac_enabled' => (bool) $validated['hmac_enabled'],
            'secret_rotated' => $generatedSecret !== null,
        ]);

        return response()->json([
            'status' => 'success',
            'generated_secret' => $generatedSecret,
            'fingerprint' => $setting->hmac_secret_fingerprint,
        ]);
    }

    public function storeEndpoint(Request $request, WebhookUrlValidator $urlValidator)
    {
        $data = $this->validateEndpoint($request, $urlValidator);

        $endpoint = DB::transaction(function () use ($data) {
            $endpoint = WebhookEndpoint::create($this->endpointAttributes($data));
            $this->syncSubscriptions($endpoint, $data['subscriptions']);

            return $endpoint;
        });

        app(UserActivityLogger::class)->log($request, 'webhook_endpoint_created', true, null, [
            'target_user_id' => $endpoint->idusuario,
            'endpoint_id' => $endpoint->id,
            'host' => $endpoint->host,
        ]);

        return response()->json(['status' => 'success', 'endpoint_id' => $endpoint->id]);
    }

    public function updateEndpoint(Request $request, $id, WebhookUrlValidator $urlValidator)
    {
        $endpoint = WebhookEndpoint::findOrFail($id);
        $data = $this->validateEndpoint($request, $urlValidator, $endpoint->id);

        if ((int) $data['user_id'] !== (int) $endpoint->idusuario) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Un endpoint no puede reasignarse a otro cliente.',
            ], 422);
        }

        DB::transaction(function () use ($endpoint, $data) {
            $endpoint->update($this->endpointAttributes($data));
            $this->syncSubscriptions($endpoint, $data['subscriptions']);
        });

        app(UserActivityLogger::class)->log($request, 'webhook_endpoint_updated', true, null, [
            'target_user_id' => $endpoint->idusuario,
            'endpoint_id' => $endpoint->id,
            'host' => $endpoint->host,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function deleteEndpoint(Request $request, $id)
    {
        $endpoint = WebhookEndpoint::findOrFail($id);
        $endpoint->active = false;
        $endpoint->save();
        $endpoint->delete();

        app(UserActivityLogger::class)->log($request, 'webhook_endpoint_deleted', true, null, [
            'target_user_id' => $endpoint->idusuario,
            'endpoint_id' => $endpoint->id,
            'host' => $endpoint->host,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function deliveries(Request $request)
    {
        if (!$request->ajax()) {
            return redirect('/');
        }

        $filters = $this->deliveryFilters($request);
        if ($validation = $this->validarRangoFechasListado($filters['fechaInicio'], $filters['fechaFin'])) {
            return $validation;
        }

        $items = $this->deliveryQuery($filters)
            ->orderBy('webhook_deliveries.created_at', 'desc')
            ->paginate($filters['offset']);

        return [
            'deliveries' => $items,
            'pagination' => [
                'total' => $items->total(),
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ];
    }

    public function deliveryDetail($id, AuditSanitizer $sanitizer)
    {
        $delivery = WebhookDelivery::with(['event', 'endpoint', 'attempts' => function ($query) {
            $query->orderBy('id', 'desc');
        }])->findOrFail($id);

        return [
            'delivery' => [
                'id' => $delivery->id,
                'status' => $delivery->status,
                'attempt_count' => $delivery->attempt_count,
                'last_status_code' => $delivery->last_status_code,
                'last_error' => $delivery->last_error,
                'body_hash' => $delivery->body_hash,
                'is_test' => $delivery->is_test,
                'event' => [
                    'id' => $delivery->event->id,
                    'event_type' => $delivery->event->event_type,
                    'source_type' => $delivery->event->source_type,
                    'source_id' => $delivery->event->source_id,
                    'source_context' => $delivery->event->source_context,
                    'occurred_at' => $delivery->event->occurred_at,
                    'payload' => $sanitizer->sanitizePayload($delivery->event->payload),
                ],
                'endpoint' => [
                    'id' => $delivery->endpoint->id,
                    'name' => $delivery->endpoint->name,
                    'host' => $delivery->endpoint->host,
                    'payload_mode' => $delivery->endpoint->payload_mode,
                    'ack_mode' => $delivery->endpoint->ack_mode,
                ],
                'attempts' => $delivery->attempts,
            ],
        ];
    }

    public function retryDelivery(Request $request, $id)
    {
        $delivery = WebhookDelivery::findOrFail($id);
        if (!in_array($delivery->status, ['dead', 'cancelled'], true)) {
            return response()->json(['status' => 'error', 'msg' => 'La entrega no admite reintento manual.'], 422);
        }

        $delivery->update([
            'status' => 'pending',
            'attempt_count' => 0,
            'next_attempt_at' => null,
            'delivered_at' => null,
            'last_status_code' => null,
            'last_error' => null,
        ]);

        DeliverWebhookJob::dispatch($delivery->id)
            ->onConnection(config('webhooks.connection', 'database'))
            ->onQueue(config('webhooks.queue', 'webhooks'));

        app(UserActivityLogger::class)->log($request, 'webhook_delivery_retried', true, null, [
            'delivery_id' => $delivery->id,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function cancelDelivery(Request $request, $id)
    {
        $delivery = WebhookDelivery::findOrFail($id);
        if (!in_array($delivery->status, ['pending', 'retrying'], true)) {
            return response()->json(['status' => 'error', 'msg' => 'La entrega no puede cancelarse.'], 422);
        }

        $delivery->update(['status' => 'cancelled', 'next_attempt_at' => null]);

        app(UserActivityLogger::class)->log($request, 'webhook_delivery_cancelled', true, null, [
            'delivery_id' => $delivery->id,
        ]);

        return response()->json(['status' => 'success']);
    }

    public function testEndpoint(Request $request, $id, WebhookFanoutService $fanout)
    {
        if (!config('webhooks.enabled', false)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'El envio global de webhooks esta deshabilitado.',
            ], 422);
        }

        $endpoint = WebhookEndpoint::findOrFail($id);
        $setting = WebhookUserSetting::where('idusuario', $endpoint->idusuario)->first();

        if (!$endpoint->active || !$setting || !in_array($setting->mode, ['shadow', 'hybrid', 'active'], true)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'El endpoint debe estar activo y el cliente en modo shadow o active.',
            ], 422);
        }

        if ($setting->hmac_enabled && trim((string) $setting->hmac_secret) === '') {
            return response()->json(['status' => 'error', 'msg' => 'Falta configurar el secreto HMAC.'], 422);
        }

        $event = WebhookEvent::create([
            'id' => (string) Str::uuid(),
            'idusuario' => $endpoint->idusuario,
            'event_type' => 'webhook.endpoint.test',
            'source_type' => 'system',
            'source_context' => 'test',
            'idempotency_key' => 'webhook.endpoint.test:' . Str::uuid(),
            'payload' => [
                'legacy_payload' => [
                    'event' => 'webhook.endpoint.test',
                    'message' => 'Prueba de endpoint Centro de Cobros',
                    'sent_at' => Carbon::now('America/Hermosillo')->toIso8601String(),
                ],
                'source_payload' => [
                    'message' => 'Prueba de endpoint Centro de Cobros',
                ],
            ],
            'status' => 'queued',
            'occurred_at' => Carbon::now('America/Hermosillo'),
        ]);

        $delivery = $fanout->createTestDelivery($event, $endpoint);
        DeliverWebhookJob::dispatch($delivery->id)
            ->onConnection(config('webhooks.connection', 'database'))
            ->onQueue(config('webhooks.queue', 'webhooks'));

        app(UserActivityLogger::class)->log($request, 'webhook_endpoint_tested', true, null, [
            'target_user_id' => $endpoint->idusuario,
            'endpoint_id' => $endpoint->id,
            'delivery_id' => $delivery->id,
        ]);

        return response()->json(['status' => 'success', 'delivery_id' => $delivery->id]);
    }

    public function exportDeliveries(Request $request)
    {
        $filters = $this->deliveryFilters($request);
        if ($validation = $this->validarRangoFechasListado($filters['fechaInicio'], $filters['fechaFin'])) {
            return $validation;
        }

        $rows = $this->deliveryQuery($filters)
            ->orderBy('webhook_deliveries.created_at', 'desc')
            ->get()
            ->map(function ($row) {
                return [
                    (string) $row->created_at,
                    $row->event_type,
                    $row->usuario,
                    $row->endpoint_name,
                    $row->host,
                    $row->status,
                    $row->attempt_count,
                    $row->last_status_code,
                    $row->source_context,
                    $row->last_error,
                ];
            });

        return Excel::download(new IntegrationAuditExport($rows, [
            'Fecha', 'Evento', 'Usuario', 'Endpoint', 'Host', 'Estado', 'Intentos', 'HTTP', 'Origen', 'Error',
        ]), 'webhook_deliveries.xlsx');
    }

    private function validateEndpoint(Request $request, WebhookUrlValidator $urlValidator, ?int $endpointId = null): array
    {
        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('idrol', 2);
                }),
            ],
            'name' => 'required|string|max:120',
            'url' => 'required|string|max:2048',
            'active' => 'required|boolean',
            'channel' => ['nullable', Rule::in(['generic', 'donation', 'event'])],
            'payload_mode' => ['required', Rule::in(['legacy_exact', 'soportetech_v1', 'soportetech_v1_1'])],
            'ack_mode' => ['required', Rule::in(['legacy_code_success', 'http_2xx'])],
            'rate_limit_per_minute' => 'required|integer|min:1|max:' . config('webhooks.maximum_rate_limit', 30),
            'subscriptions' => 'required|array|min:1',
            'subscriptions.*.event_type' => 'required|string|max:120',
            'subscriptions.*.source_filter' => ['required', Rule::in(['all', 'manual', 'api', 'automatic'])],
        ]);
        $data['channel'] = $data['channel'] ?? 'generic';

        if ($error = $urlValidator->validate($data['url'])) {
            throw new HttpResponseException(response()->json(['status' => 'error', 'msg' => $error], 422));
        }

        $catalog = config('webhooks.events', []);
        foreach ($data['subscriptions'] as $subscription) {
            if (!array_key_exists($subscription['event_type'], $catalog)) {
                throw new HttpResponseException(response()->json(['status' => 'error', 'msg' => 'Evento no permitido.'], 422));
            }

            if ($subscription['event_type'] === 'webhook.endpoint.test') {
                throw new HttpResponseException(response()->json([
                    'status' => 'error',
                    'msg' => 'El evento de prueba no admite suscripcion.',
                ], 422));
            }
        }

        if ($data['payload_mode'] === 'soportetech_v1_1' && $data['channel'] !== 'donation') {
            throw new HttpResponseException(response()->json([
                'status' => 'error',
                'msg' => 'SOPORTETECH V1.1 solo puede utilizarse en el canal de donaciones.',
            ], 422));
        }

        if ($data['payload_mode'] === 'soportetech_v1_1') {
            $v11EventTypes = collect($data['subscriptions'])->pluck('event_type')->unique();
            $unsupported = $v11EventTypes
                ->reject(fn ($eventType) => in_array($eventType, \App\Services\SupportTechV11PayloadBuilder::EVENTS, true));
            if ($unsupported->isNotEmpty()) {
                throw new HttpResponseException(response()->json([
                    'status' => 'error',
                    'msg' => 'SOPORTETECH V1.1 solo admite eventos de donaciones y domiciliaciones.',
                ], 422));
            }

            if ($v11EventTypes->contains('domiciliation_link.payment.approved')
                && collect(['domiciliation.activated', 'domiciliation.activation_failed'])
                    ->diff($v11EventTypes)->isNotEmpty()) {
                throw new HttpResponseException(response()->json([
                    'status' => 'error',
                    'msg' => 'El pago inicial aprobado debe migrarse junto con ambos resultados de activacion.',
                ], 422));
            }
        }

        if ($data['channel'] === 'event') {
            $eventTypes = collect($data['subscriptions'])->pluck('event_type')->unique()->values()->all();
            if ($data['payload_mode'] !== 'legacy_exact'
                || $eventTypes !== ['payment_link.payment.approved']) {
                throw new HttpResponseException(response()->json([
                    'status' => 'error',
                    'msg' => 'El canal de eventos conserva legacy_exact y solo payment_link.payment.approved.',
                ], 422));
            }
        }

        $hash = hash('sha256', trim($data['url']));
        $duplicate = WebhookEndpoint::where('idusuario', $data['user_id'])
            ->where('url_hash', $hash)
            ->when($endpointId !== null, function ($query) use ($endpointId) {
                $query->where('id', '<>', $endpointId);
            })
            ->exists();

        if ($duplicate) {
            throw new HttpResponseException(response()->json([
                'status' => 'error',
                'msg' => 'El usuario ya tiene registrado ese endpoint.',
            ], 422));
        }

        return $data;
    }

    private function endpointAttributes(array $data): array
    {
        $url = trim($data['url']);

        return [
            'idusuario' => (int) $data['user_id'],
            'name' => trim($data['name']),
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'host' => strtolower((string) parse_url($url, PHP_URL_HOST)),
            'active' => (bool) $data['active'],
            'channel' => $data['channel'],
            'payload_mode' => $data['payload_mode'],
            'ack_mode' => $data['ack_mode'],
            'rate_limit_per_minute' => (int) $data['rate_limit_per_minute'],
        ];
    }

    private function syncSubscriptions(WebhookEndpoint $endpoint, array $subscriptions): void
    {
        WebhookEndpointSubscription::where('webhook_endpoint_id', $endpoint->id)->delete();

        foreach ($subscriptions as $subscription) {
            WebhookEndpointSubscription::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event_type' => $subscription['event_type'],
                'source_filter' => $subscription['source_filter'],
                'active' => true,
            ]);
        }
    }

    private function eventCatalog(): array
    {
        return collect(config('webhooks.events', []))->map(function ($event, $key) {
            return array_merge(['key' => $key, 'sources' => ['all']], $event);
        })->values()->all();
    }

    private function deliveryFilters(Request $request): array
    {
        $today = Carbon::now('America/Hermosillo');

        return [
            'fechaInicio' => $request->fechaInicio ?? $today->copy()->startOfMonth()->toDateString(),
            'fechaFin' => $request->fechaFin ?? $today->toDateString(),
            'buscar' => trim((string) ($request->buscar ?? '')),
            'user_id' => (int) ($request->user_id ?? 0),
            'event_type' => trim((string) ($request->event_type ?? '')),
            'status' => trim((string) ($request->status ?? '')),
            'offset' => $this->offsetPaginacion($request->offset ?? 50),
        ];
    }

    private function deliveryQuery(array $filters)
    {
        $query = WebhookDelivery::join('webhook_events', 'webhook_events.id', '=', 'webhook_deliveries.webhook_event_id')
            ->join('webhook_endpoints', 'webhook_endpoints.id', '=', 'webhook_deliveries.webhook_endpoint_id')
            ->leftJoin('users', 'users.id', '=', 'webhook_events.idusuario')
            ->select(
                'webhook_deliveries.id',
                'webhook_deliveries.status',
                'webhook_deliveries.attempt_count',
                'webhook_deliveries.next_attempt_at',
                'webhook_deliveries.delivered_at',
                'webhook_deliveries.last_status_code',
                'webhook_deliveries.last_error',
                'webhook_deliveries.is_test',
                'webhook_deliveries.created_at',
                'webhook_events.event_type',
                'webhook_events.source_context',
                'webhook_events.idusuario',
                'webhook_endpoints.name as endpoint_name',
                'webhook_endpoints.host',
                'users.usuario'
            );

        $this->aplicarRangoFechasListado($query, 'webhook_deliveries.created_at', $filters['fechaInicio'], $filters['fechaFin']);

        if ($filters['user_id'] > 0) {
            $query->where('webhook_events.idusuario', $filters['user_id']);
        }
        if ($filters['event_type'] !== '') {
            $query->where('webhook_events.event_type', $filters['event_type']);
        }
        if ($filters['status'] !== '') {
            $query->where('webhook_deliveries.status', $filters['status']);
        }
        if ($filters['buscar'] !== '') {
            $term = '%' . $filters['buscar'] . '%';
            $query->where(function ($query) use ($term) {
                $query->where('webhook_deliveries.id', 'like', $term)
                    ->orWhere('webhook_endpoints.name', 'like', $term)
                    ->orWhere('webhook_endpoints.host', 'like', $term)
                    ->orWhere('webhook_events.event_type', 'like', $term)
                    ->orWhere('users.usuario', 'like', $term);
            });
        }

        return $query;
    }
}
