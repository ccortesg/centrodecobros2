# Guia de Implementacion del Receptor Webhook Soportetech V1.1

Version del documento: 1.1  
Fecha de actualizacion: 2026-07-14  
Estado: contrato vigente para donaciones; V1 se conserva como referencia legacy  
Audiencia: equipos que desarrollan la plataforma receptora de eventos Soportetech

## 0. Alcance autoritativo de V1.1

V1.1 introduce dos canales deliberadamente separados. No se debe configurar una
misma cuenta o endpoint para ambos:

| Canal | Endpoint receptor | Payload | Uso |
| --- | --- | --- | --- |
| Donaciones | `POST /api/webhooks/soportetech/v1` | Envelope V1.1 | Pago unico, alta de domiciliacion, cargos y ciclo de vida recurrente |
| Eventos | `POST /api/aplicaPagoB` | `{"folio":<registration_id>,"monto":<pesos>}` | Solo confirmacion aprobada de boleto |

La cuenta de eventos conserva permanentemente `/api/aplicaPagoB`. No se publica
ningun evento de boletos en el endpoint unificado. La cuenta de donaciones no
debe apuntar al callback de eventos. Los secretos HMAC y las credenciales Getnet
son distintos por canal.

SPEI, terminal y cualquier `resource_type` diferente de `donation` quedan fuera
del endpoint unificado de Donar con Causa. Los apartados posteriores que describen
esas familias corresponden al contrato V1 generico y no autorizan su envio a V1.1.

### 0.0 Compatibilidad temporal para donaciones unicas

Mientras `SUPPORTTECH_V1_ENABLED=false`, el pago unico puede seguir entregandose
a `POST /api/aplicaPago` mediante un endpoint configurable
`channel=donation`, `payload_mode=legacy_exact` y
`ack_mode=legacy_code_success`:

```json
{
  "folio": "dcc:donation:123",
  "monto": 50.00,
  "idtransaccion": 9001
}
```

En este formato `monto` esta expresado en pesos, no en centavos. El receptor
acepta temporalmente pesos o centavos solo si la interpretacion coincide con la
intencion. SOPORTETECH debe enviar pesos, HMAC, `Accept: application/json`, no
seguir redirects y conservar el mismo evento para reintentos.

Una respuesta aprobada no aplicada se valida con
`php artisan webhooks:replay-response {response-id} --dry-run` y se publica
quitando `--dry-run`. El comando no acepta pagos de eventos, respuestas
rechazadas ni referencias distintas de `dcc:donation:{id}`. La marca
`respuestas.enviada=1` se establece solo despues del ACK `code=success`.

### 0.1 Envelope de donaciones

```json
{
  "event_id": "f8756d5e-1517-4d04-b552-78d2ca02dc97",
  "event_type": "recurring_charge.approved",
  "occurred_at": "2026-07-14T10:25:38-07:00",
  "source": "automatic",
  "data": {
    "schema_version": "1.1",
    "resource_type": "donation",
    "client_reference": "dcc:donation:123",
    "supporttech_transaction_id": 9001,
    "provider_reference": "provider-reference",
    "currency": "MXN",
    "amount_minor": 150000,
    "provider_status": "approved",
    "code": "00",
    "message": "Procesado",
    "subscription_reference": "st:domiciliation:9001",
    "charge_reference": "st:charge:4321",
    "rejected_attempts": 0,
    "card_brand": "VISA",
    "card_last_four": "1111"
  }
}
```

Reglas de datos:

- `schema_version` debe ser exactamente `1.1`.
- `resource_type` debe ser exactamente `donation`.
- `client_reference` se construye como `dcc:donation:{id}` y debe correlacionar
  la intencion creada previamente en Donar con Causa.
- `supporttech_transaction_id` es `transacciones.id`, no una referencia de la
  respuesta del proveedor.
- `amount_minor` es un entero en centavos. `150000` representa MXN 1,500.00.
- `subscription_reference` es estable para toda la domiciliacion.
- `charge_reference` es unica por cargo recurrente.
- Solo se permiten marca y ultimos cuatro digitos. Se prohiben PAN, token,
  titular, vencimiento, contrasenas y respuestas crudas del proveedor.
- `provider_reference` puede ser `null` cuando el proveedor no la entregue.

### 0.2 Eventos aceptados por el endpoint unificado

| Evento | Efecto esperado en el receptor |
| --- | --- |
| `payment_link.payment.approved` | Liquidar una donacion unica exactamente una vez |
| `payment_link.payment.rejected` | Registrar diagnostico sin liquidar |
| `domiciliation_link.payment.approved` | Liquidar primer pago y crear suscripcion `pending` |
| `domiciliation_link.payment.rejected` | Mantener intencion pendiente |
| `domiciliation.activated` | Cambiar suscripcion a `active`, sin movimiento financiero |
| `domiciliation.activation_failed` | Conservar suscripcion `unknown` y diagnostico |
| `recurring_charge.approved` | Crear cargo, donacion, transaccion y earnings una sola vez |
| `recurring_charge.rejected` | Registrar rechazo e intentos, sin liquidar |
| `recurring_charge.error` | Registrar error e intentos, sin liquidar |
| `domiciliation.cancelled` | Aplicar estado terminal, fecha, motivo e intentos |
| `domiciliation.cancellation_failed` | Registrar fallo sin marcar cancelada |
| `webhook.endpoint.test` | Probar transporte, HMAC, outbox/inbox y ACK sin efecto financiero |

`resource_type=event_registration` debe recibir `422` y quedar como
`misrouted`. Tipos de evento desconocidos deben recibir tratamiento
`unsupported` sin efectos financieros.

### 0.3 Firma HMAC-SHA256

Headers obligatorios para V1.1:

```text
Content-Type: application/json
X-Soportetech-Timestamp: 1784053538
X-Soportetech-Event-Id: f8756d5e-1517-4d04-b552-78d2ca02dc97
X-Soportetech-Event-Type: recurring_charge.approved
X-Soportetech-Signature: sha256=<hexadecimal-minusculas>
```

Cadena firmada, sin reformatear ni volver a serializar el JSON:

```text
{timestamp}.{event_id}.{raw_request_body}
```

El emisor calcula:

```text
hex_hmac_sha256(secret, signed_string)
```

El receptor compara en tiempo constante, exige UUID, verifica concordancia
headers/body y rechaza timestamps fuera de una tolerancia de 300 segundos. Cada
reintento conserva `event_id` y body, pero recibe timestamp y firma nuevos. El
secreto de donaciones no se reutiliza para eventos.

### 0.4 ACK, idempotencia y reintentos

El ACK significa que el receptor persistio durablemente el inbox y encolo el
trabajo; no significa que concluyo la liquidacion:

```json
{"code":"success","event_id":"f8756d5e-1517-4d04-b552-78d2ca02dc97","duplicate":false}
```

- Evento nuevo persistido: `200`, `duplicate=false`.
- Mismo `event_id` y mismo body: `200`, `duplicate=true`.
- Mismo `event_id` con otro body: `409`.
- Error antes de persistir o encolar: `500` para solicitar reintento.
- Body mayor a 256 KB, firma invalida o contrato invalido: rechazo sin negocio.

