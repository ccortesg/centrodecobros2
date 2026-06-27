<?php

namespace Tests\Unit;

use App\Http\Middleware\Administrador;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdministradorMiddlewareTest extends TestCase
{
    private function middlewareResponse(string $method, string $path, int $idrol): Response
    {
        $request = Request::create($path, $method, [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $request->setUserResolver(function () use ($idrol) {
            return (object) ['idrol' => $idrol];
        });

        return (new Administrador())->handle($request, function () {
            return new Response('ok', 200);
        });
    }

    public function test_admin_role_can_access_administrative_routes()
    {
        $response = $this->middlewareResponse('GET', '/user', 1);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_client_role_can_access_client_operational_routes()
    {
        $response = $this->middlewareResponse('GET', '/cliente', 2);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_client_role_can_read_catalog_selectors_needed_by_customer_modal()
    {
        $estado = $this->middlewareResponse('GET', '/estado/selectEstado', 2);
        $ciudad = $this->middlewareResponse('GET', '/ciudad/selectCiudad', 2);

        $this->assertSame(200, $estado->getStatusCode());
        $this->assertSame(200, $ciudad->getStatusCode());
    }

    public function test_client_role_cannot_access_user_administration()
    {
        $response = $this->middlewareResponse('GET', '/user', 2);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('error', $response->getData(true)['status']);
    }

    public function test_client_role_cannot_generate_hidden_spei_routes()
    {
        $response = $this->middlewareResponse('POST', '/transaccion/registrarSpei', 2);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_unknown_role_is_denied()
    {
        $response = $this->middlewareResponse('GET', '/cliente', 3);

        $this->assertSame(403, $response->getStatusCode());
    }
}
