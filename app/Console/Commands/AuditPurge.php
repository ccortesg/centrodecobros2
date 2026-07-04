<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditPurge extends Command
{
    protected $signature = 'audit:purge {--days=365 : Dias de retencion} {--dry-run : Mostrar conteo sin borrar}';

    protected $description = 'Purga manual de bitacoras de auditoria de integraciones y actividad.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days <= 0) {
            $this->error('El parametro --days debe ser mayor a cero.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now('America/Hermosillo')->subDays($days);
        $dryRun = (bool) $this->option('dry-run');
        $tables = [
            'outgoing_api_requests',
            'incoming_api_requests',
            'user_activity_logs',
        ];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->warn("Tabla no encontrada: {$table}");
                continue;
            }

            $query = DB::table($table)->where('occurred_at', '<', $cutoff);
            $count = (clone $query)->count();

            if ($dryRun) {
                $this->line("{$table}: {$count} registros se eliminarian.");
                continue;
            }

            $deleted = $query->delete();
            $this->line("{$table}: {$deleted} registros eliminados.");
        }

        return self::SUCCESS;
    }
}
