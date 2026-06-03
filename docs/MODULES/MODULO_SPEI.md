# Módulo: SPEI

## Propósito
Generación de CLABE/referencia SPEI, consulta de estado, registro de pago y cancelación.

## Archivos clave
- `TransaccionController` (métodos `consultaClabe`, `pagoClabe`, `cancelaClabe`, `consultaStatus*`)
- `ConsultaSpeiController`, `PagoSpeiController`, `CancelaSpeiController`
- Modelos `ConsultaSpei`, `PagoSpei`, `CancelaSpei`
- Componentes Vue `ConsultaSpei`, `PagoSpei`, `CancelaSpei`, `ReporteSpei`

## Tablas
- `consultaspei`, `pagospei`, `cancelaspei`, `transacciones`.

## Riesgos
- Flujo sensible a respuesta externa y retries.
- Campos de trazabilidad no normalizados (texto/json mezclado).
- Fase 34 agrego validacion minima e idempotencia local en `Service/PagoClabe` y `Service/CancelaClabe`, pero falta validar contrato real con sandbox Pagadetodo.
- `Service/ConsultaClabe` ya devuelve error controlado ante referencia vacia/no encontrada; sigue pendiente firma/origen si el proveedor lo soporta.

## Estado Fase 34

- `Service/ConsultaClabe`: evita dereferenciar transacciones nulas cuando llega una referencia vacia o no localizable y persiste respuesta controlada `codigo=50` cuando aplica.
- `Service/PagoClabe`: requiere `clabe`, `monto`, `fecha` y `transaccion`; rechaza monto no numerico con respuesta controlada y deduplica por `transaccion`.
- `Service/CancelaClabe`: requiere `clabe`, `fecha`, `monto`, `transaccion` y `autorizacion`; deduplica por `transaccion + autorizacion` y maneja pago asociado inexistente.
- Prueba rectora: `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`.
