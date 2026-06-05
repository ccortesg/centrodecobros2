<?php

namespace Tests\Feature\UX;

use App\Http\Controllers\TransaccionController;
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
        DB::table('transacciones')->where('id', 200)->update(['condicion' => 1, 'intentos' => 4]);

        $this->actingAs($this->adminUser())
            ->post('/transaccionDom/registrar', ['idtransaccion' => 200], $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame(0, (int) DB::table('transacciones')->where('id', 200)->value('intentos'));
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
