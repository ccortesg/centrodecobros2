# Modulo: Respuestas y webhooks

Ultima actualizacion: 2026-06-18

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

## Filtros de busqueda

En `Respuesta.vue`, el select usa campos distintos al listado de transacciones:

- `Ref. Cliente`: `transacciones.ClientReference`.
- `Ref. Transacción`: `transacciones.Reference`.
- `Ref. Respuesta`: `respuestas.reference`.
- `Cliente`: `clientes.razon_social`.
- El filtro de status usa `respuestas.status`.

La relacion entre tablas es `respuestas.idtransaccion = transacciones.id`. No confundir `respuestas.reference` con `transacciones.responseReference`: suelen coincidir en flujos normales, pero pueden divergir en datos historicos o registros desnormalizados.

Filtro de fechas:

- `Desde` y `Hasta` filtran `respuestas.fecha`.
- Al entrar al modulo, el frontend usa el rango de los ultimos 30 dias, con la fecha actual como `Hasta`.
- `Limpiar` borra `Texto a buscar`, `Desde` y `Hasta`.
- El selector de cantidad inicia en `50` registros.

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
- Pagadetodo real probado exitosamente desde servidor en sandbox y productivo, confirmado por el propietario el 2026-06-08.
- Validacion local real bloqueada por restriccion de IP de origen del proveedor; usar mock local.

## Pruebas recomendadas

- `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`
- Feature SQLite con `PAGADETODO_MOCK=true`.
- Casos manuales:
  - payload completo;
  - payload incompleto;
  - duplicado exacto;
  - referencia inexistente;
  - rol cliente en listado/exportacion.
  - filtro `Ref. Respuesta` buscando por `respuestas.reference`.

## Pendientes y mejoras

- Agregar verificacion de firma/origen cuando proveedor entregue especificacion.
- Guardar evidencia sanitizada de pruebas servidor sandbox/productivo.
- Formalizar politica de reintentos de callbacks.
- Extraer parseo de payloads a adaptadores probables.

## Corte diagnostico 2026-06-07

- Las rutas `Service/EntregarPagoLiga`, `Service/EntregarPagoLigaToken`, `Service/EntregarPagoLector`, `Service/ConsultaClabe`, `Service/PagoClabe` y `Service/CancelaClabe` siguen vigentes sin prefijo `/api`.
- `tests\Feature\Phase34\WebhookIdempotencyFeatureTest.php` queda cubierto dentro del carril Feature aislado WAMP/SQLite que paso con 52 tests y 170 assertions.
- Addendum 2026-06-08: el bloqueo principal ya no es sandbox Pagadetodo; las pruebas reales fueron exitosas en servidor. El bloqueo local es una restriccion de IP de origen del proveedor.
- Sigue pendiente confirmar firma/origen del proveedor y conservar evidencia sanitizada de servidor.
- El Feature completo fallo por smoke tests MySQL locales; no usar ese resultado para degradar el estado de webhooks sin separar el runner.