Centro de Cobros usa outbox durable, entrega al menos una vez y clave idempotente
por evento persistido. Donar con Causa usa `supporttech:v1:{event_id}` para la
liquidacion financiera.

### 0.5 Modo de migracion

Los modos soportados son `legacy`, `shadow`, `hybrid` y `active`:

- `hybrid` publica V1.1 solo para tipos que tengan una suscripcion activa.
- Si un tipo ya se entrega por V1.1, no se ejecuta su callback legacy.
- Las familias se migran gradualmente: lifecycle, cargos recurrentes,
  domiciliacion inicial y pago unico.
- Al migrar `domiciliation_link.payment.approved`, el endpoint debe tener tambien
  `domiciliation.activated` y `domiciliation.activation_failed`.
- Los endpoints legacy de donaciones pueden permanecer durante observacion.
- `/api/aplicaPagoB` no se retira con la migracion de donaciones.

### 0.6 Cancelacion automatica

Los rechazos se cuentan consecutivamente bajo bloqueo de la transaccion:

- cargo aprobado: `intentos=0`;
- cargo rechazado/error: `intentos=intentos+1`;
- al tercer rechazo: detener cargos (`condicion=0`), pasar a
  `cancellation_pending` y solicitar cancelacion con clave estable
  `st:cancel:{transacciones.id}`;
- solo una cancelacion confirmada y persistida emite `domiciliation.cancelled`;
- fallo de proveedor o persistencia emite `domiciliation.cancellation_failed`
  y conserva el estado pendiente para reintento.
- un claim transaccional evita llamadas concurrentes al proveedor; la ventana
  de reintento default es de 300 segundos.

### 0.7 Canal de eventos conservado

El endpoint de eventos usa una cuenta, secreto y configuracion independientes:

```http
POST /api/aplicaPagoB
Content-Type: application/json

{"folio":1234,"monto":500.00}
```

Solo se envia `payment_link.payment.approved`. No se envia un rechazo a esta
ruta porque el payload legacy no contiene estado y podria interpretarse como una
aprobacion. El `event_id` estable y HMAC se envian en headers. Un reintento ya
confirmado recibe ACK exitoso sin descontar cupo, generar boleto o correo otra
vez.

### 0.8 Validacion local del corte V1.1

Resultados reproducibles del 2026-07-14 con PHP WAMP 8.3 y SQLite aislado:

- `WebhookNotificationFeatureTest`: 24 tests, 96 assertions.
- `DomiciliacionAndPaymentsFeatureTest`: 30 tests, 117 assertions.
- Suite aislada sin smoke tests dependientes de MySQL: 153 tests, 640 assertions.
- `php artisan route:list`: 121 rutas.
- `php artisan optimize`: correcto.
- `npm run prod`: correcto para lane legacy, guest, Vite y bridge publico.

La ejecucion total alcanzo 179 tests y 785 assertions, con dos fallos exclusivos
de smoke contra la MySQL local obsoleta: falta la columna historica
`transacciones.ProximoCargoBase` y la tabla historica `transaccionesDom`. Las
suites aisladas que ejercitan V1.1 pasan; los dos smoke requieren actualizar el
esquema local mediante las migraciones historicas, no credenciales ni datos de
produccion.

---

## Referencia legacy V1

El contenido siguiente documenta el contrato generico anterior. Para la
integracion Donar con Causa prevalecen las secciones 0.1 a 0.7.

## 1. Objetivo

Este documento define como desarrollar un endpoint HTTPS capaz de recibir, autenticar, almacenar y procesar los eventos que Centro de Cobros envia con el formato `soportetech_v1`.

La implementacion receptora debe cumplir cuatro objetivos principales:

1. Verificar que la solicitud fue firmada por Soportetech cuando HMAC este habilitado.
2. Evitar que un mismo evento produzca efectos de negocio duplicados.
3. Confirmar la recepcion con el codigo HTTP y cuerpo esperados.
4. Procesar de forma segura todos los eventos financieros actualmente disponibles.

Esta guia describe el contrato saliente de Centro de Cobros. No describe los webhooks que Pagadetodo envia a Centro de Cobros ni modifica esos contratos.

## 2. Resumen del contrato

| Elemento | Contrato |
| --- | --- |
| Transporte | HTTPS obligatorio |
| Metodo | `POST` |
| Content type | `application/json` |
| Formato | Un evento por solicitud |
| Payload mode | `soportetech_v1` |
| Autenticacion | HMAC-SHA256 opcional por cliente; requerida para integraciones que la habiliten |
| Idempotencia | `event_id`, estable durante todos los reintentos |
| Entrega | Al menos una vez; el receptor debe ser idempotente |
| Timeout del emisor | 5 segundos de conexion y 15 segundos totales por default |
| Redirecciones | No se siguen redirecciones HTTP |
| ACK recomendado | HTTP 200 con JSON `{"code":"success"}` |
| Reintentos de negocio | Hasta 8 intentos |
| Limite del emisor | 25 solicitudes/minuto por host por default; maximo configurable de 30 |
| Limite requerido en receptor acordado | 30 solicitudes/minuto por IP |

La URL concreta la define la plataforma receptora, por ejemplo:

```text
POST https://integracion.ejemplo.com/api/webhooks/soportetech/v1
```

La URL configurada debe ser final. Como el emisor no sigue redirecciones, no debe responder con `301`, `302`, `307` o `308` hacia otra ruta.

## 3. Envelope `soportetech_v1`

Cada solicitud contiene un objeto JSON con esta estructura:

```json
{
  "event_id": "f8756d5e-1517-4d04-b552-78d2ca02dc97",
  "event_type": "payment_link.payment.approved",
  "occurred_at": "2026-07-14T10:25:38-07:00",
  "source": "webhook",
  "data": {
    "reference": "000023000000001",
    "response": "approved",
    "amount": 1500.0
  }
}
```

### 3.1 Campos del envelope

| Campo | Tipo | Obligatorio | Descripcion |
| --- | --- | --- | --- |
| `event_id` | string UUID | Si | Identificador estable e idempotente del evento. Tambien se envia en un header. |
| `event_type` | string | Si | Nombre exacto del evento de negocio. Tambien se envia en un header. |
| `occurred_at` | string ISO-8601 | Si | Fecha y hora del evento con offset de zona horaria. |
| `source` | string o null | Si | Contexto que genero el evento, por ejemplo `webhook`, `manual` o `spei`. |
| `data` | objeto JSON | Si | Payload especifico del evento. Puede contener campos adicionales o valores nulos. |

Consideraciones importantes:

- El nombre del modo es `soportetech_v1`, pero el envelope vigente no incluye un campo `version`.
- `occurred_at` debe analizarse como ISO-8601 respetando su offset. No debe confundirse con el timestamp Unix usado por HMAC.
- `event_id` y el cuerpo HTTP exacto permanecen iguales durante los reintentos de una entrega.
- El timestamp HMAC y la firma se generan nuevamente en cada intento.
- No se garantiza el orden de llegada entre eventos distintos. Las colas y reintentos pueden cambiarlo.
- `data` conserva el payload real del flujo origen. No existe un esquema normalizado unico para todas las familias.
- El receptor debe aceptar campos desconocidos para mantener compatibilidad con adiciones futuras.
- No debe depender del orden de las propiedades JSON.

