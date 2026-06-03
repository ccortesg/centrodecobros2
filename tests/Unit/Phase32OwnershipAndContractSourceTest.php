<?php

namespace Tests\Unit;

use Tests\TestCase;

class Phase32OwnershipAndContractSourceTest extends TestCase
{
    public function test_shared_controller_helpers_cover_roles_ownership_and_pagadetodo_mock()
    {
        $source = file_get_contents(app_path('Http/Controllers/Controller.php'));

        foreach ([
            'usuarioEsAdministrador',
            'aplicarScopePropietario',
            'usuarioPuedeOperarRegistro',
            'respuestaNoAutorizado',
            'criterioPermitido',
            'offsetPaginacion',
            'postJsonControlado',
            "config('services.pagadetodo.mock'",
            'GenerarClabeIndi',
            'GenerarPagoLectorIndi',
            'PagarDomiciliacionIndi',
            'CancelarDomiciliacionIndi',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }

    public function test_client_owned_resources_have_controller_level_guards()
    {
        $expectations = [
            'ClienteController.php' => [
                'validarAdministrador',
                'clientes.idusuario',
                'usuarioPuedeOperarRegistro',
            ],
            'ArchivoController.php' => [
                'puedeAccederPersona',
                'idusuario',
                'respuestaNoAutorizado',
            ],
            'TransaccionController.php' => [
                'clienteAutorizado',
                'criteriosTransaccionPermitidos',
                "aplicarScopePropietario(\$query, 'transacciones')",
                'usuarioPuedeOperarRegistro',
                'postJsonControlado',
            ],
            'RespuestaController.php' => [
                'criteriosRespuestaPermitidos',
                'respuestaPerteneceUsuario',
                "aplicarScopePropietario(\$query, 'transacciones')",
                'usuarioPuedeOperarRegistro',
            ],
            'TransaccionDomController.php' => [
                'criteriosTransaccionDomPermitidos',
                "aplicarScopePropietario(\$query, 'transaccionesDom')",
                'usuarioPuedeOperarRegistro',
                'postJsonControlado',
            ],
        ];

        foreach ($expectations as $file => $needles) {
            $source = file_get_contents(app_path('Http/Controllers/' . $file));

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $source, $file);
            }
        }
    }

    public function test_spei_controllers_whitelist_client_reference_and_scope_by_owner()
    {
        foreach (['ConsultaSpeiController.php', 'PagoSpeiController.php', 'CancelaSpeiController.php'] as $file) {
            $source = file_get_contents(app_path('Http/Controllers/' . $file));

            $this->assertStringContainsString('criteriosPermitidos', $source, $file);
            $this->assertStringContainsString("aplicarScopePropietario(\$query, 'transacciones')", $source, $file);
            $this->assertStringContainsString('usuarioPuedeOperarRegistro', $source, $file);
            $this->assertStringContainsString('Criterio de búsqueda no permitido.', $source, $file);
        }

        $this->assertStringContainsString('ClientReference', file_get_contents(app_path('Http/Controllers/ConsultaSpeiController.php')));
        $this->assertStringContainsString('ClientReference', file_get_contents(app_path('Http/Controllers/PagoSpeiController.php')));
    }

    public function test_exports_apply_owner_scope_for_client_role()
    {
        foreach ([
            'ClienteExport.php',
            'RespuestaExport.php',
            'ConsultaSpeiExport.php',
            'PagoSpeiExport.php',
            'CancelaSpeiExport.php',
            'TransaccionDomExport.php',
        ] as $file) {
            $source = file_get_contents(app_path('Exports/' . $file));

            $this->assertStringContainsString('Auth::user()->idrol', $source, $file);
            $this->assertStringContainsString('idusuario', $source, $file);
        }
    }
}
