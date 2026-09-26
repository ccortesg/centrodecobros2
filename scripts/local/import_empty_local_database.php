<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$projectRoot = dirname(__DIR__, 2);
$dumpPath = $projectRoot.'/database/centrodecobros.sql';

require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connectionName = (string) config('database.default');
$connection = (array) config("database.connections.{$connectionName}");
$expectedDatabase = 'centrodecobros';

if (!app()->environment('local')
    || ($connection['driver'] ?? null) !== 'mysql'
    || !hash_equals($expectedDatabase, (string) ($connection['database'] ?? ''))) {
    fwrite(STDERR, "Refusing import outside the authorized local MySQL database.\n");
    exit(1);
}

$selectedDatabase = DB::selectOne('SELECT DATABASE() AS database_name')->database_name ?? '';

if (!is_string($selectedDatabase) || !hash_equals($expectedDatabase, $selectedDatabase)) {
    fwrite(STDERR, "MySQL did not select the authorized local database.\n");
    exit(1);
}

$existingTables = (int) DB::table('information_schema.tables')
    ->where('table_schema', $expectedDatabase)
    ->count();

if ($existingTables !== 0) {
    fwrite(STDERR, "Refusing to import into a non-empty local database.\n");
    exit(1);
}

if (!is_file($dumpPath) || !is_readable($dumpPath)) {
    fwrite(STDERR, "The ignored local SQL dump is unavailable.\n");
    exit(1);
}

$dump = (string) file_get_contents($dumpPath);

if (preg_match('/^(CREATE DATABASE|USE[[:space:]])/mi', $dump)
    || preg_match('/DEFINER[[:space:]]*=/i', $dump)) {
    fwrite(STDERR, "The local SQL dump contains an unsafe database switch or definer.\n");
    exit(1);
}

$expectedTables = preg_match_all('/^CREATE TABLE[[:space:]]/mi', $dump);
unset($dump);

if ($expectedTables < 1) {
    fwrite(STDERR, "The local SQL dump does not contain a schema.\n");
    exit(1);
}

$command = [
    'mysql',
    '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
    '--port='.(string) ($connection['port'] ?? '3306'),
    '--user='.(string) ($connection['username'] ?? ''),
    '--database='.$expectedDatabase,
    '--default-character-set=utf8mb4',
    '--binary-mode',
];

$descriptors = [
    0 => ['file', $dumpPath, 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$environment = getenv();
$environment['MYSQL_PWD'] = (string) ($connection['password'] ?? '');
$process = proc_open($command, $descriptors, $pipes, $projectRoot, $environment);

if (!is_resource($process)) {
    fwrite(STDERR, "Could not start the local MySQL client.\n");
    exit(1);
}

stream_get_contents($pipes[1]);
$errorOutput = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($exitCode !== 0) {
    fwrite(STDERR, "The local SQL import failed; inspect MySQL locally before retrying.\n");
    exit($exitCode);
}

DB::purge($connectionName);
$importedTables = (int) DB::connection($connectionName)
    ->table('information_schema.tables')
    ->where('table_schema', $expectedDatabase)
    ->count();

if ($importedTables < $expectedTables) {
    fwrite(STDERR, "The local SQL import did not create the expected schema.\n");
    exit(1);
}

fwrite(STDOUT, "Local database imported successfully ({$importedTables} tables).\n");
