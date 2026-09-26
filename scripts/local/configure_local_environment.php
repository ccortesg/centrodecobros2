<?php

declare(strict_types=1);

/**
 * Configures ignored local environment files without storing credentials in Git.
 *
 * Required process variables:
 * - CDC_LOCAL_DB_PASSWORD
 * - CDC_TEST_DB_PASSWORD
 */

$projectRoot = dirname(__DIR__, 2);

if (!is_file($projectRoot.'/artisan')) {
    fwrite(STDERR, "Run this helper from the Centro de Cobros checkout.\n");
    exit(1);
}

$localPassword = getenv('CDC_LOCAL_DB_PASSWORD');
$testPassword = getenv('CDC_TEST_DB_PASSWORD');

if (!is_string($localPassword) || $localPassword === '' || !is_string($testPassword) || $testPassword === '') {
    fwrite(STDERR, "Both local database passwords must be supplied through process variables.\n");
    exit(1);
}

$localPath = $projectRoot.'/.env';
$testingPath = $projectRoot.'/.env.testing';

if (!is_file($localPath)) {
    fwrite(STDERR, "The existing .env file is required so unrelated local settings are preserved.\n");
    exit(1);
}

$localValues = [
    'APP_ENV' => 'local',
    'APP_DEBUG' => 'true',
    'APP_URL' => 'http://centrodecobros.local',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'centrodecobros',
    'DB_USERNAME' => 'centrodecobros_user',
    'DB_PASSWORD' => $localPassword,
    'CACHE_DRIVER' => 'file',
    'SESSION_DRIVER' => 'file',
    'QUEUE_DRIVER' => 'sync',
    'QUEUE_CONNECTION' => 'sync',
    'WEBHOOK_NOTIFICATIONS_ENABLED' => 'false',
    'PAGADETODO_MOCK' => 'true',
];

$testingTemplate = <<<'ENV'
APP_NAME=CentroDeCobros
APP_ENV=testing
APP_KEY=
APP_DEBUG=false
APP_URL=http://centrodecobros.local

LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=centrodecobros_testing
DB_USERNAME=centrodecobros_testing_user
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=array
SESSION_DRIVER=array
QUEUE_DRIVER=sync
QUEUE_CONNECTION=sync
WEBHOOK_NOTIFICATIONS_ENABLED=false
PAGADETODO_MOCK=true

MAIL_MAILER=array
ENV;

$testingSource = is_file($testingPath) ? (string) file_get_contents($testingPath) : $testingTemplate."\n";
$testingKey = dotenvValue($testingSource, 'APP_KEY');

if ($testingKey === null || $testingKey === '') {
    $testingKey = 'base64:'.base64_encode(random_bytes(32));
}

$testingValues = [
    'APP_ENV' => 'testing',
    'APP_KEY' => $testingKey,
    'APP_DEBUG' => 'false',
    'APP_URL' => 'http://centrodecobros.local',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'centrodecobros_testing',
    'DB_USERNAME' => 'centrodecobros_testing_user',
    'DB_PASSWORD' => $testPassword,
    'CACHE_DRIVER' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_DRIVER' => 'sync',
    'QUEUE_CONNECTION' => 'sync',
    'WEBHOOK_NOTIFICATIONS_ENABLED' => 'false',
    'PAGADETODO_MOCK' => 'true',
];

writeEnvironmentFile($localPath, (string) file_get_contents($localPath), $localValues, 0640);
writeEnvironmentFile($testingPath, $testingSource, $testingValues, 0600);

fwrite(STDOUT, "Local and testing environments configured without displaying credentials.\n");

function dotenvValue(string $contents, string $key): ?string
{
    if (!preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
        return null;
    }

    $value = trim($matches[1]);

    if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
        $value = substr($value, 1, -1);
        $value = str_replace(['\\"', '\\$', '\\\\'], ['"', '$', '\\'], $value);
    }

    return $value;
}

function writeEnvironmentFile(string $path, string $contents, array $values, int $mode): void
{
    $lines = preg_split('/\R/', rtrim($contents, "\r\n")) ?: [];
    $seen = [];

    foreach ($lines as &$line) {
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=/', $line, $matches)) {
            continue;
        }

        $key = $matches[1];

        if (!array_key_exists($key, $values)) {
            continue;
        }

        $line = $key.'='.quoteDotenv((string) $values[$key]);
        $seen[$key] = true;
    }
    unset($line);

    foreach ($values as $key => $value) {
        if (!isset($seen[$key])) {
            $lines[] = $key.'='.quoteDotenv((string) $value);
        }
    }

    $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(6));
    $result = file_put_contents($temporaryPath, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX);

    if ($result === false) {
        throw new RuntimeException('Could not write the environment file.');
    }

    chmod($temporaryPath, $mode);

    if (!rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Could not atomically replace the environment file.');
    }

    chmod($path, $mode);
}

function quoteDotenv(string $value): string
{
    if (in_array($value, ['true', 'false', 'null'], true) || preg_match('#^[A-Za-z0-9_:/\.@-]+$#', $value)) {
        return $value;
    }

    return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
}
