<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FinancialUxSourceTest extends TestCase
{
    public function test_response_table_compacts_requested_fields_and_exposes_nb_error_detail(): void
    {
        $source = file_get_contents(__DIR__ . '/../../resources/assets/js/components/Respuesta.vue');

        $this->assertMatchesRegularExpression('/<span>Referencia \/<\/span>\s*<span>Ref\. Transacción<\/span>/s', $source);
        $this->assertMatchesRegularExpression('/<span>Date \/<\/span>\s*<span>Time<\/span>/s', $source);
        $this->assertMatchesRegularExpression('/<span>Folio<\/span>\s*<span>Operación<\/span>/s', $source);
        $this->assertStringContainsString('CD Resp.', $source);
        $this->assertStringContainsString('$formatDateMx(respuesta.date) || respuesta.date', $source);
        $this->assertStringContainsString('fa fa-book', $source);
        $this->assertStringContainsString(':title="respuesta.nb_error"', $source);
        $this->assertStringContainsString('Detalle NB Error', $source);
        $this->assertStringContainsString('v-text="nbErrorDetalle"', $source);
    }

    public function test_response_table_translates_known_statuses_without_changing_their_values(): void
    {
        $source = file_get_contents(__DIR__ . '/../../resources/assets/js/components/Respuesta.vue');

        $this->assertStringContainsString("respuesta.status === 'approved'", $source);
        $this->assertStringContainsString('badge badge-success">Aprobado</span>', $source);
        $this->assertStringContainsString("respuesta.status === 'denied'", $source);
        $this->assertStringContainsString('badge badge-danger cdc-status-expired">Denegado</span>', $source);
    }

    public function test_response_table_stacks_header_filters_above_bottom_aligned_titles(): void
    {
        $source = file_get_contents(__DIR__ . '/../../resources/assets/js/components/Respuesta.vue');
        $styles = file_get_contents(__DIR__ . '/../../resources/assets/js/styles/ux-ui.css');

        $this->assertStringContainsString('cdc-responsive-table cdc-bottom-aligned-headings', $source);
        $this->assertMatchesRegularExpression(
            '/<select[^>]+v-model="offset"[^>]*>.*?<\/select>\s*<span>Opciones<\/span>/s',
            $source
        );
        $this->assertMatchesRegularExpression(
            '/<select[^>]+v-model="filtroStatus"[^>]*>.*?<\/select>\s*<span>Status<\/span>/s',
            $source
        );
        $this->assertStringContainsString('.cdc-bottom-aligned-headings thead th', $styles);
        $this->assertStringContainsString('vertical-align: bottom;', $styles);
        $this->assertStringContainsString('.cdc-header-control-stack', $styles);
    }

    public function test_expired_transaction_status_uses_red_badge_with_dark_text_contract(): void
    {
        $source = file_get_contents(__DIR__ . '/../../resources/assets/js/components/Transaccion.vue');
        $styles = file_get_contents(__DIR__ . '/../../resources/assets/js/styles/ux-ui.css');

        $this->assertStringContainsString('badge badge-danger cdc-status-expired', $source);
        $this->assertStringContainsString('.cdc-status-expired', $styles);
        $this->assertStringContainsString('color: #000 !important;', $styles);
    }

    public function test_response_and_recurring_charge_statuses_use_financial_badge_contract(): void
    {
        $respuesta = file_get_contents(__DIR__ . '/../../resources/assets/js/components/Respuesta.vue');
        $transaccionDom = file_get_contents(__DIR__ . '/../../resources/assets/js/components/TransaccionDom.vue');

        foreach ([$respuesta, $transaccionDom] as $source) {
            $this->assertStringContainsString("status === 'approved'", $source);
            $this->assertStringContainsString('badge badge-success', $source);
            $this->assertStringContainsString("status === 'denied'", $source);
            $this->assertStringContainsString('badge badge-danger cdc-status-expired', $source);
            $this->assertStringContainsString("status === 'error'", $source);
            $this->assertStringContainsString('badge badge-warning', $source);
        }
    }

    public function test_recurring_charge_search_exposes_operation_folio_and_authorization(): void
    {
        $source = file_get_contents(__DIR__ . '/../../resources/assets/js/components/TransaccionDom.vue');

        $this->assertStringContainsString('<option value="foliocpagos">Folio Operación</option>', $source);
        $this->assertStringContainsString('<option value="auth">Núm. Autorización</option>', $source);
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
