# Modulo de Notificaciones Webhook Configurables

Ultima actualizacion: 2026-07-10

## Objetivo

Sustituir gradualmente los callbacks fijos de `users.ligaPago` y `users.ligaRecurrente` por un motor administrable, idempotente y ejecutado mediante colas. El modulo permite definir por cliente:

- endpoints HTTPS;
- eventos suscritos;
- formato de payload;
- criterio de confirmacion;
- limite de solicitudes por minuto;
- autenticacion opcional HMAC-SHA256;
- modo de transicion `legacy`, `shadow`, `active` o `disabled`.

No cambia rutas, payloads ni contratos de Pagadetodo. Tampoco modifica la frecuencia del scheduler financiero.

## Acceso y pantallas

Solo el rol Administrador (`users.idrol = 1`) puede acceder.

| Menu | Target | Componente | Funcion |
| --- | ---: | --- | --- |
| Webhook Configuration | 34 | `WebhookConfiguration.vue` | Configura cliente, modo, HMAC, endpoints y eventos. |
| Webhook Deliveries | 35 | `WebhookDeliveries.vue` | Consulta, exporta, reintenta, cancela y revisa intentos sanitizados. |

Ambas opciones viven bajo `Integraciones`, despues de los tres modulos de auditoria existentes. `contenido.blade.php` las renderiza solo dentro del bloque de Administrador.

## Modos por cliente

| Modo | Callback legacy | Nuevo motor | Uso |
| --- | --- | --- | --- |
| `legacy` | Si | No crea eventos | Comportamiento anterior sin cambios. |
| `shadow` | Si | Crea evento y entrega `shadow`, pero no hace HTTP | Comparacion previa sin interrumpir notificaciones vigentes. |
| `active` | No | Encola y entrega por el nuevo motor | Operacion final en background. |
| `disabled` | No | No crea ni envia | Suspender notificaciones de ese cliente. |

El interruptor global `WEBHOOK_NOTIFICATIONS_ENABLED` domina la configuracion. Si esta en `false`, el sistema conserva el flujo legacy y no consulta las tablas nuevas.

## Eventos disponibles

| Evento | Fuente |
| --- | --- |
| `payment_link.payment.approved` | Respuesta aprobada de liga unica (`transacciones.tipo=1`). |
| `payment_link.payment.rejected` | Respuesta no aprobada de liga unica. |
| `domiciliation_link.payment.approved` | Pago aprobado de liga de domiciliacion (`tipo=2`). |
| `domiciliation_link.payment.rejected` | Respuesta no aprobada de liga de domiciliacion. |
| `domiciliation.activated` | Respuesta aprobada de domiciliacion con token. |
| `domiciliation.activation_failed` | Respuesta aprobada sin token. |
| `domiciliation.cancelled` | Cancelacion local persistida desde UI o API. |
| `domiciliation.cancellation_failed` | Fallo previo a persistir una cancelacion. |
| `recurring_charge.approved` | Cargo recurrente `code=00` y `status=approved`. |
| `recurring_charge.rejected` | Cargo recurrente con rechazo controlado. |
| `recurring_charge.error` | Cargo sin codigo o mensaje util. |
| `spei.payment.approved` | `pagospei.codigo` 0/00 aceptado. |
| `spei.payment.rejected` | Pago SPEI rechazado por formato, monto, vigencia u otra regla. |
| `spei.payment.cancelled` | Cancelacion SPEI con codigo 0/00. |
| `spei.payment.cancellation_rejected` | Cancelacion SPEI rechazada. |
| `terminal.payment.approved` | Respuesta aprobada de terminal (`tipo=4`). |
| `terminal.payment.rejected` | Respuesta no aprobada de terminal. |
| `terminal.cancelled` | Cancelacion de terminal persistida. |
| `terminal.cancellation_failed` | Fallo al cancelar terminal. |
| `webhook.endpoint.test` | Prueba manual de endpoint; no admite suscripcion. |

Los cargos recurrentes permiten filtrar origen `manual`, `api` o `automatic`. Para los demas eventos se usa `all`.

## Integracion con flujos existentes

