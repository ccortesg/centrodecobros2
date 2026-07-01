<?php

namespace Tests\Feature\Phase32;

use Illuminate\Support\Facades\DB;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class PagadetodoContractMockFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedDatabase();
    }

    public function test_generar_liga_pago_uses_controlled_mock_contract()
    {
        $this->postJson('/GenerarLigaPago', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Amount' => 100,
            'ExpirationDate' => now()->addDays(3)->toDateString(),
            'Reference' => 'API-LIGA-A',
            'Description' => 'Liga mock',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'url' => 'https://mock.pagadetodo.local/pago',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-LIGA-A',
            'idusuario' => 2,
            'tipo' => 1,
        ]);
    }

    public function test_generar_liga_pago_with_service_error_is_persisted_as_error_status()
    {
        $this->postJson('/GenerarLigaPago', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Amount' => 100,
            'ExpirationDate' => now()->addDays(3)->toDateString(),
            'Reference' => 'API-LIGA-ERROR',
            'Description' => 'MOCK_LIGA_ERROR',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'error',
                'url' => '',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-LIGA-ERROR',
            'tipo' => 1,
            'condicion' => 5,
        ]);
    }

    public function test_generar_liga_pago_without_url_is_persisted_as_error_status()
    {
        $this->postJson('/GenerarLigaPago', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Amount' => 100,
            'ExpirationDate' => now()->addDays(3)->toDateString(),
            'Reference' => 'API-LIGA-SIN-URL',
            'Description' => 'MOCK_MISSING_URL',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'url' => '',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-LIGA-SIN-URL',
            'tipo' => 1,
            'condicion' => 5,
        ]);
    }

    public function test_generar_domiciliacion_uses_controlled_mock_contract()
    {
        $this->postJson('/GenerarLigaDomiciliacion', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Amount' => 100,
            'ExpirationDate' => now()->addDays(3)->toDateString(),
            'Reference' => 'API-DOM-A',
            'Description' => 'Domiciliacion mock',
            'Frecuencia' => 'mensual',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'url' => 'https://mock.pagadetodo.local/pago',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-DOM-A',
            'idusuario' => 2,
            'tipo' => 2,
        ]);
    }

    public function test_generar_domiciliacion_without_url_is_persisted_as_error_status()
    {
        $this->postJson('/GenerarLigaDomiciliacion', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Amount' => 100,
            'ExpirationDate' => now()->addDays(3)->toDateString(),
            'Reference' => 'API-DOM-SIN-URL',
            'Description' => 'MOCK_MISSING_URL',
            'Frecuencia' => 'mensual',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'url' => '',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-DOM-SIN-URL',
            'tipo' => 2,
            'condicion' => 5,
        ]);
    }

    public function test_generar_spei_uses_controlled_mock_contract()
    {
        $this->postJson('/GenerarSpei', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Email' => 'cliente-a@example.com',
            'Amount' => 100,
            'ExpirationDate' => now()->addDays(3)->toDateString(),
            'Reference' => 'API-SPEI-A',
            'Description' => 'SPEI mock',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'clabe' => '012345678901234567',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-SPEI-A',
            'idusuario' => 2,
            'tipo' => 3,
        ]);
    }

    public function test_generar_spei_without_clabe_is_persisted_as_error_status()
    {
        $this->postJson('/GenerarSpei', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Email' => 'cliente-a@example.com',
            'Amount' => 100,
            'ExpirationDate' => now()->addDays(3)->toDateString(),
            'Reference' => 'API-SPEI-SIN-CLABE',
            'Description' => 'MOCK_MISSING_CLABE',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'clabe' => '',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-SPEI-SIN-CLABE',
            'tipo' => 3,
            'condicion' => 5,
        ]);
    }

    public function test_generar_lector_uses_controlled_mock_contract()
    {
        $this->postJson('/GenerarLigaLector', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Amount' => 100,
            'Reference' => 'API-LECTOR-A',
            'Description' => 'Lector mock',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'reference' => '000000000000102',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-LECTOR-A',
            'idusuario' => 2,
            'tipo' => 4,
        ]);
    }

    public function test_generar_lector_without_qr_or_reference_is_persisted_as_error_status()
    {
        $this->postJson('/GenerarLigaLector', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Amount' => 100,
            'Reference' => 'API-LECTOR-SIN-REFERENCIA',
            'Description' => 'MOCK_MISSING_LECTOR_REFERENCE',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'reference' => '',
            ]);

        $this->assertDatabaseHas('transacciones', [
            'ClientReference' => 'API-LECTOR-SIN-REFERENCIA',
            'tipo' => 4,
            'condicion' => 5,
        ]);
    }

    public function test_cargo_domiciliacion_uses_controlled_mock_contract()
    {
        $this->postJson('/CargoDomiciliacion', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'ClientReference' => 'DOM-A',
            'Amount' => 0,
        ])
            ->assertOk()
            ->assertJson([
                'code' => '00',
                'message' => 'approved',
            ]);

        $this->assertSame(2, DB::table('transaccionesDom')->where('idusuario', 2)->count());
    }

    public function test_cancelar_domiciliacion_uses_controlled_mock_contract()
    {
        $this->postJson('/CancelarDomiciliacion', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'ClientReference' => 'DOM-A',
        ])
            ->assertOk()
            ->assertJson([
                'code' => 'success',
                'message' => 'MOCK cancelacion registrada',
            ]);

        $this->assertDatabaseHas('cancelacionesDom', [
            'idusuario' => 2,
            'Token' => 'TOKEN-A',
            'code' => 'success',
        ]);
    }
}
