<?php

namespace App\Http\Controllers;

use App\Exports\IntegrationAuditExport;
use App\IncomingApiRequest;
use App\OutgoingApiRequest;
use App\Services\UserActivityLogger;
use App\UserActivityLog;
use Carbon\Carbon;
use Excel;
use Illuminate\Http\Request;

class IntegrationAuditController extends Controller
{
    public function outgoing(Request $request)
    {
        return $this->listAudit($request, 'outgoing');
    }

    public function incoming(Request $request)
    {
        return $this->listAudit($request, 'incoming');
    }

    public function userActivity(Request $request)
    {
        return $this->listAudit($request, 'user_activity');
    }

    public function exportOutgoing(Request $request)
    {
        return $this->exportAudit($request, 'outgoing');
    }

    public function exportIncoming(Request $request)
    {
        return $this->exportAudit($request, 'incoming');
    }

    public function exportUserActivity(Request $request)
    {
        return $this->exportAudit($request, 'user_activity');
    }

    public function storeModuleActivity(Request $request)
    {
        $menu = $request->input('menu');

        if (!is_numeric($menu)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Modulo no permitido.',
            ], 422);
        }

        app(UserActivityLogger::class)->log($request, 'module_access', true, null, [
            'module_key' => (int) $menu,
        ]);

        return response()->json(['status' => 'success']);
    }

    private function listAudit(Request $request, string $type)
    {
        if (!$request->ajax()) {
            return redirect('/');
        }

        $filters = $this->filters($request);

        if ($validation = $this->validarRangoFechasListado($filters['fechaInicio'], $filters['fechaFin'])) {
            return $validation;
        }

        $query = $this->queryForType($type);
        $this->applyFilters($query, $type, $filters);

        $items = $query->orderBy($this->tableForType($type) . '.id', 'desc')
            ->paginate($filters['offset']);

        return [
            'registros' => $items,
            'pagination' => [
                'total' => $items->total(),
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ];
    }

    private function exportAudit(Request $request, string $type)
    {
        if (!$request->ajax()) {
            return redirect('/');
        }

        $filters = $this->filters($request);

        if ($validation = $this->validarRangoFechasListado($filters['fechaInicio'], $filters['fechaFin'])) {
            return $validation;
        }

        $query = $this->queryForType($type);
        $this->applyFilters($query, $type, $filters);

        $rows = $query->orderBy($this->tableForType($type) . '.id', 'desc')
            ->get()
            ->map(function ($row) use ($type) {
                return $this->mapExportRow($row, $type);
            });

        return Excel::download(
            new IntegrationAuditExport($rows, $this->headingsForType($type)),
            $this->filenameForType($type)
        );
    }

    private function filters(Request $request): array
    {
        [$defaultStart, $defaultEnd] = $this->defaultDateRange();

        return [
            'buscar' => trim((string) ($request->buscar ?? '')),
            'fechaInicio' => $request->fechaInicio ?? $defaultStart,
            'fechaFin' => $request->fechaFin ?? $defaultEnd,
            'offset' => $this->offsetPaginacion($request->offset ?? 50),
        ];
    }

    private function defaultDateRange(): array
    {
        $today = Carbon::now('America/Hermosillo');

        return [
            $today->copy()->startOfMonth()->toDateString(),
            $today->toDateString(),
        ];
    }

    private function queryForType(string $type)
    {
        if ($type === 'outgoing') {
            return OutgoingApiRequest::leftJoin('users', 'users.id', '=', 'outgoing_api_requests.idusuario')
                ->select('outgoing_api_requests.*', 'users.usuario as usuario_nombre');
        }

        if ($type === 'incoming') {
            return IncomingApiRequest::leftJoin('users', 'users.id', '=', 'incoming_api_requests.idusuario')
                ->select('incoming_api_requests.*', 'users.usuario as usuario_nombre');
        }

        return UserActivityLog::select('user_activity_logs.*');
    }

    private function applyFilters($query, string $type, array $filters): void
    {
        $table = $this->tableForType($type);
        $this->aplicarRangoFechasListado($query, $table . '.occurred_at', $filters['fechaInicio'], $filters['fechaFin']);

        if ($filters['buscar'] === '') {
            return;
        }

        $term = '%' . $filters['buscar'] . '%';

        $query->where(function ($query) use ($type, $table, $term) {
            if ($type === 'outgoing') {
                $query->where($table . '.provider', 'like', $term)
                    ->orWhere($table . '.source_context', 'like', $term)
                    ->orWhere($table . '.url', 'like', $term)
                    ->orWhere($table . '.host', 'like', $term)
                    ->orWhere($table . '.correlation_reference', 'like', $term)
                    ->orWhere($table . '.error_message', 'like', $term)
                    ->orWhere('users.usuario', 'like', $term);
                return;
            }

            if ($type === 'incoming') {
                $query->where($table . '.path', 'like', $term)
                    ->orWhere($table . '.route_action', 'like', $term)
                    ->orWhere($table . '.ip_address', 'like', $term)
                    ->orWhere($table . '.correlation_reference', 'like', $term)
                    ->orWhere($table . '.error_message', 'like', $term)
                    ->orWhere('users.usuario', 'like', $term);
                return;
            }

            $query->where($table . '.usuario', 'like', $term)
                ->orWhere($table . '.action', 'like', $term)
                ->orWhere($table . '.module_name', 'like', $term)
                ->orWhere($table . '.route_path', 'like', $term)
                ->orWhere($table . '.ip_address', 'like', $term);
        });
    }

    private function tableForType(string $type): string
    {
        if ($type === 'outgoing') {
            return 'outgoing_api_requests';
        }

        if ($type === 'incoming') {
            return 'incoming_api_requests';
        }

        return 'user_activity_logs';
    }

    private function headingsForType(string $type): array
    {
        if ($type === 'outgoing') {
            return ['Fecha', 'Proveedor', 'Contexto', 'Metodo', 'URL', 'Status', 'Exitoso', 'Duracion ms', 'Usuario', 'Referencia', 'Error'];
        }

        if ($type === 'incoming') {
            return ['Fecha', 'Metodo', 'Ruta', 'Accion', 'Status', 'Exitoso', 'Duracion ms', 'IP', 'Usuario', 'Referencia', 'Error'];
        }

        return ['Fecha', 'Usuario', 'Rol', 'Accion', 'Exitoso', 'Modulo', 'Ruta', 'IP'];
    }

    private function mapExportRow($row, string $type): array
    {
        if ($type === 'outgoing') {
            return [
                (string) $row->occurred_at,
                $row->provider,
                $row->source_context,
                $row->method,
                $row->url,
                $row->status_code,
                $row->success ? 'Si' : 'No',
                $row->duration_ms,
                $row->usuario_nombre,
                $row->correlation_reference,
                $row->error_message,
            ];
        }

        if ($type === 'incoming') {
            return [
                (string) $row->occurred_at,
                $row->method,
                $row->path,
                $row->route_action,
                $row->status_code,
                $row->success ? 'Si' : 'No',
                $row->duration_ms,
                $row->ip_address,
                $row->usuario_nombre,
                $row->correlation_reference,
                $row->error_message,
            ];
        }

        return [
            (string) $row->occurred_at,
            $row->usuario,
            $row->idrol,
            $row->action,
            $row->success ? 'Si' : 'No',
            $row->module_name,
            $row->route_path,
            $row->ip_address,
        ];
    }

    private function filenameForType(string $type): string
    {
        if ($type === 'outgoing') {
            return 'outgoing_api_requests.xlsx';
        }

        if ($type === 'incoming') {
            return 'incoming_api_requests.xlsx';
        }

        return 'user_activity_log.xlsx';
    }
}
