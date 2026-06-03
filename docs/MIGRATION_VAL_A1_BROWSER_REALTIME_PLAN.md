# VAL-A1: Plan de validacion browser y realtime aislada

Ultima actualizacion: 2026-03-14  
Estado: `Ejecutada; NO-GO documentado`  
Workspace de referencia: `C:\temp\centrodecobros_phase8_feh1_batch1`  
Baseline de rollback: `C:\temp\centrodecobros_phase7_fe3_vue27`

## Objetivo

Definir una lane segura y reproducible para validar sesion autenticada, polling, `broadcasting/auth`, `Echo.private` y el estado real de `Pusher` sin reutilizar credenciales productivas ni introducir side effects externos en el workspace FE-3 / FE-H1 ya aprobado.

## Motivo de apertura

`FE-H1-L4` no pudo ejecutarse como upgrade de dependencias porque la evidencia local disponible solo alcanza para polling HTTP:

- el backend local resolvio `broadcasting.default=log`;
- el frontend sigue cargando `laravel-echo` y `pusher-js` con `key` / `cluster` hardcoded;
- el probe browser mostro `window.Echo` activo, canal privado montado y `pusherState=disconnected`;
- la captura de red no mostro `broadcasting/auth` ni websocket autenticado.

## Alcance

- estrategia segura de sesion autenticada para browser automation;
- validacion controlada de `/main`, `/dashboard` y polling `notification/get`;
- validacion de `broadcasting/auth`, `Echo.private('App.User.{id}')` y estado de conexion websocket;
- definicion de evidencia minima de consola, red y canal privado;
- criterio `GO / NO-GO` para reabrir la deuda realtime hoy diferida.

## Fuera de alcance

- upgrade de `laravel-echo` o `pusher-js`;
- cambios en `package.json`, `package-lock.json` o assets compilados;
- migrar a `Vite`;
- migrar a `Vue 3`;
- modificar `resources/views/principal.blade.php`;
- disparar callbacks, OTP, correo o pasarelas reales.

## Prerrequisitos

1. `FE-3` y `FE-H1` cerradas con baseline tecnica verde.
2. `Node 22.22.1` / npm `10.9.4` como lane operativa.
3. Estrategia segura para sesion autenticada:
   - cookie/sesion tecnica controlada; o
   - credencial local documentada de solo lectura.
4. Credenciales de realtime controladas:
   - app Pusher aislada de pruebas; o
   - equivalente acordado que no reapunte a un servicio externo productivo.
5. Servidor local arrancable en host controlado (`php artisan serve` o equivalente).

## Restricciones duras

1. No reutilizar por defecto la `key` hardcoded actual del frontend para validar contra un servicio externo real.
2. No cambiar `BROADCAST_DRIVER` a `pusher` en esta copia si no existe sandbox realtime dedicado.
3. No mutar usuarios operativos ni apoyarse en cuentas reales fuera de una sesion tecnica controlada.
4. No aprobar `FE-H1-L4` solo porque el polling HTTP siga funcionando.
5. No mezclar esta etapa con refactors de auth, frontend build o upgrades de framework.

## Tareas minimas

1. Inventariar el wiring actual de auth browser, `broadcasting.php`, `bootstrap.js`, `channels.php` y `NotifyAdmin`.
2. Definir como inyectar sesion autenticada de forma reproducible sin tocar credenciales reales.
3. Definir como externalizar o inyectar credenciales realtime controladas para la prueba.
4. Automatizar o guiar un probe browser con captura de:
   - consola;
   - red;
   - estado de `window.Echo`;
   - lista de canales;
   - estado de conexion websocket.
5. Confirmar si aparece `broadcasting/auth` y si el canal privado `App.User.{id}` queda realmente conectado.
6. Si hay capacidad real, disparar una notificacion controlada de prueba contra un usuario tecnico; si no, dejar el bloqueo documentado.
7. Emitir dictamen `GO` o `NO-GO` para reabrir la deuda realtime fuera de FE-H1.

## Evidencia minima esperada

- `POST /notification/get` en `200`;
- `POST /broadcasting/auth` en `200` o causa exacta del bloqueo;
- `window.Echo` y `window.Pusher` presentes;
- canal `private-App.User.{id}` visible en runtime;
- estado de conexion distinto de `disconnected` si la lane es valida;
- captura de consola sin errores websocket no controlados.

