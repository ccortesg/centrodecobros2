<?php

namespace Tests\Feature\Smoke;

use App\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommandAndDatabaseSmokeTest extends TestCase
{
    public function test_database_connection_is_available_and_uses_the_loaded_schema()
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $tables = DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'");
        } else {
            $tables = DB::select('SHOW TABLES');
        }

        $tableNames = array_map(function ($table) {
            return array_values((array) $table)[0];
        }, $tables);

        foreach (['users', 'clientes', 'transacciones', 'respuestas', 'transaccionesDom'] as $tableName) {
            $this->assertContains($tableName, $tableNames);
        }

        $this->assertGreaterThanOrEqual(15, count($tables));
    }

    public function test_schedule_list_contains_the_expected_tasks()
    {
        $exitCode = Artisan::call('schedule:list');
        $output = Artisan::output();
        $normalizedOutput = preg_replace('/\s+/', ' ', trim($output));

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('0 7 * * *', $normalizedOutput);
        $this->assertStringContainsString(
            'App\Http\Controllers\TransaccionDomController@ejecutarCron',
            $normalizedOutput
        );
        $this->assertStringContainsString('*/5 * * * *', $normalizedOutput);
        $this->assertStringContainsString('revisar-status-pagos-spei', $normalizedOutput);
        $this->assertStringContainsString('5 0 * * *', $normalizedOutput);
        $this->assertStringContainsString('transacciones:sincronizar-status', $normalizedOutput);

        $events = app(ConsoleKernel::class)->resolveConsoleSchedule()->events();
        $speiEvent = collect($events)->first(function ($event) {
            return $event->getSummaryForDisplay() === 'revisar-status-pagos-spei';
        });
        $syncEvent = collect($events)->first(function ($event) {
            return str_contains($event->getSummaryForDisplay(), 'transacciones:sincronizar-status');
        });

        $this->assertNotNull($speiEvent);
        $this->assertNotNull($syncEvent);
        $this->assertTrue($speiEvent->withoutOverlapping);
        $this->assertTrue($syncEvent->withoutOverlapping);
    }

    public function test_route_list_command_executes()
    {
        $exitCode = Artisan::call('route:list');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
    }
}
