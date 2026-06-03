# Fase 29 - Release readiness y cierre operativo

Fecha: 2026-03-25
Estado final: `GO con condiciones previas`

## Resumen ejecutivo

La Fase 29 se ejecuto en `C:\temp\centrodecobros_phase29_release_readiness` como una fase de cierre operativo y `production readiness`, no como una nueva ronda de migracion estructural.

La baseline funcional heredada de Fase 28 quedo revalidada dentro del alcance actual:

1. Laravel `12.54.1` sigue estable sobre PHP `8.2.24`.
2. Vue `3.5.30` puro sigue estable.
3. `app.js` por Vite y la lane legacy `plantilla.*` siguen reproduciendose sin drift funcional observado.
4. `/login` y `/url` siguen desacoplados de `public/js/plantilla.js` y cargan `public/js/guest-public.js`.
5. `principal.blade.php` se mantiene intacto.
6. El contrato publico de assets sigue intacto:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
   - `public/js/guest-public.js`

La conclusion formal de esta fase es:

`el proyecto esta listo para produccion dentro del alcance actual, pero con condiciones previas obligatorias de despliegue y aceptacion explicita de riesgo residual`

## Contexto de entrada y aislamiento

- Ruta base usada: `C:\temp\centrodecobros_phase28_migration_closure`
- Ruta nueva creada: `C:\temp\centrodecobros_phase29_release_readiness`
- La copia aislada se creo correctamente antes de modificar archivos.
- No se trabajó directamente sobre `C:\temp\centrodecobros_phase28_migration_closure`.
- Los cambios de esta fase quedaron solo en `C:\temp\centrodecobros_phase29_release_readiness`.

## Alcance rector de Fase 29

Esta fase quedo limitada a:

1. validar integralmente la baseline final;
2. normalizar la reproducibilidad del entorno;
3. documentar deuda residual aceptada;
4. documentar prerequisitos de despliegue, postdeploy y rollback;
5. emitir un dictamen unico de release readiness.

No se abrio ninguna migracion estructural nueva.

## Validaciones ejecutadas

### Entorno y dependencias

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `composer show laravel/framework --locked` -> `v12.54.1`
- `composer --version` -> `2.7.3`
- `node -v` -> `v20.20.0`
- `npm -v` -> `10.8.2`
- `npx -v` -> `10.8.2`
- `composer validate --no-check-publish` -> `OK`
- `composer audit` -> `1` advisory abierta:
  - `league/commonmark`
  - `CVE-2026-33347`
  - severidad `medium`
  - introducida transitivamente por `laravel/framework`
- `npm audit` -> `7` vulnerabilidades heredadas (`5 low`, `2 moderate`), sin fix disponible inmediato en la ruta actual del toolchain legacy

### Reproducibilidad del build

- `npm ci` -> `OK`
- `npm run development` -> `OK`
- `npm run production` -> `OK`

Artefactos verificados tras `production`:

- `public/js/app.js` -> `1859` bytes
- `public/js/plantilla.js` -> `403207` bytes
- `public/css/plantilla.css` -> `246986` bytes
- `public/js/guest-public.js` -> `1141` bytes

### Backend

- `php artisan route:list` -> `OK`, `97` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 136 assertions)`

Hallazgo de entorno durante la validacion:

- `phpunit` fallo primero porque `wampmysqld64` estaba en `Stopped`
- se levanto temporalmente para completar la evidencia
- la suite quedo en verde
- el riesgo queda documentado como fragilidad del entorno local, no como regresion funcional del proyecto

### Browser real

Servidor local auditado:

- `http://localhost:8010`

#### `/login`

- render correcto
- `data-template-context="guest"`
- `data-template-view="auth"`
- `data-template-guest-ready="true"`
- `data-template-guest-screen-active="login"`
- `data-template-runtime="guest-public"`
- solo carga `js/guest-public.js`
- `window.CentroDeCobrosLegacyTemplate` ausente
- `window.jQuery` ausente
- consola: `0` errores / `0` warnings

#### `/url`