## Criterio de salida

- `GO` si existe lane aislada con sesion reproducible, credenciales controladas y evidencia browser/red suficiente para decidir luego un upgrade de `laravel-echo` / `pusher-js`.
- `NO-GO` si la validacion sigue dependiendo de credenciales hardcodeadas reales, de un servicio externo no aislado o de una sesion no reproducible.

## Resultado real de la ejecucion

### Baseline tecnica

- `nvm use 22.22.1` -> `OK`.
- `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1` -> `OK`.
- `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` -> `OK`.
- `php artisan route:list` -> `OK`; `broadcasting/auth` sigue publicado.
- `php artisan schedule:list` -> `OK`.
- `php vendor\bin\phpunit tests\Feature\Smoke tests\Feature\ExampleTest.php` -> `OK (21 tests, 114 assertions)`.
- No se rehizo `npm ci` ni build porque el workspace no presento drift de assets ni de lockfile y VAL-A1 no requiere tocar paquetes.

### Wiring confirmado

- `config/broadcasting.php` sigue env-driven y la lane local resuelve `broadcasting.default=log`.
- `resources/assets/js/bootstrap.js` sigue levantando `Echo` / `Pusher` con `key` y `cluster` hardcoded.
- `resources/assets/js/app.js` sigue montando `Echo.private('App.User.{id}')` desde el shell autenticado.
- `app/Notifications/NotifyAdmin.php` mantiene `['database', 'broadcast']`.
- `routes/channels.php` sigue autorizando `App.User.{id}` contra el `id` autenticado.

### Estrategia de sesion autenticada validada

- La lane local corre con `SESSION_DRIVER=file` y `SESSION_COOKIE=laravel_session`.
- Se valido una sesion tecnica de solo lectura generada desde el propio runtime Laravel para el admin local `id=1`, `usuario=admin`, sin mutar password ni depender de login interactivo.
- Como el cookie va cifrado en Laravel 11, la estrategia reproducible queda definida como `laravel_session` cifrado desde el runtime local, no como session id plano.
- Un probe HTTP contra `http://127.0.0.1:8010/main` con ese cookie devolvio `200`, renderizo `id="app"` y expuso `meta name="userId" content="1"`.

### Estrategia de credenciales realtime

- El backend local mantiene `PUSHER_*` poblado y `cluster=us2`, pero sigue operando en `BROADCAST_DRIVER=log`.
- El frontend hardcodea la misma lane Pusher; no existe evidencia de app de pruebas aislada ni de credenciales controladas separadas del servicio externo real.
- Repetir el probe websocket en navegador reutilizando esa `key` violaria las restricciones de VAL-A1. Por eso no se repitio Playwright para websocket en esta ejecucion.
- Se conserva como ultima evidencia browser vigente la obtenida en `FE-H1-L4`: `window.Echo` y `window.Pusher` presentes, canal `private-App.User.1` montado, `pusherState=disconnected` y ausencia de `broadcasting/auth`.

## Dictamen final

`NO-GO`.

- La sesion browser ya es reproducible en esta copia mediante cookie tecnica cifrada.
- La lane realtime no es segura ni aislada mientras dependa de la `key` hardcoded actual y de una app Pusher no controlada.
- `FE-H1-L4` no debe reabrirse en este workspace con la configuracion actual.

## Siguiente accion recomendada

1. Mantener bloqueada la deuda realtime en este workspace hasta contar con sandbox Pusher/Echo o equivalente con credenciales controladas.
2. Si se quiere reabrir websocket, preparar primero ese prerequisito externo y luego reintentar una validacion browser que incluya `broadcasting/auth` y estado conectado.
3. La siguiente fase interna del plan puede avanzar por `FE-4`, manteniendo realtime como deuda separada.

## Entregables

- `docs/MIGRATION_VAL_A1_BROWSER_REALTIME_PLAN.md`
- actualizacion de `docs/MIGRATION_MASTER_PLAN.md`
- actualizacion de `docs/MIGRATION_RISK_REGISTER.md`
- actualizacion de `docs/MIGRATION_DECISIONS_LOG.md`
- actualizacion de `docs/MIGRATION_CHANGELOG.md`
- si cambia la cobertura browser, actualizacion de `docs/MIGRATION_SMOKE_TESTS.md`
