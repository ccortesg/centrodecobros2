<?php

namespace Tests\Feature\Phase32;

use App\Http\Middleware\Administrador;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class AccessOwnershipFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedDatabase();
    }

    public function test_admin_can_access_user_administration_without_password_hashes()
    {
        $response = $this->actingAs($this->adminUser())
            ->get('/user?buscar=&criterio=nombre', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonMissing(['password' => true]);
        $this->assertArrayHasKey('personas', $response->json());
    }

    public function test_client_cannot_access_admin_user_administration()
    {
        $this->actingAs($this->clientAUser())
            ->get('/user?buscar=&criterio=nombre', $this->ajaxHeaders())
            ->assertStatus(403)
            ->assertJson(['status' => 'error']);
    }

    public function test_client_only_lists_own_clients()
    {
        $response = $this->actingAs($this->clientAUser())
            ->get('/cliente?offset=10&buscar=&criterio=nombre', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['razon_social' => 'Cliente A SA']);
        $response->assertJsonMissing(['razon_social' => 'Cliente B SA']);
    }

    public function test_client_cannot_update_another_clients_customer()
    {
        $this->actingAs($this->clientAUser())
            ->put('/cliente/actualizar', [
                'id' => 20,
                'nombre' => 'Cliente B Editado',
                'tipo_documento' => 'CLIENTE',
                'num_documento' => '20',
                'idciudad' => 1,
                'razon_social' => 'Cliente B Editado',
            ], $this->ajaxHeaders())
            ->assertStatus(403);
    }

    public function test_client_cannot_read_another_clients_files()
    {
        $this->actingAs($this->clientAUser())
            ->get('/archivo?idpersona=20', $this->ajaxHeaders())
            ->assertStatus(403);
    }

    public function test_client_only_lists_own_transactions()
    {
        $response = $this->actingAs($this->clientAUser())
            ->get('/transaccion?tipo=1&offset=10&buscar=&criterio=folio&status=99', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['ClientReference' => 'LIGA-A']);
        $response->assertJsonMissing(['ClientReference' => 'LIGA-B']);
    }

    public function test_client_cannot_update_another_clients_transaction()
    {
        $this->actingAs($this->clientAUser())
            ->put('/transaccion/actualizar', [
                'id' => 101,
                'ClientReference' => 'INTENTO-AJENO',
                'idcliente' => 20,
            ], $this->ajaxHeaders())
            ->assertStatus(403);
    }

    public function test_client_only_lists_own_responses()
    {
        $response = $this->actingAs($this->clientAUser())
            ->get('/respuesta?tipo=1&offset=10&buscar=&criterio=reference', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['reference' => 'RESP-A']);
        $response->assertJsonMissing(['reference' => 'RESP-B']);
    }

    public function test_client_only_lists_own_domiciliation_charges()
    {
        $response = $this->actingAs($this->clientAUser())
            ->get('/transaccionDom?tipo=2&offset=10&buscar=&criterio=Reference', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['response_reference' => 'DOM-CHARGE-A']);
        $response->assertJsonMissing(['response_reference' => 'DOM-CHARGE-B']);
    }

    public function test_spei_controllers_apply_ownership_when_route_middleware_allows_execution()
    {
        $this->withoutMiddleware(Administrador::class);

        $consulta = $this->actingAs($this->clientAUser())
            ->get('/consultaspei?offset=10&buscar=&criterio=reference', $this->ajaxHeaders())
            ->assertOk();
        $consulta->assertJsonFragment(['reference' => 'SPEI-A']);
        $consulta->assertJsonMissing(['reference' => 'SPEI-B']);

        $pago = $this->actingAs($this->clientAUser())
            ->get('/pagospei?offset=10&buscar=&criterio=clabe', $this->ajaxHeaders())
            ->assertOk();
        $pago->assertJsonFragment(['transaccion' => 'PAY-A']);
        $pago->assertJsonMissing(['transaccion' => 'PAY-B']);

        $cancelacion = $this->actingAs($this->clientAUser())
            ->get('/cancelaspei?offset=10&buscar=&criterio=clabe', $this->ajaxHeaders())
            ->assertOk();
        $cancelacion->assertJsonFragment(['transaccion' => 'CAN-A']);
        $cancelacion->assertJsonMissing(['transaccion' => 'CAN-B']);
    }

    public function test_dynamic_search_criteria_are_rejected_when_not_whitelisted()
    {
        $this->actingAs($this->clientAUser())
            ->get('/transaccion?tipo=1&offset=10&buscar=x&criterio=idusuario', $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }
}
