# Modulo: SPEI

Ultima actualizacion: 2026-07-10

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

- `GenerarSpei` / `POST transaccion/registrarSpei`: si Pagadetodo no entrega `Clabe` o devuelve error operativo, la transaccion `tipo=3` se guarda con `transacciones.condicion=5` (`Error`).
- `Service/ConsultaClabe`: maneja referencia vacia o no encontrada y persiste respuesta controlada `codigo=50` cuando aplica.
- `Service/PagoClabe`: valida `clabe`, `monto`, `fecha`, `transaccion`; rechaza monto no numerico, deduplica por `transaccion` y, cuando el pago es exitoso (`codigo='0'`), actualiza la transaccion `tipo=3` a `condicion=3` (`Pagado`).
- `Service/CancelaClabe`: valida `clabe`, `fecha`, `monto`, `transaccion`, `autorizacion`; deduplica por `transaccion + autorizacion` y maneja pago asociado inexistente.
- En `shadow/active`, cada `pagospei` persistido publica `spei.payment.approved/rejected` y cada `cancelaspei` publica `spei.payment.cancelled/cancellation_rejected` con clave idempotente por registro.
- `storeSpeiAPI` requiere correo (`Email` o `email`) para continuar.

## Riesgos

- Contrato externo sensible a formato de proveedor.
- Campos de trazabilidad mezclan texto/json.
- Falta firma/origen si Pagadetodo lo soporta.
- Pagadetodo real probado exitosamente desde servidor sandbox/productivo, confirmado por el propietario el 2026-06-08; local no puede reproducir llamadas reales por restriccion de IP de origen.

## Pruebas recomendadas

- Feature SQLite con `PAGADETODO_MOCK=true`.
- `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`.
- Smoke UI admin/cliente:
  - generar SPEI;
  - consultar pagos;
  - exportar reportes;
  - validar ownership.

## Pendientes y mejoras

- Evidencia sanitizada de pruebas Pagadetodo servidor sandbox/productivo.
- Fixtures sanitizados de pagos/cancelaciones reales.
- Adapter SPEI separado de `TransaccionController`.
- Pruebas de concurrencia/idempotencia ampliadas.

## Corte diagnostico 2026-06-07

- Las rutas SPEI web y `Service/*` cargan en el inventario vigente de 100 rutas.
- Los filtros `condicion` y `enviada` de pagos/cancelaciones estan cubiertos por `FinancialFiltersFeatureTest` dentro del carril WAMP/SQLite verde.
- El cliente (`idrol=2`) no tiene SPEI visible en sidebar y el middleware bloquea generacion SPEI oculta; ownership de controladores SPEI esta probado cuando el middleware permite ejecutar.
- Addendum 2026-06-08: pago/cancelacion Pagadetodo fueron probados exitosamente desde servidor en sandbox y productivo segun propietario; pendiente confirmar si Pagadetodo ofrece firma/origen y guardar evidencia sanitizada.
