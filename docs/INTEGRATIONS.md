# Integraciones externas

Ultima actualizacion: 2026-06-03

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

Los valores reales no deben versionarse. Si no hay sandbox oficial, usar `PAGADETODO_MOCK=true`.

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
- Sandbox oficial sigue pendiente por falta de URL/credenciales no productivas.
- Firma/origen de webhooks sigue pendiente por falta de especificacion del proveedor.

## 2. Callback a sistemas cliente

- URLs configuradas por usuario, principalmente `users.ligaPago` y `users.ligaRecurrente`.
- Se invocan despues de respuestas aprobadas o eventos relevantes.
- Riesgo vigente: reintentos, timeouts e idempotencia del sistema cliente no estan formalizados como contrato.

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

1. No cambiar payloads publicos sin evidencia de sandbox.
2. No usar credenciales productivas en pruebas automatizadas.
3. Sanitizar request/response en cualquier evidencia.
4. Mantener `PAGADETODO_MOCK=true` si no hay sandbox oficial.
5. Actualizar `docs/MODULES` y pruebas si cambia un contrato.
