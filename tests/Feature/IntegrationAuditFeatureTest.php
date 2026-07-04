<?php

namespace Tests\Feature;

use App\IncomingApiRequest;
use App\OutgoingApiRequest;
use App\UserActivityLog;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class IntegrationAuditFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedDatabase();
    }

    public function test_incoming_and_outgoing_logs_are_created_for_public_api_generation(): void
    {
        $this->postJson('/GenerarLigaPago', [
            'User' => 'client-a',
            'Password' => 'token-a',
            'Amount' => 150.50,
            'ExpirationDate' => now()->addDay()->toDateString(),
            'Reference' => 'AUDIT-REF',
            'Description' => 'Audit test',
        ])->assertOk();

        $this->assertDatabaseHas('incoming_api_requests', [
            'method' => 'POST',
            'path' => 'GenerarLigaPago',
            'status_code' => 200,
        ]);

        $this->assertDatabaseHas('outgoing_api_requests', [
            'provider' => 'Pagadetodo',
            'status_code' => 200,
            'success' => 1,
        ]);

        $incoming = IncomingApiRequest::where('path', 'GenerarLigaPago')->firstOrFail();
        $outgoing = OutgoingApiRequest::firstOrFail();

        $this->assertSame('[secreto omitido]', $incoming->request_payload['Password']);
        $this->assertStringNotContainsString('token-a', json_encode($outgoing->request_payload));
    }

    public function test_admin_can_list_and_export_audit_modules_but_client_cannot(): void
    {
        OutgoingApiRequest::create([
            'occurred_at' => now(),
            'provider' => 'Pagadetodo',
            'source_context' => 'test',
            'method' => 'POST',
            'url' => 'https://pagadetodo.mx/test',
            'host' => 'pagadetodo.mx',
            'status_code' => 200,
            'success' => true,
            'duration_ms' => 15,
            'request_payload' => ['Password' => '[secreto omitido]'],
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->actingAs($this->adminUser())
            ->get('/integraciones/outgoing-api-requests', $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('pagination.total', 1);

        $this->withHeaders($this->ajaxHeaders())
            ->actingAs($this->adminUser())
            ->get('/integraciones/outgoing-api-requests/exportar', $this->ajaxHeaders())
            ->assertOk();
    }

    public function test_client_cannot_access_audit_modules(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->actingAs($this->clientAUser())
            ->get('/integraciones/outgoing-api-requests', $this->ajaxHeaders())
            ->assertStatus(403);
    }

    public function test_admin_shell_shows_integrations_menu(): void
    {
        $this->actingAs($this->adminUser())
            ->get('/main')
            ->assertOk()
            ->assertSee('Integraciones')
            ->assertSee('Outgoing API Requests')
            ->assertSee('Incoming API Requests')
            ->assertSee('User Activity Log');
    }

    public function test_client_shell_does_not_show_integrations_menu(): void
    {
        $this->actingAs($this->clientAUser())
            ->get('/main')
            ->assertOk()
            ->assertDontSee('Integraciones')
            ->assertDontSee('Outgoing API Requests')
            ->assertDontSee('Incoming API Requests')
            ->assertDontSee('User Activity Log');
    }

    public function test_login_logout_and_module_access_are_logged(): void
    {
        $this->post('/login', [
            'usuario' => 'admin',
            'password' => 'secret',
        ])->assertRedirect('/main');

        $this->assertDatabaseHas('user_activity_logs', [
            'usuario' => 'admin',
            'action' => 'login_success',
            'success' => 1,
        ]);

        $this->actingAs($this->adminUser())
            ->postJson('/user-activity/module', [
                'menu' => 31,
            ])->assertOk();

        $this->assertDatabaseHas('user_activity_logs', [
            'idusuario' => 1,
            'action' => 'module_access',
            'module_key' => 31,
            'module_name' => 'Outgoing API Requests',
        ]);

        $this->actingAs($this->adminUser())
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertDatabaseHas('user_activity_logs', [
            'idusuario' => 1,
            'action' => 'logout',
        ]);
    }

    public function test_failed_login_is_logged_without_password(): void
    {
        $this->post('/login', [
            'usuario' => 'admin',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('usuario');

        $log = UserActivityLog::where('action', 'login_failed')->firstOrFail();

        $this->assertSame('admin', $log->usuario);
        $this->assertFalse((bool) $log->success);
        $this->assertStringNotContainsString('wrong-password', json_encode($log->metadata));
    }
}