## 4. Headers HTTP

### 4.1 Headers enviados siempre

```text
Accept: application/json
Content-Type: application/json
X-Soportetech-Event-Id: <event_id>
X-Soportetech-Event-Type: <event_type>
```

### 4.2 Headers enviados cuando HMAC esta habilitado

```text
X-Soportetech-Timestamp: <Unix timestamp UTC en segundos>
X-Soportetech-Signature: sha256=<64 caracteres hexadecimales en minuscula>
```

El receptor debe comprobar que:

- `X-Soportetech-Event-Id` coincide exactamente con `event_id` del body;
- `X-Soportetech-Event-Type` coincide exactamente con `event_type` del body;
- los headers HMAC existen cuando la integracion exige firma;
- no existen varios valores ambiguos para un mismo header de seguridad.

Los nombres de headers HTTP no distinguen mayusculas de minusculas, pero sus valores si deben compararse exactamente.

## 5. Firma HMAC-SHA256

### 5.1 Cadena canonica

La firma se calcula sobre los bytes exactos recibidos en el body:

```text
{timestamp}.{event_id}.{raw_request_body}
```

Formula:

```text
sha256=hex_lower(HMAC-SHA256(shared_secret, canonical_string))
```

Ejemplo conceptual:

```text
timestamp = 1784049938
event_id = f8756d5e-1517-4d04-b552-78d2ca02dc97
raw_body = {"event_id":"f8756d5e-1517-4d04-b552-78d2ca02dc97",...}

canonical = 1784049938.f8756d5e-1517-4d04-b552-78d2ca02dc97.{"event_id":"f8756d5e-1517-4d04-b552-78d2ca02dc97",...}
```

No se debe volver a serializar el JSON antes de verificar la firma. Cambiar espacios, escapes, orden de propiedades o representacion numerica cambia la firma.

### 5.2 Vector de prueba determinista

Este vector usa un secreto exclusivamente ficticio. Permite validar que otra implementacion produzca la misma firma.

```text
shared_secret = shared-secret-with-at-least-32-characters
timestamp = 1784049938
event_id = f8756d5e-1517-4d04-b552-78d2ca02dc97
raw_request_body = {"event_id":"f8756d5e-1517-4d04-b552-78d2ca02dc97","event_type":"payment_link.payment.approved","occurred_at":"2026-07-14T10:25:38-07:00","source":"webhook","data":{"reference":"000023000000001","response":"approved","amount":1500.0}}
```

Firma esperada:

```text
sha256=99b81bca89915dab7ec30cb966b57b4893261d0d4bbd4f65527b897bbb40310a
```

### 5.3 Orden obligatorio de validacion

1. Leer y conservar el body como bytes o string crudo.
2. Obtener los cuatro headers `X-Soportetech-*`.
3. Rechazar headers faltantes, duplicados o con formato invalido.
4. Validar que el timestamp sea un entero Unix en segundos.
5. Validar una diferencia maxima predeterminada de 300 segundos respecto al reloj UTC del receptor.
6. Construir la cadena canonica con el body crudo, sin transformarlo.
7. Calcular HMAC-SHA256 con el secreto compartido.
8. Comparar firma recibida y calculada con una funcion de tiempo constante.
9. Solo despues de autenticar, decodificar y validar el JSON.
10. Comparar los headers de evento contra los campos del envelope.
11. Aplicar idempotencia y anti-replay antes de ejecutar efectos de negocio.

### 5.4 Ejemplo PHP

```php
<?php

function verifySoportetechSignature(
    string $rawBody,
    string $timestampHeader,
    string $eventIdHeader,
    string $signatureHeader,
    string $sharedSecret,
    int $toleranceSeconds = 300
): bool {
    if (!ctype_digit($timestampHeader)) {
        return false;
    }

    $timestamp = (int) $timestampHeader;
    if (abs(time() - $timestamp) > $toleranceSeconds) {
        return false;
    }

    if (!preg_match('/^sha256=[0-9a-f]{64}$/', $signatureHeader)) {
        return false;
    }

    $canonical = $timestampHeader . '.' . $eventIdHeader . '.' . $rawBody;
    $expected = 'sha256=' . hash_hmac('sha256', $canonical, $sharedSecret);

    return hash_equals($expected, $signatureHeader);
}
```

### 5.5 Ejemplo Node.js

```javascript
import crypto from 'node:crypto';

export function verifySoportetechSignature({
  rawBody,
  timestamp,
  eventId,
  signature,
  sharedSecret,
  toleranceSeconds = 300,
}) {
  if (!/^\d+$/.test(timestamp)) return false;

  const age = Math.abs(Math.floor(Date.now() / 1000) - Number(timestamp));
  if (!Number.isSafeInteger(Number(timestamp)) || age > toleranceSeconds) return false;
  if (!/^sha256=[0-9a-f]{64}$/.test(signature)) return false;

  const canonical = `${timestamp}.${eventId}.${rawBody}`;
  const expected = `sha256=${crypto
    .createHmac('sha256', sharedSecret)
    .update(canonical, 'utf8')
    .digest('hex')}`;

  return crypto.timingSafeEqual(
    Buffer.from(expected, 'ascii'),
    Buffer.from(signature, 'ascii'),
  );
}
```

En Express, `rawBody` debe capturarse antes de que `express.json()` transforme el contenido. En Laravel se debe usar `$request->getContent()`.

## 6. Idempotencia y proteccion anti-replay

### 6.1 Garantia del emisor

El motor entrega eventos con semantica de al menos una vez. La misma entrega puede llegar varias veces por timeout, respuesta no confirmada, `429`, error `5xx` o reintento administrativo.

Durante esos reintentos:

- `event_id` permanece estable;
- `event_type` permanece estable;
- el body permanece estable byte por byte;
- el timestamp y la firma cambian en cada intento firmado.

### 6.2 Regla del receptor

El receptor debe tener una restriccion unica duradera sobre `event_id`. Verificar si existe y luego insertar en dos operaciones separadas no es suficiente, porque dos solicitudes concurrentes pueden pasar la comprobacion. Debe usarse un `INSERT` atomico o una transaccion respaldada por `UNIQUE(event_id)`.

Comportamiento recomendado:

| Estado local del `event_id` | Respuesta recomendada | Accion |
| --- | --- | --- |
| No existe | 200 o 202 despues de persistirlo | Registrar y encolar procesamiento. |
| Ya procesado | 200 | No repetir efectos; responder como duplicado exitoso. |
| Recibido/encolado | 200 o 202 | No volver a encolar si ya existe trabajo durable. |
| Fallo interno recuperable antes de persistir | 500 | Permitir que Soportetech reintente. |

