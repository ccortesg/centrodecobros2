<?php

namespace Tests\Feature\Smoke;

use App\User;
use Tests\TestCase;

class AuthenticatedReadOnlySmokeTest extends TestCase
{
    private function activeUser(): User
    {
        $user = User::where('condicion', 1)->orderBy('id')->first();

        $this->assertNotNull($user, 'No active user available in the local dataset.');

        return $user;
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

    public function test_main_shell_renders_for_an_authenticated_user()
    {
        $this->actingAs($this->activeUser())
            ->get('/main')
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('data-shell-header="authenticated"', false)
            ->assertSee('data-shell-sidebar="authenticated"', false)
            ->assertSee('data-shell-dropdown-toggle="account"', false)
            ->assertSee('data-menu-target="0"', false)
            ->assertDontSee('data-toggle="dropdown"', false)
            ->assertSee('css/plantilla.css', false)
            ->assertSee('js/app.js', false)
            ->assertSee('js/plantilla.js', false);
    }

    public function test_dashboard_endpoint_returns_expected_json_shape()
    {
        $this->actingAs($this->activeUser())
            ->get('/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'transacciones',
                'importes',
            ]);
    }

    public function test_cliente_index_returns_paginated_payload()
    {
        $this->actingAs($this->activeUser())
            ->get('/cliente?offset=10&buscar=&criterio=nombre', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'pagination' => ['total', 'current_page', 'per_page', 'last_page', 'from', 'to'],
                'personas',
            ]);
    }

    public function test_transaccion_index_returns_paginated_payload()
    {
        $this->actingAs($this->activeUser())
            ->get('/transaccion?tipo=1&offset=10&buscar=&criterio=folio&status=99', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'pagination' => ['total', 'current_page', 'per_page', 'last_page', 'from', 'to'],
                'transacciones',
            ]);
    }

    public function test_respuesta_index_returns_paginated_payload()
    {
        $this->actingAs($this->activeUser())
            ->get('/respuesta?tipo=1&offset=10&buscar=&criterio=reference', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'pagination' => ['total', 'current_page', 'per_page', 'last_page', 'from', 'to'],
                'respuestas',
            ]);
    }

    public function test_transaccion_dom_index_returns_paginated_payload()
    {
        $this->actingAs($this->activeUser())
            ->get('/transaccionDom?tipo=2&offset=10&buscar=&criterio=Reference', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'pagination' => ['total', 'current_page', 'per_page', 'last_page', 'from', 'to'],
                'transaccionesDom',
            ]);
    }

    public function test_admin_read_only_modules_render_in_read_mode()
    {
        $admin = $this->activeAdmin();

        $this->actingAs($admin)
            ->get('/cliente/consolidar?idusuario=' . $admin->id . '&buscar=&offset=10', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'pagination' => ['total', 'current_page', 'per_page', 'last_page', 'from', 'to'],
                'clientes',
            ]);

        $this->actingAs($admin)
            ->get('/cliente/depurar?idusuario=' . $admin->id . '&buscar=&offset=10', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'pagination' => ['total', 'current_page', 'per_page', 'last_page', 'from', 'to'],
                'clientes',
            ]);
    }
}
