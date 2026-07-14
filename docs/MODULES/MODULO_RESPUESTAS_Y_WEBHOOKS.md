# Modulo: Respuestas y webhooks

Ultima actualizacion: 2026-07-10

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
- En `legacy/shadow`, callback aprobado a `users.ligaPago`; en `active`, evento configurable en Database Queue.
- Marcado de bandera `enviada`.
- Respuestas controladas ante payload incompleto o duplicado.

## Filtros de busqueda

En `Respuesta.vue`, el select usa campos distintos al listado de transacciones:

- `Ref. Cliente`: `transacciones.ClientReference`.
- `Ref. Transacción`: `transacciones.Reference`.
- `Ref. Respuesta`: `respuestas.reference`.
- `Folio Operación`: `respuestas.foliocpagos`.
- `Núm. Autorización`: `respuestas.auth`; el criterio HTTP se llama `autorizacion` para usar una etiqueta funcional consistente.
- `Cliente`: `clientes.razon_social`.
- El filtro de status usa `respuestas.status`.

La relacion entre tablas es `respuestas.idtransaccion = transacciones.id`. No confundir `respuestas.reference` con `transacciones.responseReference`: suelen coincidir en flujos normales, pero pueden divergir en datos historicos o registros desnormalizados.

El esquema vigente no contiene `respuestas.autorizacion`. El numero de autorizacion entregado por Pagadetodo se persiste en `respuestas.auth`; no se debe crear una segunda columna para el mismo dato.

Filtro de fechas:

- `Desde` y `Hasta` filtran `respuestas.fecha`.
- Al entrar al modulo, el frontend usa el rango de los ultimos 30 dias, con la fecha actual como `Hasta`.
- `Limpiar` borra `Texto a buscar`, `Desde` y `Hasta`.
- El selector de cantidad inicia en `50` registros.

Presentacion compacta del listado:

- En los encabezados `Opciones` y `Status`, el select aparece centrado en el primer renglón y el título en el segundo. El resto de encabezados se alinea horizontalmente al centro y verticalmente al borde inferior de la celda.
- `Referencia` y `Ref. Transacción` comparten una columna, en dos renglones del mismo tamaño; el encabezado también se divide en esos dos renglones.
- `Folio Operación` se muestra como etiqueta funcional de `respuestas.foliocpagos`, con el encabezado dividido en dos renglones. `CD Response` se abrevia visualmente como `CD Resp.`.
- `Date` y `Time` comparten una columna y un encabezado de dos renglones; `Time` aparece con tipografia menor. `Date` usa el formato visual mexicano cuando el valor es reconocible y conserva el texto original recibido cuando el formato externo no puede interpretarse.
- `NB Error` no despliega texto extenso en la tabla. Cuando existe contenido muestra un icono de libro con tooltip nativo y un modal de detalle; cuando está vacío, la celda permanece vacía.
- La columna `Status` conserva internamente los valores de origen y usa el contrato visual financiero: `approved` se presenta como `Aprobado` con badge verde de Activo, `denied` como `Denegado` con badge rojo y texto negro de Vencido, y `error` conserva su badge amarillo de Pendiente.

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
- El callback legacy conserva su comportamiento. El motor `active` agrega timeout, ACK, reintentos e idempotencia; ver `MODULO_NOTIFICACIONES_WEBHOOK_CONFIGURABLES.md`.
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
- Completar rollout por cliente del motor configurable y retirar callbacks legacy solo despues de validar `active`.
- Extraer parseo de payloads a adaptadores probables.

## Corte diagnostico 2026-06-07

- Las rutas `Service/EntregarPagoLiga`, `Service/EntregarPagoLigaToken`, `Service/EntregarPagoLector`, `Service/ConsultaClabe`, `Service/PagoClabe` y `Service/CancelaClabe` siguen vigentes sin prefijo `/api`.
- `tests\Feature\Phase34\WebhookIdempotencyFeatureTest.php` queda cubierto dentro del carril Feature aislado WAMP/SQLite que paso con 52 tests y 170 assertions.
- Addendum 2026-06-08: el bloqueo principal ya no es sandbox Pagadetodo; las pruebas reales fueron exitosas en servidor. El bloqueo local es una restriccion de IP de origen del proveedor.
- Sigue pendiente confirmar firma/origen del proveedor y conservar evidencia sanitizada de servidor.
- El Feature completo fallo por smoke tests MySQL locales; no usar ese resultado para degradar el estado de webhooks sin separar el runner.
