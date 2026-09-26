<?php

namespace Tests\Feature\Support;

use RuntimeException;
use Tests\Support\UsesIsolatedCentroCobrosDatabase;
use Tests\TestCase;

class IsolatedDatabaseSafetyFeatureTest extends TestCase
{
    use UsesIsolatedCentroCobrosDatabase;

    public function test_destructive_setup_refuses_the_operational_database_name(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'centrodecobros',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing destructive test setup');

        $this->setUpIsolatedDatabase();
    }
}
