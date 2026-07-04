<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function usuarioEsAdministrador()
    {
        return \Auth::check() && (int) \Auth::user()->idrol === 1;
    }

    protected function aplicarScopePropietario($query, $table)
    {
        if ($this->usuarioEsAdministrador()) {
            return $query;
        }

        $prefix = $table ? $table . '.' : '';

        $query->where($prefix . 'idusuario', '=', \Auth::user()->id);

        if (isset(\Auth::user()->productivo)) {
            $query->where($prefix . 'productivo', '=', \Auth::user()->productivo);
        }

        return $query;
    }

    protected function usuarioPuedeOperarRegistro($registro, $idusuarioColumn = 'idusuario', $productivoColumn = 'productivo')
    {
        if ($this->usuarioEsAdministrador()) {
            return true;
        }

        if (!$registro || !\Auth::check()) {
            return false;
        }

        if ((int) $registro->{$idusuarioColumn} !== (int) \Auth::user()->id) {
            return false;
        }

        if ($productivoColumn && isset($registro->{$productivoColumn}) && isset(\Auth::user()->productivo)) {
            return (string) $registro->{$productivoColumn} === (string) \Auth::user()->productivo;
        }

        return true;
    }

    protected function respuestaNoAutorizado($request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'error',
                'msg' => 'No tienes permisos para realizar esta acción.',
            ], 403);
        }

        abort(403, 'No autorizado.');
    }

    protected function criterioPermitido($criterio, array $permitidos)
    {
        return in_array($criterio, $permitidos, true);
    }

    protected function offsetPaginacion($offset, $default = 50, $max = 100)
    {
        $offset = (int) $offset;
        if ($offset <= 0) {
            return $default;
        }

        return min($offset, $max);
    }

    protected function fechaListadoValida($fecha)
    {
        if ($fecha === null || $fecha === '') {
            return true;
        }

        try {
            $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha);
        } catch (\Exception $e) {
            return false;
        }

        return $parsed && $parsed->format('Y-m-d') === $fecha;
    }

    protected function validarRangoFechasListado($fechaInicio, $fechaFin)
    {
        if (!$this->fechaListadoValida($fechaInicio) || !$this->fechaListadoValida($fechaFin)) {
            return response()->json([
                'status' => 'error',
                'msg' => 'Rango de fechas no permitido.',
            ], 422);
        }

        if ($fechaInicio !== '' && $fechaFin !== '') {
            $inicio = \Carbon\Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay();
            $fin = \Carbon\Carbon::createFromFormat('Y-m-d', $fechaFin)->endOfDay();

            if ($inicio->gt($fin)) {
                return response()->json([
                    'status' => 'error',
                    'msg' => 'Rango de fechas no permitido.',
                ], 422);
            }
        }

        return null;
    }

    protected function aplicarRangoFechasListado($query, $column, $fechaInicio, $fechaFin)
    {
        if ($fechaInicio !== '' && $fechaFin !== '') {
            return $query->whereBetween($column, [
                \Carbon\Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay(),
                \Carbon\Carbon::createFromFormat('Y-m-d', $fechaFin)->endOfDay(),
            ]);
        }

        if ($fechaInicio !== '') {
            return $query->where($column, '>=', \Carbon\Carbon::createFromFormat('Y-m-d', $fechaInicio)->startOfDay());
        }

        if ($fechaFin !== '') {
            return $query->where($column, '<=', \Carbon\Carbon::createFromFormat('Y-m-d', $fechaFin)->endOfDay());
        }

        return $query;
    }

    protected function postJsonControlado($url, array $params, array $context = [])
    {
        $context = array_merge([
            'provider' => 'Pagadetodo',
            'source_context' => 'pagadetodo',
            'method' => 'POST',
        ], $context);

        if (config('services.pagadetodo.mock', false)) {
            $startedAt = microtime(true);
            $response = new \GuzzleHttp\Psr7\Response(200, [], json_encode($this->pagadetodoMockPayload($url, $params)));
            app(\App\Services\ApiAuditLogger::class)->recordOutgoing($url, $params, $response, null, $startedAt, $context);

            return $response;
        }

        return $this->postJsonAuditado($url, $params, $context);
    }

    protected function postJsonAuditado($url, array $params, array $context = [])
    {
        $startedAt = microtime(true);
        $client = new \GuzzleHttp\Client();
        $response = null;

        try {
            $response = $client->request('POST', $url, [\GuzzleHttp\RequestOptions::JSON => $params]);
            app(\App\Services\ApiAuditLogger::class)->recordOutgoing($url, $params, $response, null, $startedAt, array_merge([
                'method' => 'POST',
            ], $context));

            return $response;
        } catch (\Throwable $e) {
            if ($e instanceof \GuzzleHttp\Exception\RequestException) {
                $response = $e->getResponse();
            }

            app(\App\Services\ApiAuditLogger::class)->recordOutgoing($url, $params, $response, $e, $startedAt, array_merge([
                'method' => 'POST',
            ], $context));

            throw $e;
        }
    }

    private function pagadetodoMockPayload($url, array $params)
    {
        $mockScenario = (string) ($params['Description'] ?? '');

        if (strpos($url, 'GenerarClabeIndi') !== false) {
            if ($mockScenario === 'MOCK_MISSING_CLABE') {
                return [
                    'Error' => null,
                    'Message' => 'MOCK SPEI sin CLABE',
                    'Folio' => 'MOCK-FOLIO',
                    'Account' => $params['Account'] ?? 'MOCK-ACCOUNT',
                    'Date' => now()->toDateString(),
                    'Clabe' => '',
                ];
            }

            return [
                'Error' => null,
                'Message' => 'MOCK SPEI generado',
                'Folio' => 'MOCK-FOLIO',
                'Account' => $params['Account'] ?? 'MOCK-ACCOUNT',
                'Date' => now()->toDateString(),
                'Clabe' => '012345678901234567',
            ];
        }

        if (strpos($url, 'GenerarPagoLectorIndi') !== false) {
            if ($mockScenario === 'MOCK_MISSING_LECTOR_REFERENCE') {
                return [
                    'code' => 'success',
                    'message' => 'MOCK lector sin referencia',
                    'codeQR' => '',
                    'reference' => '',
                    'referenceEmisor' => 'MOCK-EMISOR',
                ];
            }

            return [
                'code' => 'success',
                'message' => 'MOCK lector generado',
                'codeQR' => 'MOCK-QR',
                'reference' => $params['Reference'] ?? 'MOCK-REFERENCE',
                'referenceEmisor' => 'MOCK-EMISOR',
            ];
        }

        if (strpos($url, 'PagarDomiciliacionIndi') !== false) {
            return [
                'code' => '00',
                'message' => 'MOCK cargo aprobado',
                'token' => $params['Token'] ?? 'MOCK-TOKEN',
                'txResponse' => [
                    'reference' => $params['Reference'] ?? 'MOCK-REFERENCE',
                    'response' => 'approved',
                    'foliocpagos' => 'MOCK-FOLIOCPAGOS',
                    'auth' => 'MOCK-AUTH',
                    'cd_response' => '00',
                    'cd_error' => '',
                    'nb_error' => '',
                    'time' => now()->format('H:i:s'),
                    'date' => now()->toDateString(),
                    'nb_company' => 'MOCK',
                    'nb_merchant' => 'MOCK',
                    'nb_street' => 'MOCK',
                    'cc_type' => 'VISA',
                    'tp_operation' => 'VENTA',
                    'cc_name' => 'MOCK CARD',
                    'cc_number' => '************1111',
                    'cc_expmonth' => $params['ExpMonth'] ?? '12',
                    'cc_expyear' => $params['ExpYear'] ?? '30',
                    'amount' => $params['Amount'] ?? 0,
                    'voucher' => 'MOCK-VOUCHER',
                    'payment_type' => 'card',
                ],
            ];
        }

        if (strpos($url, 'CancelarDomiciliacionIndi') !== false) {
            return [
                'code' => 'success',
                'message' => 'MOCK cancelacion registrada',
                'reference' => $params['Tkn_reference'] ?? 'MOCK-REFERENCE',
            ];
        }

        if ($mockScenario === 'MOCK_LIGA_ERROR') {
            return [
                'code' => 'error',
                'message' => 'MOCK liga con error',
                'url' => '',
                'reference' => $params['Reference'] ?? 'MOCK-REFERENCE',
                'referenceEmisor' => 'MOCK-EMISOR',
            ];
        }

        if ($mockScenario === 'MOCK_MISSING_URL') {
            return [
                'code' => 'success',
                'message' => 'MOCK liga sin URL',
                'url' => '',
                'reference' => $params['Reference'] ?? 'MOCK-REFERENCE',
                'referenceEmisor' => 'MOCK-EMISOR',
            ];
        }

        return [
            'code' => 'success',
            'message' => 'MOCK liga generada',
            'url' => 'https://mock.pagadetodo.local/pago',
            'reference' => $params['Reference'] ?? 'MOCK-REFERENCE',
            'referenceEmisor' => 'MOCK-EMISOR',
        ];
    }
}
