<?php

namespace Tests\Feature\Phase32;

use App\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class ResponseViewerRoleFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedDatabase();
    }

    public function test_admin_can_create_multiple_response_viewers_for_the_same_active_client(): void
    {
        $select = $this->actingAs($this->adminUser())
            ->get('/user/selectClientesVinculables', $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame([2, 3], collect($select->json('clientes'))->pluck('id')->all());

        foreach (['reviewer-a', 'reviewer-b'] as $username) {
            $this->actingAs($this->adminUser())
                ->post('/user/registrar', $this->viewerPayload($username), $this->ajaxHeaders())
                ->assertOk()
                ->assertJson(['status' => 'success']);
        }

        $viewers = DB::table('users')->where('idrol', User::ROLE_CONSULTA_RESPUESTAS)->get();

        $this->assertCount(2, $viewers);
        foreach ($viewers as $viewer) {
            $this->assertSame(2, (int) $viewer->idusuario_vinculado);
            $this->assertSame(0, (int) $viewer->IntegrationID);
            $this->assertSame('N/A', $viewer->BusinessID);
            $this->assertSame(1, (int) $viewer->productivo);
        }
    }

    public function test_response_viewer_requires_an_active_client_link(): void
    {
        $missingLink = $this->viewerPayload('reviewer-missing');
        unset($missingLink['idusuario_vinculado']);

        $this->withHeaders($this->ajaxHeaders())->actingAs($this->adminUser())
            ->postJson('/user/registrar', $missingLink)
            ->assertStatus(422);

        $adminLink = $this->viewerPayload('reviewer-admin');
        $adminLink['idusuario_vinculado'] = 1;

        $this->withHeaders($this->ajaxHeaders())->actingAs($this->adminUser())
            ->postJson('/user/registrar', $adminLink)
            ->assertStatus(422);

        DB::table('users')->where('id', 2)->update(['condicion' => 0]);

        $this->withHeaders($this->ajaxHeaders())->actingAs($this->adminUser())
            ->postJson('/user/registrar', $this->viewerPayload('reviewer-inactive'))
            ->assertStatus(422);
    }

    public function test_response_viewer_reads_all_response_types_and_received_payments_for_linked_client_only(): void
    {
        $viewer = $this->createViewer();

        DB::table('respuestas')->insert($this->responseRow(5, 300, 'RESP-SPEI-VIEWER'));
        DB::table('transacciones')->insert($this->transactionRow(400, 2, 10, 4, 'TERMINAL-A', 'RESP-TERMINAL-A'));
        DB::table('respuestas')->insert($this->responseRow(6, 400, 'RESP-TERMINAL-VIEWER'));

        $expected = [
            1 => 'RESP-A',
            2 => 'RESP-DOM-A',
            3 => 'RESP-SPEI-VIEWER',
            4 => 'RESP-TERMINAL-VIEWER',
        ];

        foreach ($expected as $type => $reference) {
            $response = $this->actingAs($viewer)
                ->get('/respuesta?tipo='.$type.'&offset=10&buscar=&criterio=reference', $this->ajaxHeaders())
                ->assertOk();

            $response->assertJsonFragment(['reference' => $reference]);
            $response->assertJsonMissing(['reference' => 'RESP-B']);
            $this->assertArrayHasKey('number_tkn', $response->json('respuestas.data.0'));
        }

        $payments = $this->actingAs($viewer)
            ->get('/pagos-recibidos?offset=100&buscar=&criterio=cliente', $this->ajaxHeaders())
            ->assertOk();

        $this->assertNotEmpty($payments->json('pagos.data'));
        foreach ($payments->json('pagos.data') as $payment) {
            $this->assertSame(2, (int) $payment['idusuario']);
            $this->assertSame(1, (int) $payment['productivo']);
        }
    }

    public function test_response_viewer_cannot_export_write_or_access_other_modules(): void
    {
        $viewer = $this->createViewer();
        $responseCount = DB::table('respuestas')->count();

        $requests = [
            fn () => $this->actingAs($viewer)->get('/dashboard', $this->ajaxHeaders()),
            fn () => $this->actingAs($viewer)->get('/user?buscar=&criterio=nombre', $this->ajaxHeaders()),
            fn () => $this->actingAs($viewer)->get('/respuesta/exportar?tipo=1', $this->ajaxHeaders()),
            fn () => $this->actingAs($viewer)->get('/pagos-recibidos/exportar', $this->ajaxHeaders()),
            fn () => $this->actingAs($viewer)->post('/transaccion/registrar', [], $this->ajaxHeaders()),
            fn () => $this->actingAs($viewer)->post('/respuesta/registrar', ['reference' => 'BLOCKED'], $this->ajaxHeaders()),
            fn () => $this->actingAs($viewer)->put('/respuesta/actualizar', ['id' => 1], $this->ajaxHeaders()),
            fn () => $this->actingAs($viewer)->put('/pagos-recibidos/status', ['source_type' => 'respuesta', 'source_id' => 1, 'status' => 'cancelado'], $this->ajaxHeaders()),
        ];

        foreach ($requests as $request) {
            $request()->assertStatus(403);
        }

        $this->assertSame($responseCount, DB::table('respuestas')->count());
        $this->assertSame('approved', DB::table('respuestas')->where('id', 1)->value('status'));
        $this->assertDatabaseMissing('pagos_recibidos', ['source_type' => 'respuesta', 'source_id' => 1]);
    }

    public function test_response_viewer_shell_is_restricted_and_inactive_link_fails_closed(): void
    {
        $viewer = $this->createViewer();

        $shell = $this->actingAs($viewer)->get('/main')->assertOk();
        $shell->assertSee('Consulta de respuestas');
        $shell->assertSee('Pagos Recibidos');
        $shell->assertDontSee('Generar Liga de pago');
        $shell->assertDontSee('Usuarios');

        $this->actingAs($viewer)
            ->post('/user-activity/module', ['menu' => 2], $this->ajaxHeaders())
            ->assertOk();
        $this->actingAs($viewer)
            ->post('/user-activity/module', ['menu' => 1], $this->ajaxHeaders())
            ->assertStatus(403);

        DB::table('users')->where('id', 2)->update(['condicion' => 0]);
        $viewer->refresh();

        $this->actingAs($viewer)
            ->get('/respuesta?tipo=1&offset=10&buscar=&criterio=reference', $this->ajaxHeaders())
            ->assertStatus(403);
        $this->actingAs($viewer)->get('/main')->assertStatus(403);
    }

    public function test_admin_can_edit_relink_and_convert_response_viewer_roles(): void
    {
        $viewer = $this->createViewer();
        $password = $viewer->password;
        DB::table('users')->where('id', 3)->update(['productivo' => 0]);
        $payload = $this->viewerPayload('response-viewer');
        $payload['id'] = $viewer->id;
        $payload['idusuario_vinculado'] = 3;
        $payload['password'] = '';
        $payload['IntegrationID'] = '999';
        $payload['BusinessID'] = 'IGNORED';

        $this->actingAs($this->adminUser())->putJson('/user/actualizar', $payload, $this->ajaxHeaders())
            ->assertOk();
        $viewer->refresh();
        $this->assertSame(3, (int) $viewer->idusuario_vinculado);
        $this->assertSame(0, (int) $viewer->IntegrationID);
        $this->assertSame('N/A', $viewer->BusinessID);
        $this->assertSame(0, (int) $viewer->productivo);
        $this->assertSame($password, $viewer->password);

        foreach ([User::ROLE_CLIENTE, User::ROLE_ADMINISTRADOR] as $role) {
            $payload['idrol'] = $role;
            unset($payload['IntegrationID'], $payload['BusinessID']);
            $this->actingAs($this->adminUser())->putJson('/user/actualizar', $payload, $this->ajaxHeaders())
                ->assertUnprocessable()->assertJsonValidationErrors(['IntegrationID', 'BusinessID']);
            $payload['IntegrationID'] = '117';
            $payload['BusinessID'] = '000040';
            $payload['productivo'] = 1;
            $this->actingAs($this->adminUser())->putJson('/user/actualizar', $payload, $this->ajaxHeaders())
                ->assertOk();
            $viewer->refresh();
            $this->assertNull($viewer->idusuario_vinculado);
            $this->assertSame(117, (int) $viewer->IntegrationID);
            $this->assertSame('000040', $viewer->BusinessID);

            $payload['idrol'] = User::ROLE_CONSULTA_RESPUESTAS;
            $this->actingAs($this->adminUser())->putJson('/user/actualizar', $payload, $this->ajaxHeaders())
                ->assertOk();
            $viewer->refresh();
            $this->assertSame(0, (int) $viewer->IntegrationID);
            $this->assertSame(3, (int) $viewer->idusuario_vinculado);
        }
    }

    public function test_failed_user_insert_rolls_back_the_new_person(): void
    {
        $peopleBefore = DB::table('personas')->count();
        $usersBefore = DB::table('users')->count();
        // Force a real NOT NULL constraint failure after Persona has been inserted.
        User::creating(function (User $user) {
            $user->IntegrationID = null;
        });

        try {
            $this->actingAs($this->adminUser())
                ->postJson('/user/registrar', $this->viewerPayload('rollback-viewer'), $this->ajaxHeaders())
                ->assertStatus(500);
            $this->assertSame($peopleBefore, DB::table('personas')->count());
            $this->assertSame($usersBefore, DB::table('users')->count());
            $this->assertDatabaseMissing('personas', ['email' => 'rollback-viewer@example.com']);
        } finally {
            User::flushEventListeners();
        }
    }

    private function createViewer(): User
    {
        DB::table('personas')->insert([
            'id' => 4,
            'nombre' => 'Response Viewer',
            'tipo_documento' => 'USER',
            'num_documento' => '4',
            'email' => 'viewer@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => 4,
            'usuario' => 'response-viewer',
            'password' => bcrypt('secret'),
            'idrol' => User::ROLE_CONSULTA_RESPUESTAS,
            'idusuario_vinculado' => 2,
            'condicion' => 1,
            'IntegrationID' => 0,
            'BusinessID' => 'N/A',
            'productivo' => 1,
        ]);

        return User::findOrFail(4);
    }

    private function viewerPayload(string $username): array
    {
        return [
            'nombre' => 'Reviewer '.$username,
            'tipo_documento' => 'USUARIO',
            'num_documento' => strtoupper($username),
            'email' => $username.'@example.com',
            'idrol' => User::ROLE_CONSULTA_RESPUESTAS,
            'idusuario_vinculado' => 2,
            'usuario' => $username,
            'password' => 'secret123',
        ];
    }
}
