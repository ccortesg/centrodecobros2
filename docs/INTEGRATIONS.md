# Integraciones externas

Ultima actualizacion: 2026-07-10

## 1. Pagadetodo

### Configuracion

La configuracion vive en `config/services.php` bajo `services.pagadetodo` y se alimenta desde `.env`.

Variables principales:

- `PAGADETODO_MOCK`
- `PAGADETODO_URL_GENERAR_LIGA`
- `PAGADETODO_URL_GENERAR_DOMICILIACION`
- `PAGADETODO_URL_CANCELAR_DOMICILIACION`
- `PAGADETODO_URL_GENERAR_SPEI`
- `PAGADETODO_URL_GENERAR_LECTOR`
- `PAGADETODO_URL_CANCELAR_LECTOR`
- `PAGADETODO_URL_CARGO_DOMICILIACION`
- `PAGADETODO_USER`, `PAGADETODO_PASSWORD`
- `PAGADETODO_DOM_USER`, `PAGADETODO_DOM_PASSWORD`
- `PAGADETODO_DOM_BA_USER`, `PAGADETODO_DOM_BA_PASSWORD`
- `PAGADETODO_*_INTEGRATION_ID`, `PAGADETODO_*_BUSINESS_ID`

Los valores reales no deben versionarse. En ambiente local usar `PAGADETODO_MOCK=true`; Pagadetodo restringe llamadas reales por IP address de origen y no se puede validar localmente.

### Endpoints externos usados

- `GenerarLigaIndi`
- `GenerarLigaDomiciliacionIndi`
- `CancelarDomiciliacionIndi`
- `GenerarClabeIndi`
- `GenerarPagoLectorIndi`
- `CancelarReferenciaLectorIndi`
- `PagarDomiciliacionIndi`

### Endpoints propios legacy

Estas rutas no usan prefijo `/api`:

- `POST GenerarLigaPago`
- `POST GenerarLigaDomiciliacion`
- `POST CargoDomiciliacion`
- `POST CancelarDomiciliacion`
- `POST GenerarSpei`
- `POST GenerarLigaLector`
- `POST Service/EntregarPagoLiga`
- `POST Service/EntregarPagoLigaToken`
- `POST Service/EntregarPagoLector`
- `GET Service/ConsultaClabe`
- `POST Service/PagoClabe`
- `POST Service/CancelaClabe`

### Estado actual

- Fases 31-33 agregaron validaciones tempranas, ownership y mock controlado.
- Fase 34 agrego validacion minima e idempotencia local en `Service/*`.
- El propietario confirmo el 2026-06-08 que los servicios Pagadetodo ya fueron probados exitosamente desde servidor en sandbox y en productivo.
- La validacion real no es reproducible desde ambiente local por restriccion de IP de origen del proveedor.
- La firma/origen de los webhooks entrantes de Pagadetodo sigue pendiente por falta de especificacion del proveedor. Esto es independiente de la firma HMAC implementada para webhooks salientes hacia sistemas cliente.

## 2. Webhooks salientes a sistemas cliente

- El flujo legacy conserva `users.ligaPago` y `users.ligaRecurrente`.
- El motor configurable vive en `WebhookEventPublisher`, `WebhookFanoutService`, `DeliverWebhookJob` y `WebhookDeliveryService`.
- Modos por cliente: `legacy`, `shadow`, `active`, `disabled`.
- `shadow` conserva el callback legacy y genera entregas de simulacion sin hacer una segunda llamada HTTP.
- `active` reemplaza el callback legacy por Database Queue.
- Se cubren pagos/rechazos de liga unica, domiciliacion, cargos recurrentes manual/API/automaticos, cancelaciones, SPEI y terminal.
- Los payloads reales se transmiten completos; la sanitizacion solo afecta bitacoras/UI/export.
- URL valida y HTTPS son obligatorios. No existe allowlist de host por decision funcional.
- HMAC-SHA256 opcional por cliente usa `timestamp.event_id.raw_request_body` y los headers `X-Soportetech-*`.
- El primer receptor acordado es `app.donarconcausa.org.mx`, con tolerancia de 300 segundos, anti-replay de 10 minutos y maximo receptor de 30 solicitudes/minuto/IP.
- Detalle completo: `docs/MODULES/MODULO_NOTIFICACIONES_WEBHOOK_CONFIGURABLES.md`.

## 3. Realtime Pusher/Echo

### Configuracion

- Backend: `config/broadcasting.php`.
- Frontend: `resources/assets/js/bootstrap.js`.
- Variables frontend: `VITE_PUSHER_APP_KEY`, `VITE_PUSHER_APP_CLUSTER`.

Si `VITE_PUSHER_APP_KEY` no existe, `window.Echo` queda en `null` y el sistema no debe considerarse validado en realtime.

### Estado actual

- Polling HTTP de notificaciones validado.
- Websocket realtime no validado end-to-end con credenciales aisladas.
- La validacion futura debe usar sandbox/app Pusher separada, no credenciales productivas.

## 4. Correo

- SMTP/Postmark configurados por `.env`.
- `app/Notifications/TransaccionValidada.php` existe como notificacion relevante.
- No publicar tokens ni credenciales SMTP/Postmark.

## 5. OTP/SMS

- El flujo publico legacy `GET /verify/{id}`, `POST /verifySMS` y `POST /sendSMS` fue retirado en Fase 21.
- `telesign/telesign` permanece como dependencia historica residual; su remocion debe tratarse como carril separado.

## 6. Exportaciones/PDF

- Excel/CSV: `maatwebsite/excel` y exportaciones por streaming segun modulo.
- PDF: `barryvdh/laravel-dompdf` instalado; uso parcial en vistas legacy.

## 7. Reglas para cambios de integracion

1. No cambiar payloads publicos sin evidencia servidor/IP autorizado y sanitizada.
2. No usar credenciales productivas en pruebas automatizadas.
3. Sanitizar request/response en cualquier evidencia.
4. Mantener `PAGADETODO_MOCK=true` en local; ejecutar llamadas reales Pagadetodo solo desde servidor/IP autorizado.
5. Actualizar `docs/MODULES` y pruebas si cambia un contrato.

## 8. Auditoria de integraciones

- `Outgoing API Requests` registra llamadas salientes a Pagadetodo y callbacks a clientes (`users.ligaPago`, `users.ligaRecurrente`).
- `Incoming API Requests` registra llamadas externas del grupo `api` (`routes/api.php`), que conserva rutas legacy sin prefijo `/api`.
- `User Activity Log` registra login exitoso/fallido, logout y acceso a modulos del shell.
- La sanitizacion vive en `App\Services\AuditSanitizer`; los headers/payloads se guardan ya sanitizados.
- La purga es manual con `php artisan audit:purge --days=365 --dry-run`; no se agrego scheduler.
- Las entregas configurables tambien se registran en Outgoing API Requests, pero el cuerpo persistido en esa bitacora se sanitiza; no debe confundirse con el cuerpo real cifrado de `webhook_deliveries`.