- `RespuestaController@storePublic`: liga unica y domiciliacion.
- `RespuestaController@storeLectorPublic`: terminal.
- `TransaccionDomController@store`: cargo manual.
- `TransaccionDomController@storeAPI`: cargo por API.
- `TransaccionDomController@ejecutarCron`: cargo automatico.
- `TransaccionController@pagoClabe`: recepcion o rechazo SPEI.
- `TransaccionController@cancelaClabe`: cancelacion o rechazo SPEI.
- `TransaccionController@rechazar`: cancelacion UI de domiciliacion/terminal.
- `TransaccionController@cancelarDomAPI`: cancelacion API de domiciliacion.

La clave idempotente se deriva del tipo de evento y del registro persistido, por ejemplo `payment_link.payment.approved:respuesta:123`. Un webhook entrante duplicado reutiliza el evento y la entrega existentes; una entrega ya finalizada no vuelve a enviarse.

## Payloads

### `legacy_exact`

Envia el payload compatible construido por el flujo historico. Se usa para migrar endpoints actuales sin exigir un cambio inmediato al receptor.

### `soportetech_v1`

Envia un envelope:

```json
{
  "event_id": "uuid-estable",
  "event_type": "payment_link.payment.approved",
  "occurred_at": "2026-07-10T12:00:00-07:00",
  "source": "webhook",
  "data": {}
}
```

El payload de entrega conserva los valores completos recibidos por Centro de Cobros, incluidos los campos de tarjeta ya protegidos por el sistema de origen y la respuesta cruda de cargos recurrentes. No se enmascara ni se elimina informacion en el cuerpo HTTP enviado.

La sanitizacion de `AuditSanitizer` se aplica exclusivamente a bitacoras, detalle administrativo y exportaciones. No se aplica a `webhook_deliveries.raw_body` antes de enviar; este campo se cifra con el cast de Laravel.

## HMAC-SHA256

HMAC se habilita por cliente. El secreto se almacena cifrado, nunca se vuelve a mostrar y solo se entrega una vez al generarlo o rotarlo.

Cada peticion firmada incluye:

```text
X-Soportetech-Timestamp: <Unix timestamp UTC en segundos>
X-Soportetech-Event-Id: <UUID estable del evento>
X-Soportetech-Signature: sha256=<hexadecimal minuscula>
```

La firma usa exactamente:

```text
{timestamp}.{event_id}.{raw_request_body}
```

Formula:

```text
sha256=hex_lower(HMAC-SHA256(shared_secret, canonical_string))
```

Reglas acordadas para `app.donarconcausa.org.mx`:

- HTTPS obligatorio;
- tolerancia temporal del receptor: 300 segundos;
- proteccion anti-replay: 10 minutos;
- limite receptor: 30 solicitudes por minuto por IP;
- limite emisor recomendado/configurado: 25 por minuto por host, con maximo duro de 30.

El `event_id` y el cuerpo permanecen estables en reintentos. El timestamp y la firma se regeneran para cada intento. El receptor debe tratar el `event_id` como clave de idempotencia y devolver una confirmacion exitosa tambien ante un duplicado ya procesado.

## URL y confirmacion

La configuracion exige:

- URL valida;
- esquema `https`;
- host presente;
- sin credenciales embebidas;
- sin fragmento URL.

No se aplica allowlist, validacion DNS ni bloqueo de rangos privados, por decision funcional: los endpoints pertenecen a plataformas internas. Esta excepcion debe conservarse documentada.

Modos de ACK:

- `http_2xx`: cualquier respuesta HTTP 200-299 confirma la entrega.
- `legacy_code_success`: requiere HTTP 200-299 y JSON con `code=success`.

## Colas, reintentos y limites

- Conexion: `WEBHOOK_QUEUE_CONNECTION`, recomendada `database`.
- Cola: `WEBHOOK_QUEUE_NAME`, default `webhooks`.
- Recuperacion de una entrega atascada en `processing`: 60 s por default (`WEBHOOK_PROCESSING_TIMEOUT`).
- Maximo: 8 intentos de negocio.
- Backoff: 60 s, 5 min, 15 min, 1 h, 3 h, 6 h y 12 h.
- Reintentables: error de red, sin respuesta, HTTP 408/425/429/5xx y ACK legacy no confirmado.
- No reintentables: otros 4xx y errores permanentes de configuracion.
- `Retry-After` se respeta con limite de 24 horas.
- El limite se agrega por host, no por endpoint, para respetar 30 peticiones/minuto por IP receptora.

Estados de entrega: `pending`, `processing`, `retrying`, `delivered`, `dead`, `cancelled`, `shadow`.

Un fallo al crear/auditar/encolar un evento se registra como warning y no revierte la operacion financiera que lo origino.

