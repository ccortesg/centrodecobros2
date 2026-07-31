<?php

namespace App\Http\Middleware;

use Closure;

class Administrador
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $this->deny($request, 401, 'No autenticado.');
        }

        if ((int) $user->idrol === 1) {
            return $next($request);
        }

        if ((int) $user->idrol === 2 && $this->clientePuedeAcceder($request)) {
            return $next($request);
        }

        return $this->deny($request, 403, 'No tienes permisos para realizar esta acción.');
    }

    private function clientePuedeAcceder($request)
    {
        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());

        $allowed = [
            'GET' => [
                'estado/selectEstado',
                'ciudad/selectCiudad',
                'cliente',
                'cliente/selectCliente',
                'cliente/exportar',
                'archivo',
                'archivo/descargar',
                'transaccion',
                'transaccion/reporteTransacciones',
                'transaccion/selectDomiciliacion',
                'domiciliacion-activa',
                'transaccion/exportar',
                'transaccion/exportarTransacciones',
                'transaccion/importar/estatus',
                'transaccion/importar/log',
                'respuesta',
                'respuesta/exportar',
                'transaccionDom',
                'transaccionDom/reporteTransaccionesDom',
                'transaccionDom/exportar',
                'transaccionDom/exportarTransacciones',
                'pagos-recibidos',
                'pagos-recibidos/exportar',
            ],
            'POST' => [
                'cliente/registrar',
                'archivo/registrar',
                'transaccion/registrar',
                'transaccion/registrarDom',
                'transaccion/importar/iniciar',
                'transaccion/importar/procesar',
                'transaccion/importar/cancelar',
                'transaccionDom/registrar',
            ],
            'PUT' => [
                'cliente/actualizar',
                'archivo/eliminar',
                'transaccion/actualizar',
                'transaccion/rechazar',
                'transaccion/proximo-cargo',
                'pagos-recibidos/status',
            ],
        ];

        return in_array($path, $allowed[$method] ?? [], true);
    }

    private function deny($request, $status, $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'error',
                'msg' => $message,
            ], $status);
        }

        if ($status === 401) {
            return redirect('/login');
        }

        abort($status, $message);
    }
}
