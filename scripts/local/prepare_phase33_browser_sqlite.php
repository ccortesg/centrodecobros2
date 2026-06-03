<?php

use Tests\Support\UsesIsolatedCentroCobrosDatabase;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = $argv[1] ?? storage_path('phase33_browser.sqlite');
$databaseDirectory = dirname($database);

if (!is_dir($databaseDirectory)) {
    mkdir($databaseDirectory, 0777, true);
}

if (file_exists($database)) {
    unlink($database);
}

touch($database);

$seeder = new class {
    use UsesIsolatedCentroCobrosDatabase;

    public function run(string $database): void
    {
        $this->setUpIsolatedDatabase($database);
    }
};

$seeder->run($database);

echo $database . PHP_EOL;
