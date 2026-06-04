# Modulo: SPEI

Ultima actualizacion: 2026-06-03

## Proposito

Gestionar generacion de CLABE/referencia SPEI, consulta de estado, registro de pago, cancelacion y reportes.

## Archivos clave

- `app/Http/Controllers/TransaccionController.php`
- `app/Http/Controllers/ConsultaSpeiController.php`
- `app/Http/Controllers/PagoSpeiController.php`
- `app/Http/Controllers/CancelaSpeiController.php`
- Modelos: `ConsultaSpei`, `PagoSpei`, `CancelaSpei`, `Transaccion`
- Componentes Vue: `ConsultaSpei`, `PagoSpei`, `CancelaSpei`, `ReporteSpei`
- `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`

## Rutas web

- `GET consultaspei`
- `GET consultaspei/exportar`
- `GET pagospei`
- `GET pagospei/exportar`
- `GET pagospei/reportePagoSpei`
- `GET pagospei/exportarReporteSpei`
- `GET cancelaspei`
- `GET cancelaspei/exportar`
- `POST transaccion/registrarSpei`

## Rutas API legacy sin prefijo `/api`

- `POST GenerarSpei`
- `GET Service/ConsultaClabe`
- `POST Service/PagoClabe`
- `POST Service/CancelaClabe`

## Tablas

- `transacciones`
- `consultaspei`
- `pagospei`
- `cancelaspei`
- `clientes`
- `users`

## Acceso por rol

- Admin: consulta y exportacion completa.
- Cliente: acceso acotado por ownership en consultas, pagos, cancelaciones y reportes.
- API externa: credenciales legacy por payload y configuracion Pagadetodo.

## Estado Fase 34

- `Service/ConsultaClabe`: maneja referencia vacia o no encontrada y persiste respuesta controlada `codigo=50` cuando aplica.
- `Service/PagoClabe`: valida `clabe`, `monto`, `fecha`, `transaccion`; rechaza monto no numerico y deduplica por `transaccion`.
- `Service/CancelaClabe`: valida `clabe`, `fecha`, `monto`, `transaccion`, `autorizacion`; deduplica por `transaccion + autorizacion` y maneja pago asociado inexistente.
- `storeSpeiAPI` requiere correo (`Email` o `email`) para continuar.

## Riesgos

- Contrato externo sensible a formato de proveedor.
- Campos de trazabilidad mezclan texto/json.
- Falta firma/origen si Pagadetodo lo soporta.
- Sandbox oficial pendiente.

## Pruebas recomendadas

- Feature SQLite con `PAGADETODO_MOCK=true`.
- `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`.
- Smoke UI admin/cliente:
  - generar SPEI;
  - consultar pagos;
  - exportar reportes;
  - validar ownership.

## Pendientes y mejoras

- Validacion contra sandbox oficial Pagadetodo.
- Fixtures sanitizados de pagos/cancelaciones reales.
- Adapter SPEI separado de `TransaccionController`.
- Pruebas de concurrencia/idempotencia ampliadas.
