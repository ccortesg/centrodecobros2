<?php

namespace App\Services;

use App\User;
use App\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserActivityLogger
{
    private AuditSanitizer $sanitizer;

    public function __construct(AuditSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
    }

    public static function moduleMap(): array
    {
        return [
            0 => 'Escritorio',
            1 => 'Liga de Pago Unica',
            2 => 'Respuestas Ligas de Pago',
            3 => 'Usuarios',
            4 => 'Roles',
            5 => 'Ayuda',
            6 => 'Acerca de',
            7 => 'Estados',
            8 => 'Ciudades',
            9 => 'Clientes',
            10 => 'Consolidar Clientes',
            11 => 'Liga de Pago Domiciliacion',
            12 => 'Respuestas Domiciliacion',
            13 => 'Cargos Recurrentes',
            14 => 'Referencia SPEI',
            15 => 'Respuestas Pago en Caja',
            18 => 'Reporte Ligas de Pago',
            19 => 'Reporte Domiciliacion',
            20 => 'Reporte SPEI',
            21 => 'Reporte Pago con Terminal',
            22 => 'Consulta SPEI',
            23 => 'Pago SPEI',
            24 => 'Cancelaciones SPEI',
            25 => 'Reporte Cargos Recurrentes',
            26 => 'Liga de Pago Terminal',
            27 => 'Respuestas Terminal',
            28 => 'Depurar Clientes',
            29 => 'Domiciliacion Activa',
            30 => 'Pagos Recibidos',
            31 => 'Outgoing API Requests',
            32 => 'Incoming API Requests',
            33 => 'User Activity Log',
            34 => 'Webhook Configuration',
            35 => 'Webhook Deliveries',
        ];
    }

    public function log(Request $request, string $action, bool $success = true, ?User $user = null, array $metadata = []): void
    {
        try {
            $user = $user ?: (Auth::check() ? Auth::user() : null);
            $moduleKey = isset($metadata['module_key']) ? (int) $metadata['module_key'] : null;
            $moduleName = $moduleKey !== null ? (self::moduleMap()[$moduleKey] ?? 'Modulo desconocido') : ($metadata['module_name'] ?? null);

            UserActivityLog::create([
                'occurred_at' => Carbon::now('America/Hermosillo'),
                'idusuario' => $user->id ?? null,
                'usuario' => $user->usuario ?? ($metadata['usuario'] ?? null),
                'idrol' => $user->idrol ?? null,
                'action' => $action,
                'success' => $success,
                'module_key' => $moduleKey,
                'module_name' => $moduleName,
                'route_path' => trim($request->path(), '/'),
                'ip_address' => $request->ip(),
                'user_agent' => $this->sanitizer->sanitizeString((string) $request->userAgent()),
                'session_id_hash' => $this->sessionHash($request),
                'metadata' => $this->sanitizer->sanitizePayload($metadata),
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo guardar auditoria de actividad de usuario.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sessionHash(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $sessionId = $request->session()->getId();

        return $sessionId ? hash('sha256', $sessionId) : null;
    }
}
