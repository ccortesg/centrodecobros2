<?php

namespace Tests\Feature\Smoke;

use App\Http\Middleware\VerifyCsrfToken;
use App\User;
use Tests\TestCase;

class LegacyFunctionalAlignmentSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function activeAdmin(): User
    {
        $admin = User::where('condicion', 1)->where('idrol', 1)->orderBy('id')->first();

        $this->assertNotNull($admin, 'No active admin available in the local dataset.');

        return $admin;
    }

    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    public function test_url_page_renders()
    {
        $this->get('/url')
            ->assertOk()
            ->assertSee('Registro')
            ->assertSee('data-template-context="guest"', false)
            ->assertSee('data-template-view="transaccion"', false)
            ->assertSee('data-template-screen="url"', false)
            ->assertSee('css/plantilla.css', false)
            ->assertSee('js/guest-public.js', false)
            ->assertDontSee('js/plantilla.js', false);
    }

    public function test_url_page_accepts_a_safe_http_url()
    {
        $this->from('/url')
            ->post('/url', ['url' => 'https://example.com/pago'])
            ->assertRedirect('/url')
            ->assertSessionHas('message', 'https://example.com/pago');
    }

    public function test_role_alias_route_returns_paginated_payload()
    {
        $this->actingAs($this->activeAdmin())
            ->get('/role?buscar=&criterio=nombre', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'pagination' => ['total', 'current_page', 'per_page', 'last_page', 'from', 'to'],
                'roles',
            ]);
    }

    public function test_reporte_spei_filtered_export_route_downloads_a_file()
    {
        $this->actingAs($this->activeAdmin())
            ->get('/pagospei/exportarReporteSpei?idcliente=0&fechaInicio=&fechaFin=', $this->ajaxHeaders())
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=reporteSpei.xlsx');
    }

    public function test_reporte_cargos_recurrentes_filtered_export_route_downloads_a_file()
    {
        $this->actingAs($this->activeAdmin())
            ->get('/transaccionDom/exportarTransacciones?idcliente=0&fechaInicio=&fechaFin=', $this->ajaxHeaders())
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=reporteTransaccionesDom.xlsx');
    }

    public function test_reporte_ligas_filtered_export_route_downloads_a_file_with_date_filters()
    {
        $this->actingAs($this->activeAdmin())
            ->get('/transaccion/exportarTransacciones?idcliente=0&fechaInicio=2026-03-01&fechaFin=2026-03-13&tipo=1', $this->ajaxHeaders())
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="Reporte Transacciones.xlsx"');
    }

    public function test_transaccion_generic_export_route_downloads_a_file_for_tipo_1()
    {
        $this->actingAs($this->activeAdmin())
            ->get('/transaccion/exportar?tipo=1', $this->ajaxHeaders())
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=transacciones.csv')
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
