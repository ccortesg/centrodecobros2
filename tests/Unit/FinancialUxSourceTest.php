<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FinancialUxSourceTest extends TestCase
{
    public function test_response_table_compacts_requested_fields_and_exposes_nb_error_detail(): void
    {
        $source = file_get_contents(__DIR__ . '/../../resources/assets/js/components/Respuesta.vue');

        $this->assertStringContainsString('Referencia / Ref. Transacción', $source);
        $this->assertStringContainsString('Date / Time', $source);
        $this->assertStringContainsString('fa fa-book', $source);
        $this->assertStringContainsString(':title="respuesta.nb_error"', $source);
        $this->assertStringContainsString('Detalle NB Error', $source);
        $this->assertStringContainsString('v-text="nbErrorDetalle"', $source);
    }

    public function test_expired_transaction_status_uses_red_badge_with_dark_text_contract(): void
    {
        $source = file_get_contents(__DIR__ . '/../../resources/assets/js/components/Transaccion.vue');
        $styles = file_get_contents(__DIR__ . '/../../resources/assets/js/styles/ux-ui.css');

        $this->assertStringContainsString('badge badge-danger cdc-status-expired', $source);
        $this->assertStringContainsString('.cdc-status-expired', $styles);
        $this->assertStringContainsString('color: #000 !important;', $styles);
    }

    public function test_direct_financial_actions_guard_http_requests_with_confirmation(): void
    {
        $transaccion = file_get_contents(__DIR__ . '/../../resources/assets/js/components/Transaccion.vue');
        $domiciliacion = file_get_contents(__DIR__ . '/../../resources/assets/js/components/DomiciliacionActiva.vue');

        $this->assertConfirmationBeforeRequest(
            $this->methodSegment($transaccion, 'cancelarImportacionConConfirmacion(){', 'descargarLogImportacion(){'),
            "axios.post('/transaccion/importar/cancelar'"
        );
        $this->assertConfirmationBeforeRequest(
            $this->methodSegment($transaccion, 'rechazarTransaccion(id){', 'validarTransaccion(){'),
            "axios.put('/transaccion/rechazar'"
        );
        $this->assertConfirmationBeforeRequest(
            $this->methodSegment($domiciliacion, 'cancelarDomiciliacion(id) {', 'cancelacionExitosa(respuesta) {'),
            "axios.put('/transaccion/rechazar'"
        );
        $this->assertConfirmationBeforeRequest(
            $this->methodSegment($domiciliacion, 'cargarDomiciliacion(id) {', 'abrirModalProximoCargo(domiciliacion) {'),
            "axios.post('/transaccionDom/registrar'"
        );
    }

    private function methodSegment(string $source, string $startMarker, string $endMarker): string
    {
        $start = strpos($source, $startMarker);
        $this->assertNotFalse($start, 'No se encontro el inicio del metodo ' . $startMarker);

        $end = strpos($source, $endMarker, $start);
        $this->assertNotFalse($end, 'No se encontro el fin del metodo ' . $startMarker);

        return substr($source, $start, $end - $start);
    }

    private function assertConfirmationBeforeRequest(string $segment, string $requestMarker): void
    {
        $this->assertStringContainsString('showCancelButton: true', $segment);

        $confirmationGuard = strpos($segment, 'result.value');
        $request = strpos($segment, $requestMarker);

        $this->assertNotFalse($confirmationGuard, 'La accion no valida la confirmacion del usuario.');
        $this->assertNotFalse($request, 'No se encontro la peticion esperada.');
        $this->assertLessThan($request, $confirmationGuard, 'La peticion se ejecuta antes de validar la confirmacion.');
    }
}
