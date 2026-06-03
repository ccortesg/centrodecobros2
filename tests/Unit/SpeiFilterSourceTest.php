<?php

namespace Tests\Unit;

use Tests\TestCase;

class SpeiFilterSourceTest extends TestCase
{
    public function test_client_reference_filters_use_valid_query_builder_signature()
    {
        $files = [
            app_path('Http/Controllers/ConsultaSpeiController.php'),
            app_path('Http/Controllers/PagoSpeiController.php'),
        ];

        foreach ($files as $file) {
            $source = file_get_contents($file);

            $this->assertStringNotContainsString("transacciones.ClientReference like", $source, $file);
            $this->assertStringContainsString("where('transacciones.ClientReference', 'like'", $source, $file);
        }
    }
}