- render correcto
- `data-template-context="guest"`
- `data-template-view="transaccion"`
- `data-template-guest-ready="true"`
- `data-template-guest-screen-active="url"`
- `data-template-runtime="guest-public"`
- solo carga `js/guest-public.js`
- `window.CentroDeCobrosLegacyTemplate` ausente
- `window.jQuery` ausente
- submit seguro validado con `https://example.com`
- `iframe` renderizado con `src="https://example.com"`
- consola: `0` errores / `0` warnings

#### `/main`, topbar y sidebar

- `meta[name="userId"] = 1`
- `window.CentroDeCobrosVueRoot.menu = 0`
- `window.CentroDeCobrosLegacyTemplate.state.ajaxHashMode = "disabled"`
- requests iniciales:
  - `POST /notification/get` -> `200`
  - `GET /dashboard` -> `200`
- topbar de cuenta validada:
  - `aria-expanded = true`
  - items visibles: `Cuenta`, `Cerrar sesión`
- sidebar grupo `Acceso` validado:
  - `open = true`
  - items `Usuarios` y `Roles` presentes
- consola: `0` errores / `0` warnings

#### Modulos auditados en browser

1. `Roles`
   - `menu = 4`
   - header `Roles`
   - `GET /rol?page=1&buscar=&criterio=nombre` -> `200`
2. `Clientes`
   - `menu = 9`
   - header `Clientes`
   - `GET /cliente?page=1&buscar=&criterio=nombre&offset=10` -> `200`
3. `Usuarios`
   - `menu = 3`
   - header `Usuarios`
   - `GET /user?page=1&buscar=&criterio=nombre` -> `200`
4. `Reporte Ingresos SPEI`
   - `menu = 20`
   - header `Reporte Ingresos SPEI`
   - `GET /cliente/selectCliente` -> `200`
   - `GET /pagospei/reportePagoSpei?idcliente=0&fechaInicio=null&fechaFin=null` -> `200`
5. `Reporte Ingresos por Cargos Recurrentes`
   - `menu = 25`
   - header `Reporte Ingresos por Cargos Recurrentes`
   - `GET /cliente/selectCliente` -> `200`
   - `GET /transaccionDom/reporteTransaccionesDom?idcliente=0&fechaInicio=null&fechaFin=null` -> `200`

Consola browser del bloque autenticado y de los modulos auditados:

- `0` errores
- `0` warnings

## Evaluacion de normalizacion del entorno

### Lo que ya quedo suficientemente normalizado

1. Lockfiles y scripts de build reproducen la salida esperada con:
   - PHP `8.2.24`
   - Composer `2.7.3`
   - Node `20.20.0`
   - npm `10.8.2`
2. `npm ci`, `npm run development` y `npm run production` generan el contrato publico esperado.
3. La suite backend minima y la bateria browser pueden repetirse sobre la copia aislada.
4. La lane moderna y la lane legacy quedan documentadas y separadas por script.

### Fragilidades de entorno que siguen vivas

1. La validacion local completa depende de un MySQL local operativo; en este host concreto requirio `wampmysqld64`.
2. En este host local el acceso estable a MySQL quedo en `DB_HOST=localhost`; `127.0.0.1` puede colisionar con `wslrelay` en `3306`.
3. La autenticacion browser automatizada sigue sin credenciales publicadas de prueba; para esta fase se resolvio con una sesion local controlada.
4. La wrapper `.sh` de la skill `playwright` fallo por fin de linea CRLF bajo el bash disponible del host; la validacion se completo con `npx @playwright/cli`, que es funcionalmente equivalente.

Conclusion de normalizacion:

`el entorno local ya es suficientemente reproducible para validacion y despliegue controlado, pero no es todavia un entorno local totalmente autosuficiente ni libre de dependencias del host`

## Variables y servicios obligatorios para produccion

### Variables de entorno minimas