Nunca debe devolverse `409` solo porque `event_id` ya existe. Eso seria interpretado como fallo no reintentable, aunque el duplicado sea normal en un contrato de al menos una vez.

### 6.3 Anti-replay de 10 minutos

Ademas de la idempotencia durable:

- conservar durante 10 minutos una huella de cada solicitud autenticada, por ejemplo `event_id + timestamp + signature`;
- si reaparece exactamente la misma solicitud, no ejecutar negocio otra vez;
- devolver ACK exitoso si el evento ya fue almacenado o procesado;
- no confundir un reintento legitimo, que tiene timestamp y firma nuevos, con un evento nuevo;
- mantener sincronizado el reloj del servidor receptor mediante NTP.

La tolerancia de firma de 300 segundos limita la edad aceptable de la solicitud. La ventana anti-replay de 10 minutos conserva evidencia suficiente para detectar repeticiones dentro de la operacion normal.

### 6.4 Modelo de persistencia recomendado

```sql
CREATE TABLE received_webhook_events (
    event_id          VARCHAR(36) PRIMARY KEY,
    event_type        VARCHAR(100) NOT NULL,
    occurred_at       TIMESTAMP NULL,
    source            VARCHAR(40) NULL,
    raw_body          TEXT NOT NULL,
    body_sha256       CHAR(64) NOT NULL,
    status            VARCHAR(20) NOT NULL,
    received_at       TIMESTAMP NOT NULL,
    processing_at     TIMESTAMP NULL,
    processed_at      TIMESTAMP NULL,
    last_error        TEXT NULL,
    created_at        TIMESTAMP NOT NULL,
    updated_at        TIMESTAMP NOT NULL
);
```

Adapte tipos y cifrado al motor de base de datos. Si `raw_body` contiene datos financieros o de tarjeta protegida, debe cifrarse o aplicarse la politica de minimizacion y retencion aprobada por la plataforma receptora.

## 7. Catalogo completo de eventos

| Evento | `source` actual | Se dispara cuando |
| --- | --- | --- |
| `payment_link.payment.approved` | `webhook` | Se recibe una respuesta `approved` para una liga unica (`transacciones.tipo=1`). |
| `payment_link.payment.rejected` | `webhook` | Se recibe una respuesta distinta de `approved` para una liga unica. |
| `domiciliation_link.payment.approved` | `webhook` | Se recibe una respuesta `approved` para una liga de domiciliacion (`tipo=2`). |
| `domiciliation_link.payment.rejected` | `webhook` | Se recibe una respuesta distinta de `approved` para una liga de domiciliacion. |
| `domiciliation.activated` | `webhook` | La respuesta de domiciliacion es `approved` y contiene `number_tkn`. |
| `domiciliation.activation_failed` | `webhook` | La respuesta es `approved`, pero `number_tkn` esta vacio o ausente. |
| `domiciliation.cancelled` | `cancellation` | La cancelacion quedo persistida localmente en Centro de Cobros. |
| `domiciliation.cancellation_failed` | `cancellation` | Falta token, no hay respuesta valida o no se pudo persistir la cancelacion. |
| `recurring_charge.approved` | `manual`, `api` o `automatic` | El cargo tiene `code=00` y `status=approved`. |
| `recurring_charge.rejected` | `manual`, `api` o `automatic` | El cargo no fue aprobado y conserva codigo y mensaje utilizables. |
| `recurring_charge.error` | `manual`, `api` o `automatic` | El registro de cargo no contiene codigo o mensaje util. |
| `spei.payment.approved` | `spei` | El pago SPEI persistido tiene codigo `0`/`00` y condicion aceptada. |
| `spei.payment.rejected` | `spei` | El pago SPEI fue rechazado por formato, referencia, vigencia, monto u otra regla. |
| `spei.payment.cancelled` | `spei` | La cancelacion SPEI persistida tiene codigo `0`/`00`. |
| `spei.payment.cancellation_rejected` | `spei` | La cancelacion SPEI persistida fue rechazada. |
| `terminal.payment.approved` | `terminal` | Se recibe una respuesta `approved` para una liga de terminal (`tipo=4`). |
| `terminal.payment.rejected` | `terminal` | Se recibe una respuesta distinta de `approved` para una liga de terminal. |
| `terminal.cancelled` | `cancellation` | La cancelacion de terminal quedo persistida localmente. |
| `terminal.cancellation_failed` | `cancellation` | No hubo respuesta valida o no se pudo persistir la cancelacion de terminal. |
| `webhook.endpoint.test` | `test` | Un administrador ejecuta la prueba manual del endpoint. |

`webhook.endpoint.test` no es una suscripcion. Se envia directamente al endpoint seleccionado para verificar conectividad, ACK y HMAC.

Los cargos recurrentes pueden suscribirse por origen: `all`, `manual`, `api` o `automatic`. Los demas eventos se configuran con origen `all`.

## 8. Payload `data` por familia

### 8.1 Regla general

`data` transmite la informacion completa recibida o producida por el flujo origen. La sanitizacion usada por las bitacoras administrativas no modifica el body realmente enviado.

Consecuencias para el receptor:

- debe tratar el payload como informacion sensible;
- no debe escribir datos completos de tarjeta, tokens, secretos o respuestas crudas en logs de aplicacion;
- debe tolerar propiedades adicionales;
- debe validar solo los campos que necesita para su operacion;
- no debe asumir un tipo numerico global para montos;
- debe interpretar nombres, unidades y casing segun la familia del evento.

Los ejemplos siguientes son representativos y usan datos ficticios. Un campo marcado como opcional puede no existir o llegar como `null` o string vacio.

### 8.2 Liga de pago unica

Eventos:

- `payment_link.payment.approved`
- `payment_link.payment.rejected`

Campos minimos que Centro de Cobros exige al webhook origen antes de crear la respuesta:

| Campo | Descripcion |
| --- | --- |
| `reference` | Referencia usada para localizar `transacciones.responseReference`. |
| `response` | Resultado recibido, normalmente `approved`, `denied` o `error`. |
| `amount` | Monto informado por el proveedor. |

Campos observados y opcionales:

`foliocpagos`, `auth`, `cd_response`, `cd_error`, `nb_error`, `time`, `date`, `nb_company`, `nb_merchant`, `cc_type`, `tp_operation`, `cc_name`, `cc_number`, `cc_expmonth`, `cc_expyear`, `id_url`, `email`, `payment_type`, `promocion`, `number_tkn` y `cc_mask`.

Ejemplo:

```json
{
  "event_id": "ac50ba15-c813-4c69-83f8-f3df3395c1ba",
  "event_type": "payment_link.payment.approved",
  "occurred_at": "2026-07-14T09:40:17-07:00",
  "source": "webhook",
  "data": {
    "reference": "000023000000001",
    "response": "approved",
    "amount": 1500.0,
    "foliocpagos": "OP-100245",
    "auth": "12345678",
    "cc_type": "VISA",
    "cc_number": "411111******1111",
    "cc_expmonth": "12",
    "cc_expyear": "28",
    "email": "cliente@example.test"
  }
}
```

### 8.3 Liga de domiciliacion y activacion

Eventos:

- `domiciliation_link.payment.approved`
- `domiciliation_link.payment.rejected`
- `domiciliation.activated`
- `domiciliation.activation_failed`

Usan el mismo formato de `data` que la respuesta de liga de pago. El campo determinante para la activacion es:

| Campo | Uso |
| --- | --- |
| `number_tkn` | Token de domiciliacion. No vacio genera `domiciliation.activated`; vacio genera `domiciliation.activation_failed`. |

Una respuesta aprobada de domiciliacion produce dos eventos independientes:

1. `domiciliation_link.payment.approved`.
2. `domiciliation.activated` o `domiciliation.activation_failed`.

Cada evento tiene un `event_id` distinto. El receptor debe procesar ambos por su significado y no descartarlos por compartir referencia o datos.

Ejemplo de activacion:

```json
{
  "event_id": "141e17e7-d4d5-47a0-a771-02ad117c3dcc",
  "event_type": "domiciliation.activated",
  "occurred_at": "2026-07-14T10:05:11-07:00",
  "source": "webhook",
  "data": {
    "reference": "DOM-00000000001",
    "response": "approved",
    "amount": 500.0,
    "number_tkn": "TOKEN-PROTEGIDO-RECIBIDO",
    "foliocpagos": "OP-100246",
    "auth": "87654321"
  }
}
```

`domiciliation.activation_failed` puede contener `response=approved`; su significado es que el pago fue aprobado, pero no se obtuvo un token util para activar la domiciliacion.

### 8.4 Pago con terminal

Eventos:

- `terminal.payment.approved`
- `terminal.payment.rejected`

Los campos minimos siguen siendo `reference`, `response` y `amount`. El proveedor usa nombres con casing distinto para varios campos opcionales, por ejemplo:

`folio`, `auth`, `responseCode`, `errorCode`, `errorDescription`, `time`, `date`, `company`, `merchantName`, `ccType`, `operationType`, `ccName`, `ccNumber`, `ccExpMonth` y `ccExpYear`.

Ejemplo:

```json
{
  "event_id": "6f469f56-7db8-4fde-b09f-63fcda1f110b",
  "event_type": "terminal.payment.rejected",
  "occurred_at": "2026-07-14T10:18:04-07:00",
  "source": "terminal",
  "data": {
    "reference": "TERM-000000001",
    "response": "denied",
    "amount": 850.0,
    "folio": "TERM-OP-23",
    "responseCode": "05",
    "errorCode": "DECLINED",
    "errorDescription": "Operacion denegada",
    "ccType": "VISA",
    "ccNumber": "411111******1111"
  }
}
```

El receptor debe conservar el casing original. No debe esperar que `ccType` llegue tambien como `cc_type`.

### 8.5 Cargos recurrentes

Eventos:

- `recurring_charge.approved`
- `recurring_charge.rejected`
- `recurring_charge.error`

Origenes posibles:

| `source` | Significado |
| --- | --- |
| `manual` | Cargo solicitado manualmente desde la plataforma. |
| `api` | Cargo solicitado mediante la API de Centro de Cobros. |
| `automatic` | Cargo ejecutado por el proceso automatico programado. |

Cuando la respuesta cruda del proveedor contiene JSON valido, ese objeto se envia directamente como `data`. Si no es JSON valido, se usa un payload de respaldo construido con el registro local.

Campos que puede contener el payload de respaldo:

`folio`, `idtransaccion`, `Reference`, `ClientReference`, `monto`, `Amount`, `ExpMonth`, `ExpYear`, `response`, `code`, `message`, `response_reference`, `status`, `foliocpagos`, `auth`, `cd_response`, `cd_error`, `nb_error`, `time`, `date`, `nb_company`, `nb_merchant`, `nb_street`, `tp_operation`, `cc_type`, `cc_name`, `cc_number`, `cc_expmonth`, `cc_expyear`, `amount`, `id_url`, `email` y `payment_type`.

Ejemplo representativo:

```json
{
  "event_id": "df098fa1-9710-4654-a5f7-b591d0257e6c",
  "event_type": "recurring_charge.approved",
  "occurred_at": "2026-07-14T00:12:46-07:00",
  "source": "automatic",
  "data": {
    "code": "00",
    "message": "Operacion exitosa",
    "status": "approved",
    "amount": 300.0,
    "reference": "REC-000000001",
    "foliocpagos": "OP-100247",
    "auth": "23456789",
    "cc_number": "411111******1111"
  }
}
```

No debe asumirse que `data` siempre incluira todos los campos del payload de respaldo. La respuesta JSON real del proveedor tiene prioridad.

### 8.6 Recepcion de pago SPEI

Eventos:

- `spei.payment.approved`
- `spei.payment.rejected`

El `data` parte del request SPEI recibido y agrega el resultado persistido. Los campos son:

| Campo | Presencia | Descripcion |
| --- | --- | --- |
| `clabe` | Requerido | CLABE/referencia SPEI procesada. |
| `monto` | Requerido | Monto numerico recibido. Su unidad conserva el contrato SPEI origen. |
| `fecha` | Requerido | Fecha informada en la solicitud SPEI. |
| `transaccion` | Requerido | Identificador de operacion del emisor SPEI. |
| `codigo` | Agregado | Codigo final persistido por Centro de Cobros. |
| `autorizacion` | Agregado | Numero de autorizacion o `0`. |
| `mensaje` | Agregado | Descripcion del resultado. |

Ejemplo aprobado:

```json
{
  "event_id": "a39a4c55-fd40-4a87-a4ac-da691a524489",
  "event_type": "spei.payment.approved",
  "occurred_at": "2026-07-14T11:00:03-07:00",
  "source": "spei",
  "data": {
    "clabe": "646180000000000001",
    "monto": 25000,
    "fecha": "2026-07-14T10:59:58-07:00",
    "transaccion": "SPEI-20260714-0001",
    "codigo": "0",
    "autorizacion": "34567890",
    "mensaje": "Operacion exitosa"
  }
}
```

Codigos de rechazo observados en el flujo actual incluyen:

| Codigo | Significado actual |
| --- | --- |
| `13` | Referencia sin adeudo. |
| `14` | Referencia fuera de vigencia. |
| `15` | Referencia con error de formato. |
| `30` | Monto invalido. |
| `40` | Adquiriente invalido o referencia no encontrada. |
| `50` | Error de sistema. |

El receptor debe usar `event_type` como clasificacion principal y conservar `codigo`/`mensaje` para diagnostico. Los catalogos de codigo pueden ampliarse.

### 8.7 Cancelacion de pago SPEI

Eventos:

- `spei.payment.cancelled`
- `spei.payment.cancellation_rejected`

Campos actuales:

`clabe`, `fecha`, `monto`, `transaccion`, `autorizacion`, `codigo` y `mensaje`.

Ejemplo:

