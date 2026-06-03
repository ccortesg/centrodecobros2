<?php

namespace Tests\Feature\Smoke;

use Tests\TestCase;

class PublicRoutesSmokeTest extends TestCase
{
    public function test_login_page_renders()
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Ingresar')
            ->assertSee('data-template-context="guest"', false)
            ->assertSee('data-template-view="auth"', false)
            ->assertSee('data-template-screen="login"', false)
            ->assertSee('css/plantilla.css', false)
            ->assertSee('js/guest-public.js', false)
            ->assertDontSee('js/plantilla.js', false);
    }

    public function test_main_redirects_guests_to_login()
    {
        $this->get('/main')->assertRedirect('/login');
    }

    public function test_dashboard_redirects_guests_to_login()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_root_entrypoint_redirects()
    {
        $this->get('/')->assertStatus(302);
    }
}
