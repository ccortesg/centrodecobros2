<?php

namespace Tests\Feature\UX;

use App\Http\Controllers\TransaccionController;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class FinancialFiltersFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpIsolatedDatabase();

        DB::table('transacciones')->where('id', 200)->update([
            'condicion' => 2,
            'status' => null,
        ]);

        DB::table('pagospei')->insert([
            'id' => 3,
            'idtransaccion' => 300,
            'fecha' => now(),
            'clabe' => '012345678901234569',
            'fecha_peticion' => now(),
            'monto' => 30000,
            'transaccion' => 'PAY-C',
            'codigo' => '05',
            'autorizacion' => 'AUTH-C',
            'mensaje' => 'rechazado',
            'condicion' => 0,
            'enviada' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cancelaspei')->insert([
            'id' => 3,
            'idtransaccion' => 300,
            'fecha' => now(),
            'clabe' => '012345678901234569',
            'fecha_peticion' => now(),
            'monto' => 30000,
            'transaccion' => 'CAN-C',
            'codigo' => '00',
            'autorizacion' => 'AUTH-C',
            'mensaje' => 'ok',
            'enviada' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('respuestas')->insert([
            'id' => 10,
            'idtransaccion' => 100,
            'fecha' => now(),
            'reference' => 'RESP-DENIED',
            'status' => 'denied',
            'foliocpagos' => 'FOLIO-DENIED',
            'auth' => 'AUTH-DENIED',
            'amount' => 30000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transaccionesDom')->insert([
            'id' => 10,
            'fecha' => now(),
            'folio' => 10,
            'idtransaccion' => 200,
            'idcliente' => 10,
            'Token' => 'TOKEN-DENIED',
            'Reference' => 'DOM-DENIED',
            'Amount' => 30000,
            'response' => '{}',
            'code' => '05',
            'message' => 'rechazado',
            'response_reference' => 'DOM-DENIED',
            'status' => 'denied',
            'idusuario' => 2,
            'productivo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_transaccion_status_filter_uses_condicion_not_missing_status_column()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=2&offset=10&buscar=&criterio=Reference&status=2', $this->ajaxHeaders())
            ->assertOk();

        $this->assertNotEmpty($response->json('transacciones.data'));

        foreach ($response->json('transacciones.data') as $transaccion) {
            $this->assertSame(2, (int) $transaccion['condicion']);
        }

        $this->assertContains(200, array_column($response->json('transacciones.data'), 'id'));
    }

    public function test_transaccion_status_all_keeps_paginated_payload()
    {
        $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=2&offset=10&buscar=&criterio=Reference&status=99', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'pagination' => ['total', 'current_page', 'per_page', 'last_page', 'from', 'to'],
                'transacciones',
            ]);
    }

    public function test_transaccion_rejects_invalid_status_and_criteria()
    {
        $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=2&offset=10&buscar=&criterio=Reference&status=7', $this->ajaxHeaders())
            ->assertStatus(422);

        $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=2&offset=10&buscar=x&criterio=status&status=99', $this->ajaxHeaders())
            ->assertStatus(422);
    }

    public function test_transaccion_status_filter_rejects_status_not_allowed_for_type()
    {
        $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=1&offset=10&buscar=&criterio=Reference&status=0', $this->ajaxHeaders())
            ->assertStatus(422);

        $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=2&offset=10&buscar=&criterio=Reference&status=3', $this->ajaxHeaders())
            ->assertStatus(422);

        $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=4&offset=10&buscar=&criterio=Reference&status=2', $this->ajaxHeaders())
            ->assertStatus(422);
    }

    public function test_transaccion_ref_respuesta_filter_finds_related_respuesta_reference()
    {
        DB::table('transacciones')->where('id', 100)->update([
            'responseReference' => 'TX-REFERENCE-ONLY',
        ]);
        DB::table('respuestas')->where('id', 1)->update([
            'reference' => 'RESPUESTA-REAL-100',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=1&offset=10&buscar=RESPUESTA-REAL-100&criterio=responseReference&status=99', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment([
            'id' => 100,
            'responseReference' => 'TX-REFERENCE-ONLY',
        ]);
    }

    public function test_transaccion_filters_by_creation_date_range()
    {
        DB::table('transacciones')->whereIn('id', [100, 101])->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('transacciones')->where('id', 100)->update(['fecha' => '2026-06-03 12:00:00']);

        $response = $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=1&offset=50&buscar=&criterio=Reference&status=99&fechaInicio=2026-06-03&fechaFin=2026-06-03', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['id' => 100]);
        $response->assertJsonMissing(['id' => 101]);

        $this->actingAs($this->adminUser())
            ->get('/transaccion?tipo=1&offset=50&buscar=&criterio=Reference&status=99&fechaInicio=2026-06-04&fechaFin=2026-06-03', $this->ajaxHeaders())
            ->assertStatus(422);
    }

    public function test_pagospei_filters_by_condicion_and_enviada()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/pagospei?offset=10&buscar=&criterio=clabe&condicion=0&enviada=1', $this->ajaxHeaders())
            ->assertOk();

        $this->assertNotEmpty($response->json('pagospei.data'));

        foreach ($response->json('pagospei.data') as $pago) {
            $this->assertSame(0, (int) $pago['condicion']);
            $this->assertSame(1, (int) $pago['enviada']);
        }
    }

    public function test_cancelaspei_filters_by_enviada()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/cancelaspei?offset=10&buscar=&criterio=clabe&enviada=1', $this->ajaxHeaders())
            ->assertOk();

        $this->assertNotEmpty($response->json('cancelaspei.data'));

        foreach ($response->json('cancelaspei.data') as $cancelacion) {
            $this->assertSame(1, (int) $cancelacion['enviada']);
        }
    }

    public function test_revisar_status_marks_expired_active_links_spei_and_terminal_as_expired()
    {
        $expired = now()->subDay()->toDateString();

        DB::table('transacciones')->where('id', 100)->update([
            'tipo' => 1,
            'condicion' => 1,
            'ExpirationDate' => $expired,
        ]);
        DB::table('transacciones')->where('id', 200)->update([
            'tipo' => 2,
            'condicion' => 0,
            'ExpirationDate' => $expired,
        ]);
        DB::table('respuestas')->where('idtransaccion', 200)->delete();
        DB::table('transacciones')->where('id', 300)->update([
            'tipo' => 3,
            'condicion' => 1,
            'ExpirationDate' => $expired,
        ]);
        DB::table('transacciones')->where('id', 101)->update([
            'tipo' => 4,
            'condicion' => 1,
            'ExpirationDate' => $expired,
        ]);

        app(TransaccionController::class)->revisarStatus();

        $this->assertSame(4, (int) DB::table('transacciones')->where('id', 100)->value('condicion'));
        $this->assertSame(4, (int) DB::table('transacciones')->where('id', 200)->value('condicion'));
        $this->assertSame(4, (int) DB::table('transacciones')->where('id', 300)->value('condicion'));
        $this->assertSame(4, (int) DB::table('transacciones')->where('id', 101)->value('condicion'));
    }

    public function test_revisar_status_does_not_expire_paid_cancelled_or_error_transactions()
    {
        $expired = now()->subDay()->toDateString();

        DB::table('transacciones')->where('id', 100)->update([
            'tipo' => 1,
            'condicion' => 3,
            'ExpirationDate' => $expired,
        ]);
        DB::table('transacciones')->where('id', 200)->update([
            'tipo' => 2,
            'condicion' => 2,
            'ExpirationDate' => $expired,
        ]);
        DB::table('transacciones')->where('id', 300)->update([
            'tipo' => 3,
            'condicion' => 5,
            'ExpirationDate' => $expired,
        ]);

        app(TransaccionController::class)->revisarStatus();

        $this->assertSame(3, (int) DB::table('transacciones')->where('id', 100)->value('condicion'));
        $this->assertSame(2, (int) DB::table('transacciones')->where('id', 200)->value('condicion'));
        $this->assertSame(5, (int) DB::table('transacciones')->where('id', 300)->value('condicion'));
    }

    public function test_respuesta_filters_by_existing_status_column()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/respuesta?tipo=1&offset=10&buscar=&criterio=reference&status=denied', $this->ajaxHeaders())
            ->assertOk();

        $this->assertNotEmpty($response->json('respuestas.data'));

        foreach ($response->json('respuestas.data') as $respuesta) {
            $this->assertSame('denied', $respuesta['status']);
        }
    }

    public function test_respuesta_ref_respuesta_filter_uses_respuestas_reference_column()
    {
        DB::table('transacciones')->where('id', 100)->update([
            'responseReference' => 'TX-REFERENCE-ONLY',
        ]);
        DB::table('respuestas')->where('id', 1)->update([
            'reference' => 'RESPUESTA-REAL-100',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->get('/respuesta?tipo=1&offset=10&buscar=RESPUESTA-REAL-100&criterio=reference&status=99', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment([
            'id' => 1,
            'reference' => 'RESPUESTA-REAL-100',
        ]);
    }

    public function test_respuesta_filters_by_response_date_range()
    {
        DB::table('respuestas')->whereIn('id', [1, 2])->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('respuestas')->where('id', 1)->update(['fecha' => '2026-06-03 12:00:00']);

        $response = $this->actingAs($this->adminUser())
            ->get('/respuesta?tipo=1&offset=50&buscar=&criterio=reference&status=99&fechaInicio=2026-06-03&fechaFin=2026-06-03', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['id' => 1]);
        $response->assertJsonMissing(['id' => 2]);
    }

    public function test_transaccion_dom_filters_by_existing_status_column()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/transaccionDom?offset=10&buscar=&criterio=Reference&status=denied', $this->ajaxHeaders())
            ->assertOk();

        $this->assertNotEmpty($response->json('transaccionesDom.data'));

        foreach ($response->json('transaccionesDom.data') as $transaccionDom) {
            $this->assertSame('denied', $transaccionDom['status']);
        }
    }

    public function test_transaccion_dom_filters_by_charge_date_range()
    {
        DB::table('transaccionesDom')->update(['fecha' => '2026-05-01 10:00:00']);
        DB::table('transaccionesDom')->where('id', 1)->update(['fecha' => '2026-06-03 12:00:00']);

        $response = $this->actingAs($this->adminUser())
            ->get('/transaccionDom?offset=50&buscar=&criterio=Reference&status=99&fechaInicio=2026-06-03&fechaFin=2026-06-03', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['id' => 1]);
        $response->assertJsonMissing(['id' => 2]);
        $response->assertJsonMissing(['id' => 10]);
    }
}
