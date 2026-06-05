<?php

namespace App\Http\Controllers;

use App\PagoRecibido;
use App\PagoSpei;
use App\Respuesta;
use App\Transaccion;
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
            'monto_centavos',
            'canal',
        ];
    }

    private function statusPermitido($status)
    {
        return in_array((string) $status, ['activo', 'cancelado', '99'], true);
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
                DB::raw('COALESCE(respuestas.amount, transacciones.Amount, 0) as monto_centavos'),
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
                DB::raw('COALESCE(pagospei.monto, transacciones.Amount, 0) as monto_centavos'),
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

    private function buildPagosRecibidosQuery()
    {
        $fuentes = $this->buildRespuestasAprobadasQuery()
            ->unionAll($this->buildSpeiExitososQuery());

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
                'fuente.monto_centavos',
                'fuente.canal',
                'fuente.idusuario',
                'fuente.productivo',
                DB::raw("COALESCE(ajuste.status, 'activo') as status"),
            ]);
    }

    public function index(Request $request)
    {
        if (!$request->ajax()) return redirect('/');

        $buscar = $request->buscar ?? '';
        $criterio = $request->criterio ?? 'cliente';
        $offset = $this->offsetPaginacion($request->offset ?? 10);
        $status = $request->status ?? '99';

        if (!$this->statusPermitido($status)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Status no permitido.',
            ], 422);
        }

        $query = $this->buildPagosRecibidosQuery();

        if ($buscar !== '') {
            if (!$this->criterioPermitido($criterio, $this->criteriosPermitidos())) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Criterio de búsqueda no permitido.',
                ], 422);
            }

            $query->where('fuente.' . $criterio, 'like', '%' . $buscar . '%');
        }

        if ((string) $status !== '99') {
            $query->whereRaw("COALESCE(ajuste.status, 'activo') = ?", [$status]);
        }

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
