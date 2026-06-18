<?php

namespace Tests\Feature\UX;

use App\Http\Controllers\TransaccionController;
use App\User;
use Illuminate\Support\Facades\DB;
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

    public function test_revisar_status_marks_pending_domiciliacion_as_expired_after_expiration()
    {
        DB::table('transacciones')->insert($this->transactionRowForStatus(700, 0, now()->subDay()->toDateString()));

        app(TransaccionController::class)->revisarStatus();

        $this->assertSame(4, (int) DB::table('transacciones')->where('id', 700)->value('condicion'));
    }

    public function test_revisar_status_marks_approved_domiciliacion_without_token_as_error()
    {
        DB::table('transacciones')->insert($this->transactionRowForStatus(701, 0, now()->addDay()->toDateString()));
        DB::table('respuestas')->insert($this->responseRowForStatus(701, 701, 'RESP-NO-TOKEN', ''));

        app(TransaccionController::class)->revisarStatus();

        $this->assertSame(5, (int) DB::table('transacciones')->where('id', 701)->value('condicion'));
    }

    public function test_revisar_status_marks_approved_domiciliacion_with_token_as_active()
    {
        DB::table('transacciones')->insert($this->transactionRowForStatus(702, 0, now()->addDay()->toDateString()));
        DB::table('respuestas')->insert($this->responseRowForStatus(702, 702, 'RESP-WITH-TOKEN', 'TOKEN-OK'));

        app(TransaccionController::class)->revisarStatus();

        $transaccion = DB::table('transacciones')->where('id', 702)->first();
        $this->assertSame(1, (int) $transaccion->condicion);
        $this->assertSame(0, (int) $transaccion->intentos);
        $this->assertNotNull($transaccion->ProximoCargoBase);
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