Definir y revisar al menos:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_DEBUG=false`
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `BROADCAST_DRIVER`
- `CACHE_DRIVER`
- `SESSION_DRIVER`
- `SESSION_LIFETIME`
- `SESSION_DOMAIN` si aplica
- `SESSION_SECURE_COOKIE=true` en HTTPS
- `QUEUE_DRIVER`
- `MAIL_MAILER`
- `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` / `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`
- `MAIL_FROM_NAME`
- `POSTMARK_TOKEN` si se usa Postmark
- `FILESYSTEM_DRIVER`
- `FILESYSTEM_CLOUD` y `AWS_*` si se usa S3
- `PUSHER_*` solo si el ambiente realmente habilita broadcasting realtime

### Servicios minimos disponibles

- PHP runtime compatible con Laravel `12.54.1`
- servidor web para servir `public/`
- MySQL con schema operativo real
- permisos de escritura en `storage/` y `bootstrap/cache/`
- lane de build Node solo para generar assets del release, salvo que se publiquen artefactos ya compilados
- scheduler del sistema para las tareas de Laravel
- correo/salida transaccional segun el `MAIL_MAILER` elegido

### Nota importante de deuda operativa

Persisten credenciales y endpoints hardcoded en integraciones financieras del codigo legacy. Eso no se resolvio en esta fase y debe seguir tratandose como riesgo residual aceptado o como backlog de seguridad separado.

## Deuda residual aceptada

1. `public/css/plantilla.css` y los layouts guest Blade siguen siendo legacy aceptado.
2. `plantilla.*` sigue fuera de Vite por decision deliberada de alcance.
3. El residual `ajax/hash` sigue encapsulado, opt-in y sin autoarranque.
4. `template.shared.js` conserva compatibilidad minima publicada.
5. Realtime / websocket sigue fuera de validacion end-to-end con credenciales reales.
6. `npm audit` mantiene `7` vulnerabilidades heredadas del toolchain legacy.
7. `composer audit` reporta `CVE-2026-33347` en `league/commonmark` via `laravel/framework`.
8. Credenciales y endpoints hardcoded de integraciones siguen como deuda de seguridad/operacion separada.

## Riesgos no bloqueantes y condiciones previas

### Riesgos no bloqueantes

1. Advisory media en `league/commonmark` sin evidencia actual de explotacion directa en las superficies auditadas.
2. Vulnerabilidades `npm audit` concentradas en dependencias legacy/dev y sin fix inmediato dentro de la baseline actual.
3. Dependencia operativa local a MySQL del host para repetir la suite completa.

### Condiciones previas obligatorias para liberar

1. provisionar y verificar variables de entorno reales del ambiente productivo;
2. generar o publicar los assets de release con la secuencia documentada;
3. ejecutar checklist de predeploy y postdeploy de esta fase;
4. dejar explicitamente aceptados o mitigados:
   - `R-01` credenciales/endpoints hardcoded
   - `R-25` advisory `league/commonmark`
5. confirmar scheduler operativo y salud de BD/permiso de `storage`;
6. ejecutar smoke funcional inmediata sobre `/login`, `/url`, `/main` y los modulos auditados tras desplegar.

## Dictamen formal

Decision unica:

`GO con condiciones previas`

Fundamento:

1. La migracion ya estaba cerrada en Fase 28 y Fase 29 no encontro drift funcional dentro del alcance actual.
2. La baseline reproduce build, rutas, scheduler, smoke suite y browser real sin errores funcionales observados.
3. Lo pendiente ya no es una brecha de migracion estructural, sino de operacion de release:
   - configuracion de ambiente
   - aceptacion explicita de riesgo residual
   - ejecucion disciplinada de deploy/rollback
4. La evidencia disponible no justifica `NO-GO`, pero tampoco sostiene un `GO` incondicional.

## Entregables de cierre

- `docs/MIGRATION_PHASE_29_RELEASE_READINESS.md`
- `docs/MIGRATION_RELEASE_CHECKLIST.md`
- `docs/MIGRATION_DEPLOY_AND_ROLLBACK_RUNBOOK.md`
- actualizaciones en:
  - `docs/MIGRATION_MASTER_PLAN.md`
  - `docs/MIGRATION_CHANGELOG.md`
  - `docs/MIGRATION_DECISIONS_LOG.md`
  - `docs/MIGRATION_RISK_REGISTER.md`
  - `docs/MIGRATION_NEXT_PROMPTS.md`
  - `docs/README.md`
