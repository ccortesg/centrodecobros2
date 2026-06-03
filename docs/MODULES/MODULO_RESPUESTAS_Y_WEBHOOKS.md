# Módulo: Respuestas y webhooks

## Propósito
Recibir y persistir respuestas del proveedor para conciliación y trazabilidad.

## Archivos clave
- `app/Http/Controllers/RespuestaController.php`
- `app/Respuesta.php`
- `routes/api.php` (endpoints `Service/*`)
- `resources/assets/js/components/Respuesta.vue`

## Entradas principales
- `Service/EntregarPagoLiga`
- `Service/EntregarPagoLigaToken`
- `Service/EntregarPagoLector`
- `Service/ConsultaClabe`
- `Service/PagoClabe`
- `Service/CancelaClabe`

## Salidas
- Persistencia en `respuestas`.
- Envío de callback a URL del cliente (`users.ligaPago`) si aplica.
- Marcado de bandera `enviada`.

## Riesgos
- Fase 34 agrego validacion minima, deduplicacion/idempotencia y errores controlados en `Service/*`.
- Falta verificar firma/origen porque no hay especificacion de proveedor disponible.
- Dependencia fuerte de formato externo.
- Sandbox oficial Pagadetodo sigue bloqueado por falta de credenciales/URL no productivas.

## Estado Fase 34

- `Service/EntregarPagoLiga*`: requiere `reference`, `response`, `amount`; campos opcionales se guardan sin forzar indices inexistentes; duplicados por `idtransaccion + reference` devuelven `success`.
- `Service/EntregarPagoLector`: mismo criterio de validacion minima e idempotencia.
- `Service/ConsultaClabe`: referencia vacia o no encontrada ya no dispara errores por variables nulas.
- `Service/PagoClabe`: valida `clabe`, `monto`, `fecha`, `transaccion`; deduplica por `transaccion`.
- `Service/CancelaClabe`: valida `clabe`, `fecha`, `monto`, `transaccion`, `autorizacion`; deduplica por `transaccion + autorizacion`.
- Prueba rectora: `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`.
