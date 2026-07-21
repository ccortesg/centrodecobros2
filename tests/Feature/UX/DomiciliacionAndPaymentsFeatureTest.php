<?php

namespace Tests\Feature\UX;

use App\Http\Controllers\TransaccionController;
use App\Services\TransaccionStatusSynchronizer;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class DomiciliacionAndPaymentsFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpIsolatedDatabase();
    }

    public function test_domiciliacion_activa_lists_only_approved_active_or_cancelled_records()
    {
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 1, 'productivo' => 1]);
        DB::table('transacciones')->where('id', 201)->update(['condicion' => 2, 'productivo' => 1]);

        $response = $this->actingAs($this->adminUser())
            ->get('/domiciliacion-activa?offset=10&buscar=&criterio=ClientReference&status=99', $this->ajaxHeaders())
            ->assertOk();

        $ids = array_column($response->json('domiciliaciones.data'), 'id');

        $this->assertContains(200, $ids);
        $this->assertContains(201, $ids);
        $this->assertNotContains(100, $ids);
    }

    public function test_domiciliacion_activa_filters_by_condicion_and_rejects_invalid_filters()
    {
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 1]);
        DB::table('transacciones')->where('id', 201)->update(['condicion' => 2]);

        $response = $this->actingAs($this->adminUser())
            ->get('/domiciliacion-activa?offset=10&buscar=&criterio=ClientReference&status=2', $this->ajaxHeaders())
            ->assertOk();

        foreach ($response->json('domiciliaciones.data') as $domiciliacion) {
            $this->assertSame(2, (int) $domiciliacion['condicion']);
        }

        $this->actingAs($this->adminUser())
            ->get('/domiciliacion-activa?offset=10&buscar=x&criterio=status&status=99', $this->ajaxHeaders())
            ->assertStatus(422);

        $this->actingAs($this->adminUser())
            ->get('/domiciliacion-activa?offset=10&buscar=&criterio=ClientReference&status=5', $this->ajaxHeaders())
            ->assertStatus(422);
    }

    public function test_pagos_recibidos_lists_sources_and_allows_status_override()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/pagos-recibidos?offset=10&buscar=&criterio=cliente&status=99', $this->ajaxHeaders())
            ->assertOk();

        $this->assertNotEmpty($response->json('pagos.data'));

        $pago = collect($response->json('pagos.data'))->firstWhere('source_type', 'respuesta');
        $this->assertNotNull($pago);
        $this->assertSame('activo', $pago['status']);

        $this->actingAs($this->adminUser())
            ->put('/pagos-recibidos/status', [
                'source_type' => $pago['source_type'],
                'source_id' => $pago['source_id'],
                'status' => 'cancelado',
            ], $this->ajaxHeaders())
            ->assertOk();

        $this->assertDatabaseHas('pagos_recibidos', [
            'source_type' => $pago['source_type'],
            'source_id' => $pago['source_id'],
            'status' => 'cancelado',
        ]);
    }

    public function test_pagos_recibidos_includes_recurring_charges_and_normalizes_amounts_by_source()
    {
        DB::table('respuestas')->where('id', 1)->update(['amount' => 1500.25]);
        DB::table('pagospei')->where('id', 1)->update(['monto' => 250075]);
        DB::table('transaccionesDom')->where('id', 1)->update(['Amount' => 38000, 'status' => 'approved']);

        $response = $this->actingAs($this->adminUser())
            ->get('/pagos-recibidos?offset=20&buscar=&criterio=cliente', $this->ajaxHeaders())
            ->assertOk();

        $pagos = collect($response->json('pagos.data'));
        $respuesta = $pagos->first(function ($pago) {
            return $pago['source_type'] === 'respuesta' && (int) $pago['source_id'] === 1;
        });
        $spei = $pagos->first(function ($pago) {
            return $pago['source_type'] === 'pagospei' && (int) $pago['source_id'] === 1;
        });
        $recurrente = $pagos->first(function ($pago) {
            return $pago['source_type'] === 'transaccionDom' && (int) $pago['source_id'] === 1;
        });

        $this->assertNotNull($respuesta);
        $this->assertNotNull($spei);
        $this->assertNotNull($recurrente);
        $this->assertEqualsWithDelta(1500.25, (float) $respuesta['monto'], 0.001);
        $this->assertEqualsWithDelta(2500.75, (float) $spei['monto'], 0.001);
        $this->assertEqualsWithDelta(380.00, (float) $recurrente['monto'], 0.001);
        $this->assertSame('Cargo Recurrente', $recurrente['canal']);
    }

    public function test_pagos_recibidos_filters_by_payment_date_range()
    {
        DB::table('respuestas')->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('pagospei')->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('transaccionesDom')->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('pagospei')->where('id', 1)->update(['fecha' => '2026-06-03 12:30:00']);

        $response = $this->actingAs($this->adminUser())
            ->get('/pagos-recibidos?offset=10&buscar=&criterio=cliente&fechaInicio=2026-06-03&fechaFin=2026-06-03', $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame(1, (int) $response->json('pagination.total'));
        $this->assertSame('pagospei', $response->json('pagos.data.0.source_type'));

        $this->actingAs($this->adminUser())
            ->get('/pagos-recibidos?offset=10&buscar=&criterio=cliente&fechaInicio=2026-06-04&fechaFin=2026-06-03', $this->ajaxHeaders())
            ->assertStatus(422);
    }

    public function test_pagos_recibidos_export_uses_search_and_date_filters()
    {
        DB::table('respuestas')->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('pagospei')->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('transaccionesDom')->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('respuestas')->where('id', 1)->update(['fecha' => '2026-06-03 12:00:00']);

        $response = $this->actingAs($this->adminUser())
            ->get('/pagos-recibidos/exportar?buscar=Cliente%20A&criterio=cliente&fechaInicio=2026-06-03&fechaFin=2026-06-03', $this->ajaxHeaders())
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Cliente A SA', $content);
        $this->assertStringNotContainsString('Cliente B SA', $content);
    }

    public function test_pagos_recibidos_filters_operation_folio_and_authorization_across_sources()
    {
        DB::table('respuestas')->where('id', 1)->update([
            'foliocpagos' => 'OPERACION-RESPUESTA-A',
            'auth' => 'AUTORIZACION-RESPUESTA-A',
        ]);
        DB::table('transaccionesDom')->where('id', 1)->update([
            'foliocpagos' => 'OPERACION-RECURRENTE-A',
            'auth' => 'AUTORIZACION-RECURRENTE-A',
            'status' => 'approved',
        ]);
        DB::table('pagospei')->where('id', 1)->update([
            'autorizacion' => 'AUTORIZACION-SPEI-A',
        ]);

        $cases = [
            ['foliocpagos', 'OPERACION-RESPUESTA-A', 'respuesta'],
            ['autorizacion', 'AUTORIZACION-RESPUESTA-A', 'respuesta'],
            ['foliocpagos', 'OPERACION-RECURRENTE-A', 'transaccionDom'],
            ['autorizacion', 'AUTORIZACION-RECURRENTE-A', 'transaccionDom'],
            ['autorizacion', 'AUTORIZACION-SPEI-A', 'pagospei'],
        ];

        foreach ($cases as [$criterio, $buscar, $sourceType]) {
            $response = $this->actingAs($this->adminUser())
                ->get('/pagos-recibidos?offset=50&buscar=' . $buscar . '&criterio=' . $criterio . '&status=99', $this->ajaxHeaders())
                ->assertOk();

            $this->assertSame(1, (int) $response->json('pagination.total'));
            $this->assertSame($sourceType, $response->json('pagos.data.0.source_type'));
        }

        $this->actingAs($this->adminUser())
            ->get('/pagos-recibidos/exportar?buscar=OPERACION-RESPUESTA-A&criterio=foliocpagos&status=99', $this->ajaxHeaders())
            ->assertOk();
    }

    public function test_client_can_access_active_domiciliations_scoped_to_own_records()
    {
        $response = $this->actingAs($this->clientAUser())
            ->get('/domiciliacion-activa?offset=10&buscar=&criterio=ClientReference&status=99', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['ClientReference' => 'DOM-A']);
        $response->assertJsonMissing(['ClientReference' => 'DOM-B']);
    }

    public function test_domiciliacion_activa_export_uses_same_filters()
    {
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 1, 'productivo' => 1]);
        DB::table('transacciones')->where('id', 201)->update(['condicion' => 2, 'productivo' => 1]);

        $response = $this->actingAs($this->adminUser())
            ->get('/domiciliacion-activa/exportar?buscar=DOM-A&criterio=ClientReference&status=1', $this->ajaxHeaders())
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('DOM-A', $content);
        $this->assertStringNotContainsString('DOM-B', $content);
    }

    public function test_client_can_cancel_own_active_domiciliation_and_get_success_without_error()
    {
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 1]);

        $this->actingAs($this->clientAUser())
            ->put('/transaccion/rechazar', ['id' => 200], $this->ajaxHeaders())
            ->assertOk()
            ->assertJson([
                'error' => '',
            ]);

        $this->assertSame(2, (int) DB::table('transacciones')->where('id', 200)->value('condicion'));
        $this->assertDatabaseHas('cancelacionesDom', [
            'Token' => 'TOKEN-A',
            'idusuario' => 2,
            'productivo' => 1,
        ]);
    }

    public function test_cannot_cancel_active_domiciliation_without_approved_token()
    {
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 1]);
        DB::table('respuestas')->where('idtransaccion', 200)->update(['number_tkn' => '']);

        $this->actingAs($this->clientAUser())
            ->put('/transaccion/rechazar', ['id' => 200], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJson([
                'error' => 'La respuesta aprobada no pudo ser identificada para cancelar la domiciliacion.',
            ]);

        $this->assertSame(1, (int) DB::table('transacciones')->where('id', 200)->value('condicion'));
        $this->assertSame(0, DB::table('cancelacionesDom')->count());
    }

    public function test_pending_cancellation_claim_prevents_a_concurrent_provider_call()
    {
        DB::table('transacciones')->where('id', 200)->update([
            'condicion' => 0,
            'domiciliation_status' => 'cancellation_pending',
            'cancellation_reason' => 'max_rejected_attempts',
            'cancellation_idempotency_key' => 'st:cancel:200',
            'cancellation_attempts' => 1,
            'cancellation_last_attempt_at' => now(),
        ]);

        $this->postJson('/CancelarDomiciliacion', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'ClientReference' => 'DOM-A',
            'reason_code' => 'max_rejected_attempts',
            'rejected_attempts' => 3,
        ])->assertStatus(202)->assertJsonPath('code', 'pending');

        $this->assertSame(1, (int) DB::table('transacciones')->where('id', 200)->value('cancellation_attempts'));
        $this->assertSame(0, DB::table('cancelacionesDom')->count());
    }

    public function test_client_can_access_received_payments_scoped_to_own_records()
    {
        $response = $this->actingAs($this->clientAUser())
            ->get('/pagos-recibidos?offset=10&buscar=&criterio=cliente&status=99', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['cliente' => 'Cliente A SA']);
        $response->assertJsonMissing(['cliente' => 'Cliente B SA']);
    }

    public function test_client_can_update_own_received_payment_status()
    {
        $this->actingAs($this->clientAUser())
            ->put('/pagos-recibidos/status', [
                'source_type' => 'respuesta',
                'source_id' => 1,
                'status' => 'cancelado',
            ], $this->ajaxHeaders())
            ->assertOk();

        $this->assertDatabaseHas('pagos_recibidos', [
            'source_type' => 'respuesta',
            'source_id' => 1,
            'status' => 'cancelado',
        ]);
    }

    public function test_client_cannot_update_another_users_received_payment_status()
    {
        $this->actingAs($this->clientAUser())
            ->put('/pagos-recibidos/status', [
                'source_type' => 'respuesta',
                'source_id' => 2,
                'status' => 'cancelado',
            ], $this->ajaxHeaders())
            ->assertStatus(403);
    }

    public function test_non_admin_non_client_role_cannot_access_new_client_modules()
    {
        DB::table('roles')->insert(['id' => 3, 'nombre' => 'Operador', 'condicion' => 1]);
        DB::table('users')->insert([
            'id' => 4,
            'usuario' => 'operator',
            'password' => bcrypt('secret'),
            'idrol' => 3,
            'condicion' => 1,
            'token' => 'operator-token',
            'IntegrationID' => '117',
            'BusinessID' => '000033',
            'productivo' => 1,
        ]);

        $operator = User::findOrFail(4);

        $this->actingAs($operator)
            ->get('/domiciliacion-activa?offset=10&buscar=&criterio=ClientReference&status=99', $this->ajaxHeaders())
            ->assertStatus(403);

        $this->actingAs($operator)
            ->get('/pagos-recibidos?offset=10&buscar=&criterio=cliente&status=99', $this->ajaxHeaders())
            ->assertStatus(403);
    }

    public function test_daily_status_sync_marks_pending_domiciliacion_as_expired_after_expiration()
    {
        DB::table('transacciones')->insert($this->transactionRowForStatus(700, 0, now()->subDay()->toDateString()));

        $metricas = app(TransaccionStatusSynchronizer::class)->sincronizarTodo();

        $this->assertSame(4, (int) DB::table('transacciones')->where('id', 700)->value('condicion'));
        $this->assertSame(1, $metricas['vencidas_domiciliacion']);
        $this->assertGreaterThanOrEqual(1, $metricas['filas_afectadas']);
        $this->assertArrayHasKey('duracion_ms', $metricas);
    }

    public function test_daily_status_sync_marks_approved_domiciliacion_without_token_as_error()
    {
        DB::table('transacciones')->insert($this->transactionRowForStatus(701, 0, now()->addDay()->toDateString()));
        DB::table('respuestas')->insert($this->responseRowForStatus(701, 701, 'RESP-NO-TOKEN', ''));

        app(TransaccionStatusSynchronizer::class)->sincronizarTodo();

        $this->assertSame(5, (int) DB::table('transacciones')->where('id', 701)->value('condicion'));
    }

    public function test_daily_status_sync_marks_approved_domiciliacion_with_token_as_active()
    {
        DB::table('transacciones')->insert($this->transactionRowForStatus(702, 0, now()->addDay()->toDateString()));
        DB::table('respuestas')->insert($this->responseRowForStatus(702, 702, 'RESP-WITH-TOKEN', 'TOKEN-OK'));

        app(TransaccionStatusSynchronizer::class)->sincronizarTodo();

        $transaccion = DB::table('transacciones')->where('id', 702)->first();
        $this->assertSame(1, (int) $transaccion->condicion);
        $this->assertSame(0, (int) $transaccion->intentos);
        $this->assertNotNull($transaccion->ProximoCargoBase);
    }

    public function test_revisar_status_only_processes_spei_and_does_not_run_daily_reconciliation()
    {
        DB::table('transacciones')->insert($this->transactionRowForStatus(703, 0, now()->subDay()->toDateString()));
        DB::table('pagospei')->update(['enviada' => 1]);

        $metricas = app(TransaccionController::class)->revisarStatus();

        $this->assertSame(0, (int) DB::table('transacciones')->where('id', 703)->value('condicion'));
        $this->assertSame(0, $metricas['pagos_candidatos']);
        $this->assertArrayHasKey('duracion_ms', $metricas);
    }

    public function test_daily_status_command_reconciles_transactions_and_reports_metrics()
    {
        DB::table('transacciones')->insert($this->transactionRowForStatus(704, 0, now()->subDay()->toDateString()));

        $exitCode = Artisan::call('transacciones:sincronizar-status');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame(4, (int) DB::table('transacciones')->where('id', 704)->value('condicion'));
        $this->assertStringContainsString('"vencidas_domiciliacion":1', $output);
        $this->assertStringContainsString('"duracion_ms":', $output);
    }

    public function test_manual_response_creation_activates_domiciliation_immediately()
    {
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 0]);

        $this->actingAs($this->adminUser())
            ->post('/respuesta/registrar', [
                'reference' => 'RESP-DOM-A',
                'status' => 'approved',
                'amount' => 10000,
                'number_tkn' => 'TOKEN-MANUAL',
            ], $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame(1, (int) DB::table('transacciones')->where('id', 200)->value('condicion'));
        $this->assertDatabaseHas('respuestas', [
            'idtransaccion' => 200,
            'reference' => 'RESP-DOM-A',
            'number_tkn' => 'TOKEN-MANUAL',
        ]);
    }

    public function test_manual_response_update_reconciles_domiciliation_immediately()
    {
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 5]);

        $this->actingAs($this->adminUser())
            ->put('/respuesta/actualizar', [
                'id' => 3,
                'reference' => 'RESP-DOM-A',
                'status' => 'approved',
                'amount' => 10000,
                'number_tkn' => 'TOKEN-ACTUALIZADO',
            ], $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame(1, (int) DB::table('transacciones')->where('id', 200)->value('condicion'));
        $this->assertSame('TOKEN-ACTUALIZADO', DB::table('respuestas')->where('id', 3)->value('number_tkn'));
    }

    public function test_client_cannot_create_manual_response_for_another_users_transaction()
    {
        DB::table('transacciones')->where('id', 201)->update(['condicion' => 0]);

        $this->actingAs($this->clientAUser())
            ->post('/respuesta/registrar', [
                'reference' => 'RESP-DOM-B',
                'status' => 'approved',
                'amount' => 10000,
                'number_tkn' => 'TOKEN-NO-AUTORIZADO',
            ], $this->ajaxHeaders())
            ->assertStatus(403);

        $this->assertSame(0, (int) DB::table('transacciones')->where('id', 201)->value('condicion'));
        $this->assertDatabaseMissing('respuestas', ['number_tkn' => 'TOKEN-NO-AUTORIZADO']);
    }

    public function test_manual_successful_recurring_charge_resets_domiciliacion_intentos()
    {
        DB::table('transacciones')->where('id', 200)->update([
            'condicion' => 1,
            'frecuencia' => 2,
            'ProximoCargo' => '2026-06-08',
            'ProximoCargoBase' => '2026-06-08',
            'intentos' => 4,
        ]);

        $this->actingAs($this->adminUser())
            ->post('/transaccionDom/registrar', ['idtransaccion' => 200], $this->ajaxHeaders())
            ->assertOk();

        $transaccion = DB::table('transacciones')->where('id', 200)->first();
        $this->assertSame(0, (int) $transaccion->intentos);
        $this->assertSame('2026-07-08', $transaccion->ProximoCargo);
    }

    public function test_automatic_third_consecutive_rejection_stops_charges_and_cancels_domiciliation()
    {
        DB::table('users')->where('id', 2)->update(['recurrente' => 1]);
        DB::table('transacciones')->where('id', 200)->update([
            'condicion' => 1,
            'domiciliation_status' => 'active',
            'ProximoCargo' => now()->toDateString(),
            'intentos' => 2,
        ]);
        DB::table('respuestas')->where('idtransaccion', 200)->update([
            'number_tkn' => 'MOCK_REJECTED_CHARGE',
        ]);

        app(\App\Http\Controllers\TransaccionDomController::class)->ejecutarCron();

        $transaction = DB::table('transacciones')->where('id', 200)->first();
        $this->assertSame(3, (int) $transaction->intentos);
        $this->assertSame(2, (int) $transaction->condicion);
        $this->assertSame('cancelled', $transaction->domiciliation_status);
        $this->assertSame('max_rejected_attempts', $transaction->cancellation_reason);
        $this->assertSame(1, (int) $transaction->cancellation_attempts);
        $this->assertNotNull($transaction->cancelled_at);
        $this->assertDatabaseHas('cancelacionesDom', [
            'idtransaccion' => 200,
            'code' => 'success',
        ]);
    }

    public function test_api_third_consecutive_rejection_uses_the_same_cancellation_policy()
    {
        DB::table('transacciones')->where('id', 200)->update([
            'condicion' => 1,
            'domiciliation_status' => 'active',
            'intentos' => 2,
        ]);
        DB::table('respuestas')->where('idtransaccion', 200)->update([
            'number_tkn' => 'MOCK_REJECTED_CHARGE',
        ]);

        $this->postJson('/CargoDomiciliacion', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'ClientReference' => 'DOM-A',
            'Amount' => 0,
        ])->assertOk();

        $transaction = DB::table('transacciones')->where('id', 200)->first();
        $this->assertSame(3, (int) $transaction->intentos);
        $this->assertSame(2, (int) $transaction->condicion);
        $this->assertSame('cancelled', $transaction->domiciliation_status);
        $this->assertSame('max_rejected_attempts', $transaction->cancellation_reason);
        $this->assertSame(1, (int) $transaction->cancellation_attempts);
    }

    public function test_client_can_update_next_charge_date_for_own_active_domiciliation()
    {
        $nextDate = now()->addDays(10)->toDateString();

        $this->actingAs($this->clientAUser())
            ->put('/transaccion/proximo-cargo', [
                'id' => 200,
                'ProximoCargo' => $nextDate,
            ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJson([
                'error' => '',
                'ProximoCargo' => $nextDate,
            ]);

        $this->assertSame($nextDate, DB::table('transacciones')->where('id', 200)->value('ProximoCargo'));
    }

    public function test_client_cannot_update_next_charge_date_for_another_users_domiciliation()
    {
        $this->actingAs($this->clientAUser())
            ->put('/transaccion/proximo-cargo', [
                'id' => 201,
                'ProximoCargo' => now()->addDays(10)->toDateString(),
            ], $this->ajaxHeaders())
            ->assertStatus(403);
    }

    public function test_cannot_update_next_charge_date_for_inactive_domiciliation()
    {
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 2]);

        $this->actingAs($this->adminUser())
            ->put('/transaccion/proximo-cargo', [
                'id' => 200,
                'ProximoCargo' => now()->addDays(10)->toDateString(),
            ], $this->ajaxHeaders())
            ->assertStatus(422);
    }

    private function transactionRowForStatus($id, $condicion, $expirationDate)
    {
        return [
            'id' => $id,
            'folio' => $id,
            'fecha' => now(),
            'User' => 'mock',
            'Password' => 'mock',
            'IntegrationID' => '117',
            'BusinessID' => '000031',
            'PaymentTypes' => '41',
            'IdReference' => str_pad($id, 10, '0', STR_PAD_LEFT),
            'Description' => 'Domiciliacion status ' . $id,
            'Amount' => 10000,
            'Reference' => str_pad($id, 15, '0', STR_PAD_LEFT),
            'ExpirationDate' => $expirationDate,
            'ClientReference' => 'DOM-STATUS-' . $id,
            'response' => '{}',
            'url' => 'https://example.com/' . $id,
            'code' => 'success',
            'message' => 'ok',
            'responseReference' => 'RESP-STATUS-' . $id,
            'idusuario' => 2,
            'idcliente' => 10,
            'tipo' => 2,
            'frecuencia' => 2,
            'ProximoCargo' => now()->addMonth()->toDateString(),
            'ProximoCargoBase' => null,
            'intentos' => 0,
            'condicion' => $condicion,
            'productivo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function responseRowForStatus($id, $idtransaccion, $reference, $token)
    {
        return [
            'id' => $id,
            'idtransaccion' => $idtransaccion,
            'fecha' => now(),
            'reference' => $reference,
            'status' => 'approved',
            'foliocpagos' => 'FOLIO-' . $id,
            'auth' => 'AUTH-' . $id,
            'amount' => 10000,
            'number_tkn' => $token,
            'cc_expmonth' => '12',
            'cc_expyear' => '30',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
