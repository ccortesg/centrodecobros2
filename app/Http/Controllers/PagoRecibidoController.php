<?php

namespace App\Http\Controllers;

use App\PagoRecibido;
use App\PagoSpei;
use App\Respuesta;
use App\Transaccion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagoRecibidoController extends Controller
{
    private function criteriosPermitidos()
    {
        return [
            'folio',
            'fecha',
            'cliente',
            'referencia',
            'monto',
            'canal',
        ];
    }

    private function statusPermitido($status)
    {
        return in_array((string) $status, ['activo', 'cancelado', '99'], true);
    }

    private function fechaFiltroValida($fecha)
    {
        if ($fecha === null || $fecha === '') {
            return true;
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $fecha);
        } catch (\Exception $e) {
            return false;
        }

        return $parsed && $parsed->format('Y-m-d') === $fecha;
    }

    private function buildRespuestasAprobadasQuery()
    {
        $query = DB::table('respuestas')
            ->join('transacciones', 'transacciones.id', '=', 'respuestas.idtransaccion')
            ->leftJoin('clientes', 'clientes.id', '=', 'transacciones.idcliente')
            ->select([
                DB::raw("'respuesta' as source_type"),
                'respuestas.id as source_id',
                'transacciones.folio as folio',
                'respuestas.fecha as fecha',
                DB::raw("COALESCE(clientes.razon_social, transacciones.ClientReference, '') as cliente"),
                DB::raw("COALESCE(transacciones.ClientReference, transacciones.Reference, '') as referencia"),
                DB::raw('COALESCE(respuestas.amount, transacciones.Amount / 100.0, 0) as monto'),
                DB::raw("CASE transacciones.tipo WHEN 1 THEN 'Liga de pago' WHEN 2 THEN 'Domiciliacion' WHEN 3 THEN 'Caja' WHEN 4 THEN 'Terminal' ELSE 'Otro' END as canal"),
                'transacciones.idusuario as idusuario',
                'transacciones.productivo as productivo',
            ])
            ->where('respuestas.status', '=', 'approved');

        $this->aplicarScopePropietario($query, 'transacciones');

        return $query;
    }

    private function buildSpeiExitososQuery()
    {
        $query = DB::table('pagospei')
            ->join('transacciones', 'transacciones.id', '=', 'pagospei.idtransaccion')
            ->leftJoin('clientes', 'clientes.id', '=', 'transacciones.idcliente')
            ->select([
                DB::raw("'pagospei' as source_type"),
                'pagospei.id as source_id',
                'transacciones.folio as folio',
                'pagospei.fecha as fecha',
                DB::raw("COALESCE(clientes.razon_social, transacciones.ClientReference, '') as cliente"),
                DB::raw("COALESCE(pagospei.clabe, transacciones.Clabe, transacciones.ClientReference, '') as referencia"),
                DB::raw('COALESCE(pagospei.monto, transacciones.Amount, 0) / 100.0 as monto'),
                DB::raw("'SPEI' as canal"),
                'transacciones.idusuario as idusuario',
                'transacciones.productivo as productivo',
            ])
            ->where('pagospei.condicion', '=', 1)
            ->where(function ($query) {
                $query->where('pagospei.mensaje', 'like', 'Operaci%exitosa')
                    ->orWhereIn('pagospei.codigo', ['0', '00']);
            });

        $this->aplicarScopePropietario($query, 'transacciones');

        return $query;
    }

    private function buildCargosRecurrentesAprobadosQuery()
    {
        $query = DB::table('transaccionesDom')
            ->join('transacciones', 'transacciones.id', '=', 'transaccionesDom.idtransaccion')
            ->leftJoin('clientes', 'clientes.id', '=', 'transaccionesDom.idcliente')
            ->select([
                DB::raw("'transaccionDom' as source_type"),
                'transaccionesDom.id as source_id',
                'transaccionesDom.folio as folio',
                'transaccionesDom.fecha as fecha',
                DB::raw("COALESCE(clientes.razon_social, transacciones.ClientReference, '') as cliente"),
                DB::raw("COALESCE(transaccionesDom.response_reference, transaccionesDom.Reference, transacciones.Reference, '') as referencia"),
                DB::raw('COALESCE(transaccionesDom.Amount, transacciones.Amount, 0) / 100.0 as monto'),
                DB::raw("'Cargo Recurrente' as canal"),
                'transaccionesDom.idusuario as idusuario',
                'transaccionesDom.productivo as productivo',
            ])
            ->where('transaccionesDom.status', '=', 'approved');

        $this->aplicarScopePropietario($query, 'transaccionesDom');

        return $query;
    }

    private function buildPagosRecibidosQuery()
    {
        $fuentes = $this->buildRespuestasAprobadasQuery()
            ->unionAll($this->buildSpeiExitososQuery())
            ->unionAll($this->buildCargosRecurrentesAprobadosQuery());

        return DB::query()
            ->fromSub($fuentes, 'fuente')
            ->leftJoin('pagos_recibidos as ajuste', function ($join) {
                $join->on('ajuste.source_type', '=', 'fuente.source_type')
                    ->on('ajuste.source_id', '=', 'fuente.source_id');
            })
            ->select([
                'fuente.source_type',
                'fuente.source_id',
                'fuente.folio',
                'fuente.fecha',
                'fuente.cliente',
                'fuente.referencia',
                'fuente.monto',
                'fuente.canal',
                'fuente.idusuario',
                'fuente.productivo',
                DB::raw("COALESCE(ajuste.status, 'activo') as status"),
            ]);
    }

    private function validarFiltrosPagosRecibidos($buscar, $criterio, $status, $fechaInicio, $fechaFin)
    {
        if (!$this->statusPermitido($status)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Status no permitido.',
            ], 422);
        }

        if (!$this->fechaFiltroValida($fechaInicio) || !$this->fechaFiltroValida($fechaFin)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Rango de fechas no permitido.',
            ], 422);
        }

        if ($buscar !== '' && !$this->criterioPermitido($criterio, $this->criteriosPermitidos())) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Criterio de búsqueda no permitido.',
            ], 422);
        }

        if ($fechaInicio !== '' && $fechaFin !== '') {
            $inicio = Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay();
            $fin = Carbon::createFromFormat('Y-m-d', $fechaFin)->endOfDay();

            if ($inicio->gt($fin)) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Rango de fechas no permitido.',
                ], 422);
            }
        }

        return null;
    }

    private function aplicarFiltrosPagosRecibidos($query, $buscar, $criterio, $status, $fechaInicio, $fechaFin)
    {
        if ($buscar !== '') {
            $query->where('fuente.' . $criterio, 'like', '%' . $buscar . '%');
        }

        if ((string) $status !== '99') {
            $query->whereRaw("COALESCE(ajuste.status, 'activo') = ?", [$status]);
        }

        if ($fechaInicio !== '' && $fechaFin !== '') {
            $inicio = Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay();
            $fin = Carbon::createFromFormat('Y-m-d', $fechaFin)->endOfDay();
            $query->whereBetween('fuente.fecha', [$inicio, $fin]);
        } elseif ($fechaInicio !== '') {
            $query->where('fuente.fecha', '>=', Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay());
        } elseif ($fechaFin !== '') {
            $query->where('fuente.fecha', '<=', Carbon::createFromFormat('Y-m-d', $fechaFin)->endOfDay());
        }

        return $query;
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $buscar = $request->buscar ?? '';
        $criterio = $request->criterio ?? 'cliente';
        $offset = $this->offsetPaginacion($request->offset ?? 50);
        $status = $request->status ?? '99';
        $fechaInicio = $request->fechaInicio ?? '';
        $fechaFin = $request->fechaFin ?? '';

        if ($validacion = $this->validarFiltrosPagosRecibidos($buscar, $criterio, $status, $fechaInicio, $fechaFin)) {
            return $validacion;
        }

        $query = $this->aplicarFiltrosPagosRecibidos(
            $this->buildPagosRecibidosQuery(),
            $buscar,
            $criterio,
            $status,
            $fechaInicio,
            $fechaFin
        );

        $pagos = $query
            ->orderBy('fuente.fecha', 'desc')
            ->orderBy('fuente.source_id', 'desc')
            ->paginate($offset);

        return [
            'pagination' => [
                'total' => $pagos->total(),
                'current_page' => $pagos->currentPage(),
                'per_page' => $pagos->perPage(),
                'last_page' => $pagos->lastPage(),
                'from' => $pagos->firstItem(),
                'to' => $pagos->lastItem(),
            ],
            'pagos' => $pagos,
        ];
    }

    public function exportar(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $buscar = $request->buscar ?? '';
        $criterio = $request->criterio ?? 'cliente';
        $status = $request->status ?? '99';
        $fechaInicio = $request->fechaInicio ?? '';
        $fechaFin = $request->fechaFin ?? '';

        if ($validacion = $this->validarFiltrosPagosRecibidos($buscar, $criterio, $status, $fechaInicio, $fechaFin)) {
            return $validacion;
        }

        $query = $this->aplicarFiltrosPagosRecibidos(
            $this->buildPagosRecibidosQuery(),
            $buscar,
            $criterio,
            $status,
            $fechaInicio,
            $fechaFin
        )
            ->orderBy('fuente.fecha', 'desc')
            ->orderBy('fuente.source_id', 'desc');

        $headings = [
            'Fuente',
            'ID Fuente',
            'Folio',
            'Fecha',
            'Cliente',
            'Referencia',
            'Monto',
            'Canal',
            'Status',
            'Productivo',
        ];

        return response()->streamDownload(function () use ($query, $headings) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new \Exception('No fue posible abrir el stream de salida para la exportacion.');
            }

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $headings);

            foreach ($query->cursor() as $pago) {
                fputcsv($handle, [
                    $pago->source_type,
                    $pago->source_id,
                    $pago->folio,
                    $pago->fecha,
                    $pago->cliente,
                    $pago->referencia,
                    $pago->monto,
                    $pago->canal,
                    $pago->status,
                    $pago->productivo,
                ]);
            }

            fclose($handle);
        }, 'pagos_recibidos.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function actualizarStatus(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $validated = $request->validate([
            'source_type' => 'required|in:respuesta,pagospei',
            'source_id' => 'required|integer|min:1',
            'status' => 'required|in:activo,cancelado',
        ]);

        if (!$this->sourceAutorizado($validated['source_type'], (int) $validated['source_id'])) {
            return $this->respuestaNoAutorizado($request);
        }

        $pago = PagoRecibido::updateOrCreate(
            [
                'source_type' => $validated['source_type'],
                'source_id' => (int) $validated['source_id'],
            ],
            [
                'status' => $validated['status'],
                'idusuario' => \Auth::id(),
            ]
        );

        return [
            'status' => 'ok',
            'pago' => $pago,
        ];
    }

    private function sourceAutorizado($sourceType, $sourceId)
    {
        if ($sourceType === 'respuesta') {
            $respuesta = Respuesta::where('status', '=', 'approved')->find($sourceId);
            if (!$respuesta) {
                return false;
            }

            return $this->usuarioPuedeOperarRegistro(Transaccion::find($respuesta->idtransaccion));
        }

        $pago = PagoSpei::where('condicion', '=', 1)
            ->where(function ($query) {
                $query->where('mensaje', 'like', 'Operaci%exitosa')
                    ->orWhereIn('codigo', ['0', '00']);
            })
            ->find($sourceId);

        if (!$pago) {
            return false;
        }

        return $this->usuarioPuedeOperarRegistro(Transaccion::find($pago->idtransaccion));
    }
}