## Modelo de datos

| Tabla | Relacion/uso |
| --- | --- |
| `webhook_user_settings` | Una configuracion por `users.id`. |
| `webhook_endpoints` | Varios endpoints por usuario; URL cifrada y hash para unicidad. |
| `webhook_endpoint_subscriptions` | Eventos/origen habilitados por endpoint. |
| `webhook_events` | Evento de negocio idempotente; payload cifrado. |
| `webhook_deliveries` | Una entrega por evento/endpoint; cuerpo exacto cifrado. |
| `webhook_delivery_attempts` | Intentos con request/response sanitizados. |
| `webhook_rate_limits` | Ventana fija agregada por host. |
| `jobs` / `failed_jobs` | Infraestructura Laravel Database Queue. |

`cancelacionesDom.idtransaccion` y `cancelacionesLector.idtransaccion` agregan correlacion explicita con la transaccion origen.

## Rutas administrativas

- `GET|POST /integraciones/webhooks/configuracion`
- `POST /integraciones/webhooks/endpoints`
- `PUT|DELETE /integraciones/webhooks/endpoints/{id}`
- `POST /integraciones/webhooks/endpoints/{id}/test`
- `GET /integraciones/webhooks/entregas`
- `GET /integraciones/webhooks/entregas/exportar`
- `GET /integraciones/webhooks/entregas/{id}`
- `POST /integraciones/webhooks/entregas/{id}/reintentar`
- `POST /integraciones/webhooks/entregas/{id}/cancelar`

Todas estan bajo `auth + Administrador`.

## Migracion legacy y activacion

El comando no imprime URLs completas ni secretos:

```bash
php artisan webhooks:import-legacy --dry-run --mode=shadow
php artisan webhooks:import-legacy --mode=shadow
```

Importa endpoints desde `users.ligaPago` y `users.ligaRecurrente` para usuarios con `notificaPago=1`. No habilita HMAC automaticamente porque el secreto debe intercambiarse fuera de logs/Git.

Secuencia segura por cliente:

1. Mantener interruptor global deshabilitado durante deploy/migraciones.
2. Crear/importar configuracion con modo `shadow`.
3. Habilitar interruptor global y comprobar que el callback legacy sigue operando.
4. Revisar entregas `shadow` y payloads esperados.
5. Configurar/rotar HMAC y entregar el secreto una sola vez por canal seguro.
6. Usar `Enviar prueba` y validar firma/ACK del receptor.
7. Confirmar worker, tabla `jobs`, `failed_jobs` y logs.
8. Cambiar solo el cliente validado a `active`.
9. Vigilar entregas, reintentos y eventos durante la ventana acordada.
10. Para rollback funcional, volver ese cliente a `shadow` o `legacy`; no borrar eventos/tablas.

## Riesgos residuales

- El JOIN del cron de cargos recurrentes puede duplicar domiciliaciones si existen varias respuestas aprobadas. No se modifico por decision del propietario; sigue como pendiente financiero separado.
- El payload real no se sanitiza por requerimiento funcional. La confidencialidad depende de HTTPS, control del endpoint y HMAC cuando se habilite.
- Database Queue requiere un worker persistente; activar un cliente sin worker deja entregas pendientes.
- El crecimiento de eventos/intentos requiere una politica de retencion futura. No se agrego scheduler de purga.
- Los modos son por cliente, no por endpoint. Cambiar a `active` afecta todas sus suscripciones activas.
- No se hicieron llamadas reales a Pagadetodo ni al receptor desde local; Pagadetodo restringe IP de origen.

## Validaciones locales del corte

```text
PHPUnit Unit: 19 pruebas, 97 aserciones.
PHPUnit Feature focalizado WAMP/SQLite: 51 pruebas, 171 aserciones.
PHPUnit Feature completo: 122 de 135 pruebas pasaron; 13 smoke fallaron exclusivamente por acceso denegado a la MySQL local configurada.
Build frontend de produccion: correcto, sin versionar assets compilados.
Smoke visual Chrome: correcto para Administrador y Cliente en desktop/movil; modal movil con scroll validado.
```

Volver a ejecutar las suites despues de cualquier cambio. Los 13 smoke acoplados a MySQL requieren un dataset/usuario local valido; no deben resolverse con credenciales productivas. El build frontend debe ejecutarse sin versionar `public/js`, `public/css` ni `public/build`.
