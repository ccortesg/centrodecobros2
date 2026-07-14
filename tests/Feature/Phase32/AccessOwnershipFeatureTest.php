<?php

namespace Tests\Feature\Phase32;

use App\Http\Middleware\Administrador;
use Illuminate\Support\Facades\DB;
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

    public function test_clients_with_legacy_invalid_city_are_still_searchable_by_name()
    {
        DB::table('personas')->insert([
            'id' => 30,
            'nombre' => 'VILLEGAS JESUS',
            'tipo_documento' => 'CLIENTE',
            'num_documento' => '30',
            'email' => 'villegas@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('clientes')->insert([
            'id' => 30,
            'idciudad' => 0,
            'razon_social' => 'VILLEGAS JESUS',
            'rfc' => 'XAXX010101000',
            'idusuario' => 2,
        ]);

        $response = $this->actingAs($this->clientAUser())
            ->get('/cliente?offset=10&buscar=VILLEGAS&criterio=nombre', $this->ajaxHeaders())
            ->assertOk();

        $response->assertJsonFragment(['razon_social' => 'VILLEGAS JESUS']);
    }

    public function test_client_registration_rejects_empty_city()
    {
        $this->actingAs($this->clientAUser())
            ->post('/cliente/registrar', [
                'nombre' => 'Nuevo Cliente',
                'tipo_documento' => 'CLIENTE',
                'idciudad' => 0,
                'rfc' => 'NUE010101AAA',
                'razon_social' => 'Nuevo Cliente SA',
            ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJson(['status' => 'error']);

        $this->assertDatabaseMissing('clientes', ['razon_social' => 'Nuevo Cliente SA']);
    }

    public function test_client_update_rejects_empty_city()
    {
        $this->actingAs($this->clientAUser())
            ->put('/cliente/actualizar', [
                'id' => 10,
                'nombre' => 'Cliente A',
                'tipo_documento' => 'CLIENTE',
                'num_documento' => '10',
                'idciudad' => 0,
                'rfc' => 'A010101AAA',
                'razon_social' => 'Cliente A SA',
            ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJson(['status' => 'error']);

        $this->assertDatabaseHas('clientes', ['id' => 10, 'idciudad' => 1]);
    }

    public function test_client_update_preserves_immutable_document_number()
    {
        DB::table('personas')->where('id', 10)->update([
            'num_documento' => 'FOLIO-CLIENTE-10',
        ]);

        $payload = [
            'id' => 10,
            'nombre' => 'Cliente A Editado',
            'tipo_documento' => 'CLIENTE',
            'direccion' => 'Direccion actualizada',
            'idciudad' => 1,
            'telefono' => '6621234567',
            'email' => 'cliente-editado@example.com',
            'contacto' => 'Contacto Editado',
            'telefono_contacto' => '6627654321',
            'email_contacto' => 'contacto-editado@example.com',
            'rfc' => 'A010101AAA',
            'razon_social' => 'Cliente A Editado SA',
            'forma_pago' => 3,
            'plazo' => 0,
            'regimen' => '601',
            'banco' => '',
            'cuenta' => '',
            'clabe' => '',
            'cuenta_sucursal' => '',
            'cuenta_ciudad' => '',
        ];

        $this->actingAs($this->clientAUser())
            ->put('/cliente/actualizar', $payload, $this->ajaxHeaders())
            ->assertOk();

        $this->assertDatabaseHas('personas', [
            'id' => 10,
            'nombre' => 'Cliente A Editado',
            'num_documento' => 'FOLIO-CLIENTE-10',
        ]);
        $this->assertDatabaseHas('clientes', [
            'id' => 10,
            'razon_social' => 'Cliente A Editado SA',
        ]);

        $payload['num_documento'] = 'FOLIO-NO-PERMITIDO';

        $this->actingAs($this->clientAUser())
            ->put('/cliente/actualizar', $payload, $this->ajaxHeaders())
            ->assertOk();

        $this->assertSame(
            'FOLIO-CLIENTE-10',
            DB::table('personas')->where('id', 10)->value('num_documento')
        );
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
