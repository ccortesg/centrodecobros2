<?php

namespace Tests\Feature\Phase34;

use Illuminate\Support\Facades\DB;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class WebhookIdempotencyFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedDatabase();
    }

    public function test_liga_webhook_rejects_payload_without_required_schema()
    {
        $count = DB::table('respuestas')->count();

        $this->postJson('/Service/EntregarPagoLiga', [
            'reference' => 'RESP-NEW',
        ])
            ->assertStatus(422)
            ->assertSee('error', false);

        $this->assertSame($count, DB::table('respuestas')->count());
    }

    public function test_liga_webhook_accepts_minimal_valid_payload_without_optional_fields()
    {
        $this->postJson('/Service/EntregarPagoLiga', [
            'reference' => 'RESP-SPEI-A',
            'response' => 'approved',
            'amount' => 12345,
        ])
            ->assertOk()
            ->assertSee('success', false);

        $this->assertDatabaseHas('respuestas', [
            'idtransaccion' => 300,
            'reference' => 'RESP-SPEI-A',
            'status' => 'approved',
            'amount' => 12345,
        ]);
    }

    public function test_liga_webhook_marks_unique_payment_link_as_paid_when_approved()
    {
        DB::table('transacciones')->where('id', 100)->update([
            'responseReference' => 'RESP-LIGA-UNICA-PAGADA',
            'condicion' => 1,
        ]);

        $this->postJson('/Service/EntregarPagoLiga', [
            'reference' => 'RESP-LIGA-UNICA-PAGADA',
            'response' => 'approved',
            'amount' => 10000,
        ])
            ->assertOk()
            ->assertSee('success', false);

        $this->assertSame(3, (int) DB::table('transacciones')->where('id', 100)->value('condicion'));
    }

    public function test_liga_webhook_keeps_domiciliation_active_when_approved_with_token()
    {
        DB::table('transacciones')->where('id', 200)->update([
            'responseReference' => 'RESP-DOM-WEBHOOK-TOKEN',
            'condicion' => 0,
        ]);

        $this->postJson('/Service/EntregarPagoLiga', [
            'reference' => 'RESP-DOM-WEBHOOK-TOKEN',
            'response' => 'approved',
            'amount' => 10000,
            'number_tkn' => 'TOKEN-WEBHOOK',
        ])
            ->assertOk()
            ->assertSee('success', false);

        $this->assertSame(1, (int) DB::table('transacciones')->where('id', 200)->value('condicion'));
    }

    public function test_liga_webhook_marks_domiciliation_as_error_when_approved_without_token()
    {
        DB::table('transacciones')->where('id', 201)->update([
            'responseReference' => 'RESP-DOM-WEBHOOK-SIN-TOKEN',
            'condicion' => 0,
        ]);

        $this->postJson('/Service/EntregarPagoLiga', [
            'reference' => 'RESP-DOM-WEBHOOK-SIN-TOKEN',
            'response' => 'approved',
            'amount' => 10000,
        ])
            ->assertOk()
            ->assertSee('success', false);

        $this->assertSame(5, (int) DB::table('transacciones')->where('id', 201)->value('condicion'));
    }

    public function test_liga_webhook_is_idempotent_for_duplicate_reference()
    {
        $count = DB::table('respuestas')
            ->where('idtransaccion', 100)
            ->where('reference', 'RESP-A')
            ->count();

        $this->assertSame(1, $count);

        $this->postJson('/Service/EntregarPagoLiga', [
            'reference' => 'RESP-A',
            'response' => 'approved',
            'amount' => 10000,
            'foliocpagos' => 'FOLIO-DUP',
        ])
            ->assertOk()
            ->assertSee('success', false);

        $this->assertSame(1, DB::table('respuestas')
            ->where('idtransaccion', 100)
            ->where('reference', 'RESP-A')
            ->count());
    }

    public function test_lector_webhook_is_idempotent_after_first_insert()
    {
        $payload = [
            'reference' => 'RESP-SPEI-A',
            'response' => 'approved',
            'amount' => 25000,
            'folio' => 'LECTOR-FOLIO-A',
            'auth' => 'LECTOR-AUTH-A',
        ];

        $this->postJson('/Service/EntregarPagoLector', $payload)
            ->assertOk()
            ->assertSee('success', false);

        $this->postJson('/Service/EntregarPagoLector', $payload)
            ->assertOk()
            ->assertSee('success', false);

        $this->assertSame(1, DB::table('respuestas')
            ->where('idtransaccion', 300)
            ->where('reference', 'RESP-SPEI-A')
            ->count());

        $this->assertDatabaseHas('respuestas', [
            'idtransaccion' => 300,
            'reference' => 'RESP-SPEI-A',
            'status' => 'approved',
            'foliocpagos' => 'LECTOR-FOLIO-A',
            'auth' => 'LECTOR-AUTH-A',
        ]);
    }

    public function test_lector_webhook_rejects_invalid_amount()
    {
        $this->postJson('/Service/EntregarPagoLector', [
            'reference' => 'RESP-SPEI-A',
            'response' => 'approved',
            'amount' => 'NO-NUMERICO',
        ])
            ->assertStatus(422)
            ->assertSee('error', false);
    }

    public function test_lector_webhook_marks_terminal_payment_link_as_paid_when_approved()
    {
        DB::table('transacciones')->insert([
            'id' => 400,
            'folio' => 400,
            'fecha' => now(),
            'User' => 'mock',
            'Password' => 'mock',
            'IntegrationID' => '117',
            'BusinessID' => '000031',
            'PaymentTypes' => '0',
            'IdReference' => '0000000400',
            'Description' => 'Terminal webhook',
            'Amount' => 10000,
            'Reference' => '000000000000400',
            'ExpirationDate' => now()->addDay()->toDateString(),
            'ClientReference' => 'LECTOR-WEBHOOK',
            'response' => '{}',
            'code' => 'success',
            'message' => 'ok',
            'responseReference' => 'RESP-LECTOR-PAGADO',
            'referenceEmisor' => 'EMISOR-400',
            'idusuario' => 2,
            'idcliente' => 10,
            'tipo' => 4,
            'condicion' => 1,
            'productivo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/Service/EntregarPagoLector', [
            'reference' => 'RESP-LECTOR-PAGADO',
            'response' => 'approved',
            'amount' => 10000,
            'folio' => 'LECTOR-FOLIO-PAGADO',
            'auth' => 'LECTOR-AUTH-PAGADO',
        ])
            ->assertOk()
            ->assertSee('success', false);

        $this->assertSame(3, (int) DB::table('transacciones')->where('id', 400)->value('condicion'));
    }

    public function test_consulta_clabe_empty_reference_returns_controlled_error()
    {
        $this->getJson('/Service/ConsultaClabe')
            ->assertOk()
            ->assertJson([
                'codigo' => '50',
                'mensaje' => 'Error de sistema',
            ]);

        $this->assertDatabaseHas('consultaspei', [
            'codigo' => '50',
            'mensaje' => 'Error de sistema',
        ]);
    }

    public function test_pago_clabe_rejects_payload_without_required_schema()
    {
        $count = DB::table('pagospei')->count();

        $this->postJson('/Service/PagoClabe', [
            'clabe' => '012345678901234567',
        ])
            ->assertOk()
            ->assertJson([
                'codigo' => '50',
                'mensaje' => 'Error de sistema',
            ]);

        $this->assertSame($count, DB::table('pagospei')->count());
    }

    public function test_pago_clabe_duplicate_transaction_returns_existing_result_without_new_row()
    {
        $count = DB::table('pagospei')->where('transaccion', 'PAY-A')->count();
        $this->assertSame(1, $count);

        $this->postJson('/Service/PagoClabe', [
            'clabe' => '012345678901234567',
            'monto' => 10000,
            'fecha' => now()->toDateString(),
            'transaccion' => 'PAY-A',
        ])
            ->assertOk()
            ->assertJson([
                'codigo' => '00',
                'transaccion' => 'PAY-A',
            ]);

        $this->assertSame(1, DB::table('pagospei')->where('transaccion', 'PAY-A')->count());
    }

    public function test_pago_clabe_success_marks_spei_transaction_as_paid()
    {
        DB::table('transacciones')->where('id', 300)->update([
            'condicion' => 1,
            'Clabe' => '012345678901234567',
        ]);

        $this->postJson('/Service/PagoClabe', [
            'clabe' => '012345678901234567',
            'monto' => 10000,
            'fecha' => now()->toDateString(),
            'transaccion' => 'PAY-SPEI-PAGADO',
        ])
            ->assertOk()
            ->assertJson([
                'codigo' => '0',
                'mensaje' => 'OperaciÃ³n exitosa',
            ]);

        $this->assertSame(3, (int) DB::table('transacciones')->where('id', 300)->value('condicion'));
    }

    public function test_cancela_clabe_rejects_payload_without_required_schema()
    {
        $count = DB::table('cancelaspei')->count();

        $this->postJson('/Service/CancelaClabe', [
            'clabe' => '012345678901234567',
        ])
            ->assertOk()
            ->assertJson([
                'codigo' => '50',
                'mensaje' => 'Error de sistema',
            ]);

        $this->assertSame($count, DB::table('cancelaspei')->count());
    }

    public function test_cancela_clabe_duplicate_transaction_returns_existing_result_without_new_row()
    {
        DB::table('cancelaspei')
            ->where('transaccion', 'CAN-A')
            ->update(['autorizacion' => 'AUTH-CAN-A']);

        $this->assertSame(1, DB::table('cancelaspei')
            ->where('transaccion', 'CAN-A')
            ->where('autorizacion', 'AUTH-CAN-A')
            ->count());

        $this->postJson('/Service/CancelaClabe', [
            'clabe' => '012345678901234567',
            'fecha' => now()->toDateString(),
            'monto' => 10000,
            'transaccion' => 'CAN-A',
            'autorizacion' => 'AUTH-CAN-A',
        ])
            ->assertOk()
            ->assertJson([
                'codigo' => '00',
                'mensaje' => 'ok',
            ]);

        $this->assertSame(1, DB::table('cancelaspei')
            ->where('transaccion', 'CAN-A')
            ->where('autorizacion', 'AUTH-CAN-A')
            ->count());
    }
}
