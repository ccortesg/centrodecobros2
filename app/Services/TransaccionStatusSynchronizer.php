<?php

namespace App\Services;

use App\Respuesta;
use App\Transaccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransaccionStatusSynchronizer
{
    public function sincronizarTodo(): array
    {
        $inicio = microtime(true);
        $hoy = Carbon::now('America/Hermosillo')->toDateString();

        $vencidasOtrosTipos = Transaccion::whereIn('tipo', [1, 3, 4])
            ->where('condicion', 1)
            ->where('ExpirationDate', '<', $hoy)
            ->update(['condicion' => 4]);

        $vencidasDomiciliacion = Transaccion::where('tipo', 2)
            ->where('condicion', 0)
            ->where('ExpirationDate', '<', $hoy)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('respuestas')
                    ->whereRaw('respuestas.idtransaccion = transacciones.id')
                    ->where('respuestas.status', 'approved');
            })
            ->update(['condicion' => 4]);

        $domiciliacionesConToken = Transaccion::where('tipo', 2)
            ->whereIn('condicion', [0, 5])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('respuestas')
                    ->whereRaw('respuestas.idtransaccion = transacciones.id')
                    ->where('respuestas.status', 'approved')
                    ->whereNotNull('respuestas.number_tkn')
                    ->where('respuestas.number_tkn', '<>', '');
            })
            ->get();

        $activadasDomiciliacion = 0;
        foreach ($domiciliacionesConToken as $transaccion) {
            $transaccion->condicion = 1;
            if ($transaccion->ProximoCargo && !$transaccion->ProximoCargoBase) {
                $transaccion->ProximoCargoBase = $transaccion->ProximoCargo;
            }
            if ($transaccion->intentos === null) {
                $transaccion->intentos = 0;
            }
            $transaccion->save();
            $activadasDomiciliacion++;
        }

        $domiciliacionesConError = Transaccion::where('tipo', 2)
            ->whereIn('condicion', [0, 1])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('respuestas')
                    ->whereRaw('respuestas.idtransaccion = transacciones.id')
                    ->where('respuestas.status', 'approved')
                    ->where(function ($query) {
                        $query->whereNull('respuestas.number_tkn')
                            ->orWhere('respuestas.number_tkn', '');
                    });
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('respuestas')
                    ->whereRaw('respuestas.idtransaccion = transacciones.id')
                    ->where('respuestas.status', 'approved')
                    ->whereNotNull('respuestas.number_tkn')
                    ->where('respuestas.number_tkn', '<>', '');
            })
            ->update(['condicion' => 5]);

        $metricas = [
            'fecha_corte' => $hoy,
            'vencidas_otros_tipos' => $vencidasOtrosTipos,
            'vencidas_domiciliacion' => $vencidasDomiciliacion,
            'activadas_domiciliacion' => $activadasDomiciliacion,
            'domiciliaciones_con_error' => $domiciliacionesConError,
            'filas_afectadas' => $vencidasOtrosTipos
                + $vencidasDomiciliacion
                + $activadasDomiciliacion
                + $domiciliacionesConError,
            'duracion_ms' => (int) round((microtime(true) - $inicio) * 1000),
        ];

        Log::info('Sincronizacion programada de status de transacciones completada.', $metricas);

        return $metricas;
    }

    public function sincronizarPorRespuesta(?Transaccion $transaccion, Respuesta $respuesta): bool
    {
        if ($transaccion === null || (string) $respuesta->status !== 'approved') {
            return false;
        }

        if (in_array((int) $transaccion->tipo, [1, 4], true)) {
            $transaccion->condicion = 3;
        } elseif ((int) $transaccion->tipo === 2) {
            $transaccion->condicion = trim((string) $respuesta->number_tkn) === '' ? 5 : 1;

            if ($transaccion->ProximoCargo && !$transaccion->ProximoCargoBase) {
                $transaccion->ProximoCargoBase = $transaccion->ProximoCargo;
            }

            if ($transaccion->intentos === null) {
                $transaccion->intentos = 0;
            }
        } else {
            return false;
        }

        if (!$transaccion->isDirty()) {
            return false;
        }

        return $transaccion->save();
    }
}
