# Modulo Integraciones y Auditoria

Ultima actualizacion: 2026-07-10

## Objetivo

Agregar visibilidad administrativa sobre:

- llamadas salientes a Pagadetodo y callbacks a sistemas cliente;
- llamadas entrantes externas por rutas legacy de `routes/api.php`;
- actividad de login/logout y acceso a modulos del shell autenticado.

El modulo es solo de consulta/exportacion y no modifica contratos Pagadetodo, callbacks, scheduler ni credenciales.

## Acceso y menu

- Solo rol Administrador (`users.idrol=1`).
- Menu administrador: `Integraciones`, despues de `Acceso`.
- Targets Vue:
  - `menu==31`: `Outgoing API Requests`.
  - `menu==32`: `Incoming API Requests`.
  - `menu==33`: `User Activity Log`.
- El mismo menu ahora incluye `menu==34/35` para configuracion y entregas webhook. Su comportamiento operativo se documenta por separado en `MODULO_NOTIFICACIONES_WEBHOOK_CONFIGURABLES.md`.
- Componente: `resources/assets/js/components/IntegrationAudit.vue`.
- Switchboard: `resources/views/contenido/contenido.blade.php`.

## Rutas

Rutas web autenticadas/admin:

- `GET /integraciones/outgoing-api-requests`
- `GET /integraciones/outgoing-api-requests/exportar`
- `GET /integraciones/incoming-api-requests`
- `GET /integraciones/incoming-api-requests/exportar`
- `GET /integraciones/user-activity-log`
- `GET /integraciones/user-activity-log/exportar`

Ruta autenticada para registrar acceso a modulos:

- `POST /user-activity/module`

## Tablas

### `outgoing_api_requests`

Registra llamadas HTTP realizadas por la plataforma:

- Pagadetodo via `postJsonControlado()`.
- Callbacks a `users.ligaPago`.
- Callbacks a `users.ligaRecurrente`.

Campos principales:

- `occurred_at`, `provider`, `source_context`, `method`, `url`, `host`.
- `status_code`, `success`, `duration_ms`.
- `request_headers`, `request_payload`, `response_headers`, `response_body`.
- `error_class`, `error_message`.
- `idusuario`, `idtransaccion`, `correlation_reference`, `productivo`.

### `incoming_api_requests`

Registra llamadas externas recibidas por el grupo `api`, que en este proyecto no usa prefijo `/api`.

Campos principales:

- `occurred_at`, `method`, `path`, `route_action`.
- `ip_address`, `user_agent`, `status_code`, `success`, `duration_ms`.
- `request_headers`, `request_payload`, `response_body`, `error_message`.
- `idusuario`, `idtransaccion`, `correlation_reference`.

### `user_activity_logs`

Registra:

- `login_success`;
- `login_failed`;
- `logout`;
- `module_access`.

Campos principales:

- `occurred_at`, `idusuario`, `usuario`, `idrol`.
- `action`, `success`, `module_key`, `module_name`.
- `route_path`, `ip_address`, `user_agent`, `session_id_hash`, `metadata`.

## Sanitizacion

La sanitizacion se centraliza en `App\Services\AuditSanitizer`.

Nunca se deben guardar valores crudos de:

- `Password`, `password`, `token`, `Authorization`, `Cookie`, `X-CSRF-TOKEN`.
- `Tkn_reference`, `number_tkn`, `cc_number`, `cc_mask`, `Clabe`.
- `X-Soportetech-Signature` y secretos HMAC.
- bearer tokens, claves o secretos equivalentes.

Los cuerpos y headers se guardan ya sanitizados. La UI y los exports no deben intentar recuperar valores originales.

Esta regla aplica a las bitacoras, no al payload real de una entrega webhook configurable. El cuerpo real debe conservarse byte por byte para poder firmarlo y enviarlo sin alteracion.

## Filtros y exportacion

- Filtro superior por texto.
- Rango de fechas `Desde/Hasta`.
- Default frontend/backend: primer dia del mes actual hasta el dia actual.
- Paginacion default: 50 registros.
- Boton `Exportar` descarga `.xlsx` con la misma informacion sanitizada del listado.

## Purga

La retencion recomendada es 365 dias.

Comando manual:

```bash
php artisan audit:purge --days=365 --dry-run
php artisan audit:purge --days=365
```

No se programa en scheduler. Si se desea automatizar purga, debe abrirse una tarea separada y validar crecimiento/retencion con el propietario.

## Riesgos y cuidados

- No ejecutar migraciones productivas sin respaldo y ventana aprobada.
- No cambiar contratos publicos de `routes/api.php`.
- No registrar todo el AJAX interno; el middleware se instala solo en grupo `api`.
- Si falla la auditoria, el flujo financiero no debe fallar; el logger degrada a warning en `laravel.log`.
- Exportaciones pueden contener datos operativos sensibles aun sanitizados; mantener solo para Administrador.

## Validaciones recomendadas

```bash
php -l app/Services/AuditSanitizer.php
php -l app/Services/ApiAuditLogger.php
php -l app/Http/Controllers/IntegrationAuditController.php
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit tests/Unit/AuditSanitizerTest.php
C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Feature\IntegrationAuditFeatureTest.php
```

Si se modifica frontend:

```powershell
npm run production -- --no-progress
```

No versionar assets compilados.