```json
{
  "event_id": "c45db006-f4e7-4e9a-81c7-478f8e2357dd",
  "event_type": "spei.payment.cancelled",
  "occurred_at": "2026-07-14T11:08:22-07:00",
  "source": "spei",
  "data": {
    "clabe": "646180000000000001",
    "fecha": "2026-07-14T11:08:20-07:00",
    "monto": 25000,
    "transaccion": "SPEI-20260714-0001",
    "autorizacion": "34567890",
    "codigo": "0",
    "mensaje": "Cancelacion exitosa"
  }
}
```

Codigos de rechazo observados incluyen `13`, `14`, `15`, `40`, `50` y `60`. El codigo `60` representa una cancelacion fuera de periodo en el flujo actual.

### 8.8 Cancelacion de domiciliacion

Eventos:

- `domiciliation.cancelled`
- `domiciliation.cancellation_failed`

Payload exitoso representativo:

```json
{
  "event_id": "5864391a-9169-467f-862f-84cd37c11aa5",
  "event_type": "domiciliation.cancelled",
  "occurred_at": "2026-07-14T11:20:00-07:00",
  "source": "cancellation",
  "data": {
    "ClientReference": "CLIENTE-DOM-001",
    "Token": "TOKEN-PROTEGIDO-RECIBIDO",
    "Tkn_reference": "000000000000124",
    "code": "success",
    "message": "Cancelacion realizada",
    "response": "{\"code\":\"success\",\"message\":\"Cancelacion realizada\"}"
  }
}
```

Un fallo puede incluir solo:

- `ClientReference`;
- `code`;
- `message`;
- opcionalmente `Token` y `Tkn_reference` si ya estaban disponibles.

Valores locales posibles de `code` incluyen `missing_token`, `invalid_provider_response` y `persistence_error`, ademas de codigos devueltos por el proveedor.

`domiciliation.cancelled` significa que la cancelacion quedo persistida por Centro de Cobros. El campo `response` puede contener la respuesta cruda del proveedor como string JSON.

### 8.9 Cancelacion de terminal

Eventos:

- `terminal.cancelled`
- `terminal.cancellation_failed`

Payload exitoso representativo:

```json
{
  "event_id": "a465cad7-18d9-41b2-a866-5ba45bf9d090",
  "event_type": "terminal.cancelled",
  "occurred_at": "2026-07-14T11:25:00-07:00",
  "source": "cancellation",
  "data": {
    "ClientReference": "CLIENTE-TERM-001",
    "Reference": "TERM-REF-001",
    "responseReference": "CANCEL-TERM-001",
    "code": "success",
    "message": "Cancelacion realizada",
    "response": "{\"code\":\"success\",\"reference\":\"CANCEL-TERM-001\"}"
  }
}
```

Un fallo incluye `ClientReference`, `Reference`, `code` y `message`. Valores locales de `code` incluyen `invalid_provider_response` y `persistence_error`.

### 8.10 Prueba del endpoint

Evento:

- `webhook.endpoint.test`

Payload:

```json
{
  "event_id": "17dd1d2d-7d83-4e36-9231-b0643a12342c",
  "event_type": "webhook.endpoint.test",
  "occurred_at": "2026-07-14T11:30:00-07:00",
  "source": "test",
  "data": {
    "message": "Prueba de endpoint Centro de Cobros"
  }
}
```

Debe pasar por las mismas verificaciones de HTTPS, HMAC, idempotencia y ACK que un evento financiero. No debe ejecutar efectos financieros.

## 9. Enrutamiento y procesamiento recomendado

El endpoint debe realizar trabajo minimo en la solicitud HTTP. La secuencia recomendada es:

1. Recibir el body crudo y headers.
2. Aplicar limite de tamano antes de procesar el contenido.
3. Verificar HMAC, timestamp y anti-replay.
4. Decodificar JSON con manejo explicito de errores.
5. Validar el envelope y concordancia header/body.
6. Insertar `event_id` de forma atomica.
7. Guardar el evento o un mensaje de cola durable.
8. Confirmar con HTTP 200/202.
9. Procesar el evento en background.
10. Registrar resultado y correlacion por `event_id`.

Pseudocodigo:

```text
raw_body = request.raw_body
security_headers = read_soportetech_headers(request)

if request_is_too_large:
    return 413

if hmac_required and not valid_hmac(raw_body, security_headers):
    return 401

payload = decode_json(raw_body)
if payload is invalid:
    return 400

if header_event_id != payload.event_id:
    return 400

if header_event_type != payload.event_type:
    return 400

result = atomic_insert_event(payload.event_id, raw_body, status="received")

if result == ALREADY_EXISTS:
    return 200 {"code":"success", "event_id": payload.event_id, "duplicate": true}

if not durable_enqueue(payload.event_id):
    rollback_insert_or_mark_failed()
    return 500

return 202 {"code":"success", "event_id": payload.event_id, "duplicate": false}
```

### 9.1 Router de eventos

La aplicacion receptora debe usar una tabla o mapa explicito de handlers:

```text
payment_link.payment.approved              -> PaymentLinkApprovedHandler
payment_link.payment.rejected              -> PaymentLinkRejectedHandler
domiciliation_link.payment.approved        -> DomiciliationPaymentApprovedHandler
domiciliation_link.payment.rejected        -> DomiciliationPaymentRejectedHandler
domiciliation.activated                    -> DomiciliationActivatedHandler
domiciliation.activation_failed            -> DomiciliationActivationFailedHandler
domiciliation.cancelled                    -> DomiciliationCancelledHandler
domiciliation.cancellation_failed          -> DomiciliationCancellationFailedHandler
recurring_charge.approved                  -> RecurringChargeApprovedHandler
recurring_charge.rejected                  -> RecurringChargeRejectedHandler
recurring_charge.error                     -> RecurringChargeErrorHandler
spei.payment.approved                      -> SpeiPaymentApprovedHandler
spei.payment.rejected                      -> SpeiPaymentRejectedHandler
spei.payment.cancelled                     -> SpeiPaymentCancelledHandler
spei.payment.cancellation_rejected         -> SpeiCancellationRejectedHandler
terminal.payment.approved                  -> TerminalPaymentApprovedHandler
terminal.payment.rejected                  -> TerminalPaymentRejectedHandler
terminal.cancelled                         -> TerminalCancelledHandler
terminal.cancellation_failed               -> TerminalCancellationFailedHandler
webhook.endpoint.test                      -> EndpointTestHandler
```

Si llega un tipo desconocido pero autenticado, la recomendacion es persistirlo, marcarlo como `unsupported`, generar una alerta y responder 2xx para evitar una tormenta de reintentos. Esta politica debe revisarse si la plataforma receptora necesita rechazo estricto por contrato.

## 10. Confirmacion o ACK

Centro de Cobros soporta dos modos por endpoint:

| Modo | Confirmacion valida |
| --- | --- |
| `http_2xx` | Cualquier HTTP 200-299. El body es opcional. |
| `legacy_code_success` | HTTP 200-299 y JSON cuyo campo `code` sea `success`, sin distinguir mayusculas/minusculas. |

La respuesta compatible con ambos modos es:

```http
HTTP/1.1 200 OK
Content-Type: application/json

{"code":"success","event_id":"f8756d5e-1517-4d04-b552-78d2ca02dc97","duplicate":false}
```

