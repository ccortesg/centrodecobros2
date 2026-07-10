<?php

namespace App\Console\Commands;

use App\Services\WebhookUrlValidator;
use App\User;
use App\WebhookEndpoint;
use App\WebhookEndpointSubscription;
use App\WebhookUserSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WebhookImportLegacy extends Command
{
    protected $signature = 'webhooks:import-legacy
        {--dry-run : Mostrar los cambios sin guardarlos}
        {--mode=legacy : Modo inicial legacy o shadow}';

    protected $description = 'Importa ligaPago y ligaRecurrente a la configuracion administrable de webhooks.';

    public function handle(WebhookUrlValidator $validator): int
    {
        $mode = (string) $this->option('mode');
        if (!in_array($mode, ['legacy', 'shadow'], true)) {
            $this->error('El modo debe ser legacy o shadow.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $users = User::where('idrol', 2)->where('notificaPago', 1)->orderBy('id')->get();
        $created = 0;
        $invalid = 0;

        foreach ($users as $user) {
            $definitions = [
                [
                    'url' => trim((string) $user->ligaPago),
                    'name' => 'Legacy Liga Pago',
                    'subscriptions' => [
                        ['payment_link.payment.approved', 'all'],
                        ['domiciliation_link.payment.approved', 'all'],
                        ['terminal.payment.approved', 'all'],
                        ['spei.payment.approved', 'all'],
                    ],
                ],
                [
                    'url' => trim((string) $user->ligaRecurrente),
                    'name' => 'Legacy Cargos Recurrentes',
                    'subscriptions' => [
                        ['recurring_charge.approved', 'automatic'],
                        ['recurring_charge.rejected', 'automatic'],
                        ['recurring_charge.error', 'automatic'],
                    ],
                ],
            ];

            foreach ($definitions as $definition) {
                if ($definition['url'] === '') {
                    continue;
                }

                if ($error = $validator->validate($definition['url'])) {
                    $this->warn("Usuario {$user->id}: {$definition['name']} no se importara. {$error}");
                    $invalid++;
                    continue;
                }

                $this->line("Usuario {$user->id}: {$definition['name']} -> " . parse_url($definition['url'], PHP_URL_HOST));
                if ($dryRun) {
                    $created++;
                    continue;
                }

                DB::transaction(function () use ($user, $mode, $definition) {
                    WebhookUserSetting::firstOrCreate(
                        ['idusuario' => $user->id],
                        ['mode' => $mode, 'hmac_enabled' => false]
                    );

                    $hash = hash('sha256', $definition['url']);
                    $endpoint = WebhookEndpoint::withTrashed()->where([
                        ['idusuario', '=', $user->id],
                        ['url_hash', '=', $hash],
                    ])->first();

                    if ($endpoint === null) {
                        $endpoint = WebhookEndpoint::create([
                            'idusuario' => $user->id,
                            'name' => $definition['name'],
                            'url' => $definition['url'],
                            'url_hash' => $hash,
                            'host' => strtolower((string) parse_url($definition['url'], PHP_URL_HOST)),
                            'active' => true,
                            'payload_mode' => 'legacy_exact',
                            'ack_mode' => 'legacy_code_success',
                            'rate_limit_per_minute' => min(25, (int) config('webhooks.maximum_rate_limit', 30)),
                        ]);
                    } elseif ($endpoint->trashed()) {
                        $endpoint->restore();
                        $endpoint->update(['active' => true]);
                    }

                    foreach ($definition['subscriptions'] as [$eventType, $sourceFilter]) {
                        WebhookEndpointSubscription::firstOrCreate([
                            'webhook_endpoint_id' => $endpoint->id,
                            'event_type' => $eventType,
                            'source_filter' => $sourceFilter,
                        ], ['active' => true]);
                    }
                });

                $created++;
            }
        }

        $suffix = $dryRun ? ' se importarian' : ' importados';
        $this->info($created . ' endpoints' . $suffix . '. URLs invalidas: ' . $invalid . '.');

        return $invalid > 0 ? self::FAILURE : self::SUCCESS;
    }
}
