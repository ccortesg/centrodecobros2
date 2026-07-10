<?php

namespace App\Console\Commands;

use App\Services\TransaccionStatusSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SincronizarStatusTransacciones extends Command
{
    protected $signature = 'transacciones:sincronizar-status';

    protected $description = 'Reconcilia estados y vencimientos de transacciones con sus respuestas.';

    public function handle(TransaccionStatusSynchronizer $synchronizer): int
    {
        $inicio = microtime(true);

        try {
            $metricas = $synchronizer->sincronizarTodo();
            $this->info(json_encode($metricas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Fallo la sincronizacion programada de status de transacciones.', [
                'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
                'error_class' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);

            $this->error('No se pudo completar la sincronizacion de status de transacciones.');

            return self::FAILURE;
        }
    }
}
