# Modulo: Respuestas y webhooks

Ultima actualizacion: 2026-06-03

## Proposito

Recibir, validar y persistir respuestas del proveedor para conciliacion, trazabilidad, callbacks a clientes e idempotencia local.

## Archivos clave

- `app/Http/Controllers/RespuestaController.php`
- `app/Http/Controllers/TransaccionController.php`
- `app/Respuesta.php`
- `routes/api.php`
- `resources/assets/js/components/Respuesta.vue`
- `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`

## Rutas web

- `GET respuesta`
- `POST respuesta/registrar`
- `PUT respuesta/actualizar`
- `PUT respuesta/eliminar`
- `GET respuesta/exportar`

## Webhooks/API legacy sin prefijo `/api`

- `POST Service/EntregarPagoLiga`
- `POST Service/EntregarPagoLigaToken`
- `POST Service/EntregarPagoLector`
- `GET Service/ConsultaClabe`
- `POST Service/PagoClabe`
- `POST Service/CancelaClabe`

## Salidas y efectos

- Persistencia en `respuestas`.
- Actualizacion de transacciones o tablas SPEI segun flujo.
- Callback a URL de cliente (`users.ligaPago`) cuando aplica.
- Marcado de bandera `enviada`.
- Respuestas controladas ante payload incompleto o duplicado.

## Acceso por rol

- Admin: listado y administracion completa.
- Cliente: lectura/exportacion acotada por ownership.
- Webhooks `Service/*`: entradas externas legacy; su seguridad depende del contrato de proveedor, validacion minima e idempotencia local.

## Estado Fase 34

- `Service/EntregarPagoLiga` y `Service/EntregarPagoLigaToken` requieren `reference`, `response`, `amount`; deduplican por `idtransaccion + reference`.
- `Service/EntregarPagoLector` aplica validacion minima e idempotencia equivalente.
- `Service/ConsultaClabe` evita errores por referencias vacias/no encontradas.
- `Service/PagoClabe` requiere `clabe`, `monto`, `fecha`, `transaccion`; deduplica por `transaccion`.
- `Service/CancelaClabe` requiere `clabe`, `fecha`, `monto`, `transaccion`, `autorizacion`; deduplica por `transaccion + autorizacion`.

## Riesgos

- Falta firma/origen de webhook hasta recibir especificacion Pagadetodo.
- Dependencia fuerte de formato externo.
- Callbacks a sistemas cliente pueden tener reintentos/timeouts no normalizados.
- Sandbox oficial Pagadetodo pendiente.

## Pruebas recomendadas

- `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`
- Feature SQLite con `PAGADETODO_MOCK=true`.
- Casos manuales:
  - payload completo;
  - payload incompleto;
  - duplicado exacto;
  - referencia inexistente;
  - rol cliente en listado/exportacion.

## Pendientes y mejoras

- Agregar verificacion de firma/origen cuando proveedor entregue especificacion.
- Guardar evidencia sanitizada de sandbox oficial.
- Formalizar politica de reintentos de callbacks.
- Extraer parseo de payloads a adaptadores probables.
