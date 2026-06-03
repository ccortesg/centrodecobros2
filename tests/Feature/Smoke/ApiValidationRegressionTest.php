<?php

namespace Tests\Feature\Smoke;

use Tests\TestCase;

class ApiValidationRegressionTest extends TestCase
{
    public function test_generar_spei_validates_credentials_before_database_lookup()
    {
        $this->postJson('/GenerarSpei', [
            'Email' => 'cliente@example.com',
            'Amount' => 100,
            'ExpirationDate' => '2026-12-31',
            'Reference' => 'REF-1',
            'Description' => 'Pago SPEI',
        ])
            ->assertStatus(400)
            ->assertJson([
                'code' => '02',
            ]);
    }

    public function test_generar_lector_validates_credentials_before_database_lookup()
    {
        $this->postJson('/GenerarLigaLector', [
            'Amount' => 100,
            'Reference' => 'REF-1',
            'Description' => 'Pago lector',
        ])
            ->assertStatus(400)
            ->assertJson([
                'code' => '02',
            ]);
    }

    public function test_generar_domiciliacion_validates_credentials_before_database_lookup()
    {
        $this->postJson('/GenerarLigaDomiciliacion', [
            'Amount' => 100,
            'ExpirationDate' => '2026-12-31',
            'Reference' => 'REF-1',
            'Description' => 'Domiciliacion',
            'Frecuencia' => 'mensual',
        ])
            ->assertStatus(400)
            ->assertJson([
                'code' => '02',
            ]);
    }

    public function test_cargo_domiciliacion_validates_credentials_before_database_lookup()
    {
        $this->postJson('/CargoDomiciliacion', [
            'ClientReference' => 'REF-1',
            'Amount' => 0,
        ])
            ->assertStatus(400)
            ->assertJson([
                'code' => '02',
            ]);
    }

    public function test_cancelar_domiciliacion_no_longer_uses_undefined_exception()
    {
        $this->postJson('/CancelarDomiciliacion', [
            'ClientReference' => 'REF-1',
        ])
            ->assertStatus(400)
            ->assertJson([
                'code' => '02',
            ]);
    }
}
