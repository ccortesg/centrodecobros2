<?php

namespace Tests\Feature\UX;

use Illuminate\Support\Facades\DB;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class ProxyAndCatalogUxFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpIsolatedDatabase();

        DB::table('estados')->insert([
            ['id' => 2, 'nombre' => 'Estado inactivo', 'condicion' => 0],
            ['id' => 3, 'nombre' => 'Estado activo adicional', 'condicion' => 1],
        ]);

        DB::table('ciudades')->insert([
            ['id' => 2, 'idestado' => 1, 'nombre' => 'Ciudad inactiva', 'condicion' => 0],
            ['id' => 3, 'idestado' => 3, 'nombre' => 'Ciudad activa adicional', 'condicion' => 1],
        ]);
    }

    public function test_logout_route_uses_https_when_reverse_proxy_reports_https()
    {
        $this->actingAs($this->adminUser())
            ->withServerVariables([
                'HTTP_HOST' => 'centrodecobros-internal',
                'HTTP_X_FORWARDED_HOST' => 'cc.soportetech.com.mx',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_PORT' => '443',
            ])
            ->get('/main')
            ->assertOk()
            ->assertSee('action="https://cc.soportetech.com.mx/logout"', false)
            ->assertDontSee('action="http://cc.soportetech.com.mx/logout"', false);
    }

    public function test_estado_status_filter_returns_only_requested_condition()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/estado?offset=25&buscar=&criterio=nombre&status=0', $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame(25, $response->json('pagination.per_page'));
        $this->assertNotEmpty($response->json('estados.data'));

        foreach ($response->json('estados.data') as $estado) {
            $this->assertSame(0, (int) $estado['condicion']);
        }
    }

    public function test_ciudad_status_filter_returns_only_requested_condition_and_uses_offset()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/ciudad?offset=25&buscar=&criterio=nombre&status=1', $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame(25, $response->json('pagination.per_page'));
        $this->assertNotEmpty($response->json('ciudades.data'));

        foreach ($response->json('ciudades.data') as $ciudad) {
            $this->assertSame(1, (int) $ciudad['condicion']);
        }
    }

    public function test_city_selector_exposes_state_id_for_client_default_location()
    {
        $this->actingAs($this->adminUser())
            ->get('/ciudad/selectCiudad', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonFragment([
                'id' => 1,
                'idestado' => 1,
                'nombre' => 'Hermosillo',
            ]);
    }

    public function test_client_role_can_read_location_selectors_for_customer_modal()
    {
        $this->actingAs($this->clientAUser())
            ->get('/estado/selectEstado', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonFragment([
                'id' => 1,
                'nombre' => 'Sonora',
            ]);

        $this->actingAs($this->clientAUser())
            ->get('/ciudad/selectCiudad', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonFragment([
                'id' => 1,
                'idestado' => 1,
                'nombre' => 'Hermosillo',
            ]);
    }

    public function test_catalog_filters_reject_invalid_criteria()
    {
        $this->actingAs($this->adminUser())
            ->get('/estado?offset=10&buscar=test&criterio=id&status=99', $this->ajaxHeaders())
            ->assertStatus(422);

        $this->actingAs($this->adminUser())
            ->get('/ciudad?offset=10&buscar=test&criterio=id&status=99', $this->ajaxHeaders())
            ->assertStatus(422);
    }
}