Para un duplicado ya persistido o procesado:

```http
HTTP/1.1 200 OK
Content-Type: application/json

{"code":"success","event_id":"f8756d5e-1517-4d04-b552-78d2ca02dc97","duplicate":true}
```

No es necesario devolver datos financieros en el ACK.

### 10.1 Codigos HTTP recomendados

| HTTP | Uso recomendado | Comportamiento del emisor |
| ---: | --- | --- |
| 200/201/202/204 | Evento confirmado segun modo ACK. | Entrega finalizada. |
| 400 | JSON o envelope invalido. | No reintentable. |
| 401/403 | Firma ausente/invalida o acceso no autorizado. | No reintentable. |
| 413 | Body demasiado grande. | No reintentable. |
| 422 | Evento semanticamente no aceptado. | No reintentable. |
| 408 | Timeout del receptor. | Reintentable. |
| 425 | Solicitud demasiado temprana. | Reintentable. |
| 429 | Limite temporal alcanzado. | Reintentable; se respeta `Retry-After`. |
| 500-599 | Fallo temporal del receptor. | Reintentable. |

Un `2xx` sin `{"code":"success"}` no confirma cuando el endpoint esta configurado como `legacy_code_success`.

## 11. Reintentos y tiempos

Si una entrega no se confirma y el error es reintentable, el calendario normal es:

| Fallo despues del intento | Espera antes del siguiente intento |
| ---: | ---: |
| 1 | 60 segundos |
| 2 | 5 minutos |
| 3 | 15 minutos |
| 4 | 1 hora |
| 5 | 3 horas |
| 6 | 6 horas |
| 7 | 12 horas |

El octavo intento es el ultimo. Despues queda en estado `dead` si no fue confirmado.

Se consideran reintentables:

- error de red;
- conexion sin respuesta;
- HTTP `408`, `425` o `429`;
- HTTP `5xx`;
- respuesta `2xx` sin `code=success` cuando se usa ACK legacy.

Otros errores `4xx` son permanentes y no se reintentan automaticamente.

Si el receptor envia `Retry-After`, Centro de Cobros lo respeta como segundos enteros o fecha HTTP, con un maximo de 24 horas. El receptor debe usarlo especialmente en respuestas `429` o mantenimiento temporal.

## 12. Limites, concurrencia y orden

- El emisor limita por host, no solamente por ruta.
- El valor default es 25 solicitudes por minuto por host y el maximo configurable es 30.
- El receptor acordado debe limitar a 30 solicitudes por minuto por IP.
- Si existe proxy o balanceador, solo se debe confiar en `X-Forwarded-For` desde proxies conocidos.
- Un limite excedido debe responder `429` con `Retry-After`.
- Distintos eventos pueden procesarse concurrentemente.
- No existe numero de secuencia por cliente o transaccion.
- Si el negocio requiere orden, debe serializar por una clave propia como referencia, CLABE o identificador de domiciliacion despues de persistir el evento.

## 13. Seguridad obligatoria

### 13.1 Transporte y autenticacion

- Exponer exclusivamente HTTPS con certificado valido.
- Deshabilitar HTTP o redirigirlo fuera de la URL configurada; Centro de Cobros solo debe apuntar directamente a HTTPS.
- Habilitar HMAC-SHA256 para integraciones que requieran autenticacion, incluida la integracion acordada con `app.donarconcausa.org.mx`.
- Guardar el secreto en un vault o variable de entorno protegida.
- No incluir el secreto en codigo fuente, logs, respuestas HTTP ni tickets.
- Usar al menos 32 bytes aleatorios para un secreto nuevo.
- Comparar firmas en tiempo constante.
- Sincronizar reloj por NTP.

### 13.2 Datos sensibles

El body puede contener:

- nombre asociado al medio de pago;
- numero de tarjeta ya protegido por el sistema origen;
- mes y ano de expiracion;
- token de domiciliacion;
- CLABE;
- email;
- respuesta cruda de cargos o cancelaciones.

La plataforma receptora debe:

- transmitir y almacenar conforme a sus politicas de seguridad y cumplimiento;
- limitar acceso por rol;
- cifrar informacion sensible en reposo cuando se conserve;
- evitar body completo en logs, APM, trazas y mensajes de error;
- definir retencion y eliminacion segura;
- enmascarar datos en herramientas de soporte;
- no devolver el payload recibido en el ACK.

### 13.3 Protecciones adicionales recomendadas

- Limite de body apropiado al contrato, por ejemplo 256 KB, ajustable con evidencia.
- Timeout interno para dependencias del receptor.
- Cola durable para desacoplar el endpoint.
- Circuit breaker para servicios internos secundarios.
- Alertas por firmas invalidas, aumento de `5xx`, cola atrasada y eventos sin handler.
- Restriccion de metodos para aceptar solo `POST`.
- `Content-Type` estricto con soporte de charset JSON si se requiere.
- No usar allowlist IP como unico control, porque las IP de salida pueden cambiar; HMAC es el control de autenticidad principal.

## 14. Rotacion del secreto HMAC

No existe actualmente un header `key-id`. Para rotar sin interrupcion:

1. Generar un secreto nuevo de al menos 32 bytes aleatorios.
2. Configurar temporalmente el receptor para intentar verificacion con el secreto nuevo y el anterior.
3. Actualizar el secreto en Centro de Cobros mediante el modulo administrativo.
4. Ejecutar `webhook.endpoint.test`.
5. Confirmar firma, ACK y registro por `event_id`.
6. Mantener el secreto anterior solo durante la ventana operativa acordada y mientras no existan solicitudes en vuelo firmadas con el anterior.
7. Retirar y destruir el secreto anterior.

La rotacion no cambia `event_id`. Los reintentos se firman con el secreto vigente al momento de cada intento.

## 15. Observabilidad del receptor

Registrar como minimo:

- `event_id`;
- `event_type`;
- `source`;
- `occurred_at`;
- hora de recepcion;
- hash SHA-256 del body;
- resultado de HMAC sin guardar firma completa si no es necesario;
- resultado de idempotencia (`new`/`duplicate`);
- estado de procesamiento;
- duracion;
- codigo HTTP devuelto;
- error interno sanitizado.

No registrar:

- secreto HMAC;
- header de firma completo en logs generales;
- body financiero completo;
- tokens o datos de tarjeta sin proteccion;
- stack traces con payload embebido.

`event_id` debe ser la correlacion principal entre Soportetech y la plataforma receptora durante soporte y conciliacion.

Metricas recomendadas:

- solicitudes recibidas por `event_type`;
- firmas invalidas;
- duplicados;
- latencia de ACK;
- procesamiento exitoso/fallido;
- antiguedad y profundidad de cola;
- respuestas `4xx`, `429` y `5xx`;
- eventos `unsupported`.

## 16. Estrategia de errores del procesamiento interno

El ACK debe representar que el evento quedo almacenado de forma durable, no necesariamente que terminaron todos los efectos secundarios.

Recomendacion:

1. Persistir evento y encolar handler dentro de una operacion consistente.
2. Devolver ACK exitoso.
3. Reintentar internamente el handler si falla una dependencia de la plataforma receptora.
4. Mover a una dead-letter queue despues del limite local.
5. Alertar y permitir reejecucion administrativa usando el mismo `event_id`.

Si el evento no pudo persistirse o encolarse de forma durable, devolver `500` para solicitar reintento al emisor.

## 17. Pruebas obligatorias antes de produccion

### 17.1 Contrato HTTP

- Acepta `POST` JSON por HTTPS.
- Rechaza metodos no permitidos.
- No depende de redirecciones.
- Responde en menos de 15 segundos; objetivo recomendado menor a 2 segundos.
- Devuelve `{"code":"success"}` en ACK para ser compatible con ambos modos.

### 17.2 HMAC

- Firma valida con body exacto.
- Firma invalida con secreto incorrecto.
- Firma invalida si cambia un byte, espacio o numero del body.
- Firma invalida si cambia `event_id` o timestamp.
- Rechazo de timestamp con mas de 300 segundos de diferencia.
- Rechazo de timestamp demasiado futuro.
- Comparacion de tiempo constante.
- Concordancia de `event_id` y `event_type` entre headers y body.
- Soporte correcto de Unicode, slashes no escapados y numeros como `1500.0`.

### 17.3 Idempotencia y concurrencia

- Dos solicitudes secuenciales con el mismo `event_id` producen un solo efecto.
- Dos solicitudes simultaneas con el mismo `event_id` producen un solo efecto.
- Un duplicado procesado responde 2xx.
- Un reintento con timestamp/firma nuevos no crea otro efecto.
- Un fallo antes de persistir responde 500 y permite recuperacion.

### 17.4 Eventos

- Existe un handler o tratamiento explicito para los 20 eventos del catalogo.
- La domiciliacion aprobada procesa tanto el pago como la activacion/fallo de activacion.
- Los cargos recurrentes distinguen `manual`, `api` y `automatic`.
- Los rechazos SPEI conservan codigo y mensaje.
- Los eventos de cancelacion no se confunden con pagos rechazados.
- `webhook.endpoint.test` no genera efectos financieros.
- Campos desconocidos no rompen el parser.
- Campos opcionales ausentes o nulos no causan error no controlado.

### 17.5 ACK y reintentos

- `200 {"code":"success"}` confirma.
- `2xx` sin `code=success` se prueba si se elegira modo legacy.
- `429` incluye `Retry-After`.
- `500` temporal se recupera en un intento posterior.
- Duplicados por timeout retornan exito.

### 17.6 Seguridad y operacion

- El secreto no aparece en logs.
- Los payloads sensibles estan cifrados o minimizados.
- El rate limit es 30/minuto/IP para la integracion acordada.
- El proxy real entrega la IP correcta mediante una cadena de confianza configurada.
- Reloj y NTP son correctos.
- Existen alertas y tablero de salud.
- Existe procedimiento de rotacion de secreto.

## 18. Checklist de activacion con Centro de Cobros

1. Publicar una URL HTTPS final y sin redirecciones.
2. Confirmar quien administra el secreto HMAC en cada plataforma.
3. Acordar modo ACK; se recomienda `legacy_code_success` con respuesta compatible.
4. Implementar los 20 tipos de evento o una cuarentena explicita para no soportados.
5. Configurar persistencia unica por `event_id`.
6. Configurar tolerancia de 300 segundos y anti-replay de 10 minutos.
7. Configurar limite receptor de 30 solicitudes/minuto/IP.
8. Probar `webhook.endpoint.test` con HMAC.
9. Probar un evento representativo por cada familia en sandbox.
10. Probar duplicados, concurrencia, `429` y recuperacion de `500`.
11. Verificar que no se registran datos sensibles en texto plano en logs.
12. Confirmar monitoreo, alertas y responsables de soporte.
13. Activar primero un conjunto controlado de suscripciones.
14. Conciliar eventos por `event_id` durante la ventana de observacion.
15. Ampliar suscripciones despues de validar resultados.

## 19. Datos que deben intercambiar ambos equipos

Antes de activar produccion se debe registrar, fuera del repositorio publico:

| Dato | Responsable |
| --- | --- |
| URL HTTPS final | Plataforma receptora |
| Ambientes sandbox/productivo | Ambos equipos |
| Secreto HMAC o mecanismo de entrega segura | Ambos equipos |
| Modo ACK | Ambos equipos |
| Eventos suscritos | Propietario funcional |
| Origenes de cargos recurrentes suscritos | Propietario funcional |
| Contacto tecnico y horario de soporte | Ambos equipos |
| Politica de retencion y tratamiento de datos | Plataforma receptora |
| SLA de ACK y procesamiento | Ambos equipos |
| Procedimiento de rotacion y contingencia | Ambos equipos |

## 20. Notas de compatibilidad

- El contrato es aditivo: pueden aparecer nuevos campos dentro de `data` sin cambiar la version.
- Un cambio incompatible debe introducir un nuevo payload mode/version, no alterar silenciosamente `soportetech_v1`.
- Los nombres de evento se comparan de forma exacta y en minusculas.
- Los nombres de campos dentro de `data` conservan el casing del sistema origen.
- Los montos no deben convertirse globalmente sin conocer la familia y unidad del campo.
- La plataforma receptora no debe inferir exito solo a partir de `code`; debe usar primero `event_type` y despues los detalles de `data`.
- Eventos de fallo previos a persistir una cancelacion pueden representar intentos de negocio distintos y recibir `event_id` diferentes. La idempotencia por `event_id` evita duplicados de entrega, no fusiona intentos funcionales separados.

## 21. Referencia rapida

Solicitud firmada:

```text
POST /api/webhooks/soportetech/v1 HTTP/1.1
Host: integracion.ejemplo.com
Accept: application/json
Content-Type: application/json
X-Soportetech-Event-Id: f8756d5e-1517-4d04-b552-78d2ca02dc97
X-Soportetech-Event-Type: payment_link.payment.approved
X-Soportetech-Timestamp: 1784049938
X-Soportetech-Signature: sha256=<firma hexadecimal>

{"event_id":"f8756d5e-1517-4d04-b552-78d2ca02dc97","event_type":"payment_link.payment.approved","occurred_at":"2026-07-14T10:25:38-07:00","source":"webhook","data":{"reference":"000023000000001","response":"approved","amount":1500.0}}
```

ACK recomendado:

```json
{
  "code": "success",
  "event_id": "f8756d5e-1517-4d04-b552-78d2ca02dc97",
  "duplicate": false
}
```

Cadena firmada:

```text
1784049938.f8756d5e-1517-4d04-b552-78d2ca02dc97.{"event_id":"f8756d5e-1517-4d04-b552-78d2ca02dc97",...}
```

Reglas esenciales:

- usar body crudo;
- tolerancia 300 segundos;
- anti-replay 10 minutos;
- `UNIQUE(event_id)`;
- duplicado devuelve exito sin repetir negocio;
- ACK durable en menos de 15 segundos;
- 30 solicitudes/minuto/IP;
- no registrar secretos ni payload financiero completo.
