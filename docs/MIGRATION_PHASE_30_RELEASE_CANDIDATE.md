# Fase 30 - Release candidate final

Fecha: 2026-03-25
Estado final: `GO con condiciones adicionales`

## Resumen ejecutivo

La Fase 30 se ejecuto en `C:\temp\centrodecobros_phase30_release_candidate` como cierre final del `release candidate`, no como una nueva migracion estructural.

La baseline funcional y tecnica heredada de Fase 29 quedo revalidada y, ademas, se cerro una de las condiciones previas abiertas:

1. Laravel sigue estable en `12.54.1` sobre PHP `8.2.24`.
2. Vue `3.5.30` puro sigue estable.
3. La reproducibilidad de `npm ci`, `npm run development` y `npm run production` sigue intacta.
4. El contrato publico de assets se preserva:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
   - `public/js/guest-public.js`
5. `principal.blade.php` permanece intacto respecto de Fase 29.
6. El advisory `CVE-2026-33347` en `league/commonmark` quedo cerrado actualizando `league/commonmark` de `2.8.1` a `2.8.2` dentro del rango permitido por `laravel/framework`.

La conclusion formal de esta fase es:

`el proyecto puede liberarse de forma controlada, pero solo bajo condiciones operativas y de aceptacion explicita del riesgo residual de integraciones hardcoded y del entorno de despliegue`

## Contexto de entrada y aislamiento

- Ruta base usada: `C:\temp\centrodecobros_phase29_release_readiness`
- Ruta nueva creada: `C:\temp\centrodecobros_phase30_release_candidate`
- La copia aislada se creo correctamente antes de modificar archivos.
- No se trabajo directamente sobre `C:\temp\centrodecobros_phase29_release_readiness`.
- Todos los cambios de esta fase quedaron solo en `C:\temp\centrodecobros_phase30_release_candidate`.

## Alcance rector

Fase 30 quedo limitada a:

1. cerrar condiciones previas abiertas del readiness;
2. revalidar build, backend y browser real sobre la copia final;
3. documentar el paquete final de predeploy, postdeploy y rollback;
4. emitir un dictamen unico de liberacion.

No se abrio una nueva migracion estructural.

## Validaciones ejecutadas

### Entorno y dependencias

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `composer show laravel/framework --locked` -> `v12.54.1`
- `composer validate --no-check-publish` -> `OK`
- `node -v` -> `v20.20.0`
- `npm -v` -> `10.8.2`
- `npm audit` -> `7` vulnerabilidades heredadas (`5 low`, `2 moderate`), sin fix inmediato acotado dentro del toolchain legacy restante

#### Cierre del advisory de Composer

- Estado heredado de Fase 29:
  - `composer audit` reportaba `CVE-2026-33347`
  - paquete afectado: `league/commonmark`
  - version lockeada: `2.8.1`
- Accion aplicada en Fase 30:
  - `composer update league/commonmark --with-all-dependencies`
  - `league/commonmark` subio a `2.8.2`
  - `composer audit` quedo en `No security vulnerability advisories found`
- Evidencia tecnica:
  - `laravel/framework v12.54.1` requiere `league/commonmark (^2.8.1)`
  - la correccion a `2.8.2` fue compatible y acotada al lockfile

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

Fragilidad de entorno observada:

- la suite completa sigue dependiendo de MySQL local operativo;
- en este host se requirio levantar temporalmente `wampmysqld64`;
- `DB_HOST=localhost` sigue siendo la configuracion estable del host actual para evitar colision con `wslrelay`.

### Browser real

Host validado:

- `http://127.0.0.1:8010`

#### Guest surfaces

- `/login`
  - `data-template-context="guest"`
  - `data-template-view="auth"`
  - `data-template-guest-ready="true"`
  - `data-template-guest-screen-active="login"`
  - `data-template-runtime="guest-public"`
  - solo carga `js/guest-public.js`
  - `window.CentroDeCobrosLegacyTemplate` ausente
  - `window.jQuery` ausente
  - consola: `0` errores / `0` warnings

- `/url`
  - `data-template-context="guest"`
  - `data-template-view="transaccion"`
  - `data-template-runtime="guest-public"`
  - submit seguro validado con `https://example.com`
  - `iframe` renderizado con `src="https://example.com"`
  - consola: `0` errores / `0` warnings

#### Shell autenticado

- `/main`
  - `meta[name="userId"] = 1`
  - `window.CentroDeCobrosVueRoot.menu = 0`
  - `window.CentroDeCobrosLegacyTemplate.state.ajaxHashMode = "disabled"`
  - requests iniciales:
    - `POST /notification/get` -> `200`
    - `GET /dashboard` -> `200`
  - topbar de cuenta validada:
    - `aria-expanded = true`
    - contenido visible: `Cuenta`, `Cerrar sesion`
  - sidebar grupo `Acceso` validado:
    - `open = true`
    - items visibles: `Usuarios`, `Roles`
  - consola: `0` errores / `0` warnings

#### Modulos auditados

1. `Roles`
   - `menu = 4`
   - header `Roles`
   - `GET /rol?page=1&buscar=&criterio=nombre` -> `200`
   - consola: `0` errores / `0` warnings
2. `Clientes`
   - `menu = 9`
   - header `Clientes`
   - `GET /cliente?page=1&buscar=&criterio=nombre&offset=10` -> `200`
   - consola: `0` errores / `0` warnings
3. `Usuarios`
   - `menu = 3`
   - header `Usuarios`
   - `GET /user?page=1&buscar=&criterio=nombre` -> `200`
   - consola: `0` errores / `0` warnings
4. `Reporte Ingresos SPEI`
   - `menu = 20`
   - header `Reporte Ingresos SPEI`
   - `GET /cliente/selectCliente` -> `200`
   - `GET /pagospei/reportePagoSpei?idcliente=0&fechaInicio=null&fechaFin=null` -> `200`
   - consola: `0` errores / `0` warnings
5. `Reporte Ingresos por Cargos Recurrentes`
   - `menu = 25`
   - header `Reporte Ingresos por Cargos Recurrentes`
   - `GET /cliente/selectCliente` -> `200`
   - `GET /transaccionDom/reporteTransaccionesDom?idcliente=0&fechaInicio=null&fechaFin=null` -> `200`
   - consola: `0` errores / `0` warnings

## Auditoria de condiciones previas heredadas

### 1. Advisory `league/commonmark`

Estado final: `cerrado`

- Fase 29 lo dejo abierto como condicion previa.
- Fase 30 lo cerro de forma segura con una actualizacion de patch:
  - `2.8.1 -> 2.8.2`
- Resultado final:
  - `composer audit` limpio
  - `phpunit` sigue en verde

### 2. Integraciones hardcoded / endpoints / credenciales

Estado final: `abierto`

Persisten referencias hardcoded en codigo vivo o residual de integracion:

- `app/Http/Controllers/TransaccionController.php`
  - endpoints `pagadetodo.mx`
  - credenciales productivas y sandbox
  - `IntegrationID` / `BusinessID`
- `app/Http/Controllers/TransaccionDomController.php`
  - mismas familias de credenciales e identificadores
- `resources/assets/js/bootstrap.js`
  - key y cluster de Pusher hardcoded
- `app/Mail/TransaccionValidada.php`
  - remitente fijo legacy

Conclusiones:

1. esta deuda no puede resolverse en esta fase sin reabrir integraciones de negocio;
2. sigue siendo riesgo de seguridad y de drift entre ambientes;
3. requiere aceptacion explicita de release o backlog de seguridad separado.

### 3. Dependencias de entorno local

Estado final: `abierto controlado`

- la reproduccion completa depende de MySQL local operativo;
- en este host la configuracion estable sigue siendo `DB_HOST=localhost`;
- la validacion browser autenticada requirio sesion controlada local y servidor temporal `php -S 127.0.0.1:8010 -t public server.php`.

### 4. Prerrequisitos de build y despliegue

Estado final: `abierto controlado`

Para liberar sigue siendo obligatorio validar:

- variables `APP_*`, `DB_*`, `SESSION_*`, `CACHE_*`, `QUEUE_*`, `MAIL_*`, `BROADCAST_*`
- scheduler operativo
- permisos sobre `storage/` y `bootstrap/cache/`
- estrategia explicita de build o publicacion de artefactos ya compilados
- smoke postdeploy inmediata
- rollback operativo listo

## Deuda residual aceptada

1. `plantilla.js` y `plantilla.css` siguen fuera de Vite por decision deliberada de alcance.
2. Las vistas guest vivas siguen apoyandose en layout/CSS legacy.
3. El residual `ajax/hash` sigue encapsulado, opt-in y sin autoarranque.
4. Realtime sigue sin validacion websocket end-to-end con credenciales reales.
5. `npm audit` mantiene `7` vulnerabilidades heredadas del carril legacy restante.
6. Integraciones y credenciales hardcoded siguen como deuda separada de seguridad/operacion.

## Condiciones exactas para liberar

1. Ejecutar el release solo desde `C:\temp\centrodecobros_phase30_release_candidate`.
2. Mantener intacto el contrato:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
   - `public/js/guest-public.js`
3. Mantener `principal.blade.php` sin cambios respecto de la baseline validada.
4. Provisionar y verificar variables de entorno productivas reales.
5. Dejar aprobada de forma explicita la excepcion temporal por:
   - endpoints y credenciales hardcoded de integraciones
   - key realtime hardcoded
   - vulnerabilidades heredadas de `npm audit`
6. Ejecutar checklist de predeploy, postdeploy y rollback de esta fase.
7. Ejecutar smoke inmediata postdeploy sobre `/login`, `/url`, `/main` y los modulos auditados.

## Dictamen formal

Decision unica:

`GO con condiciones adicionales`

Fundamento:

1. la migracion estructural ya estaba cerrada y Fase 30 no encontro drift funcional;
2. el advisory de Composer mas visible quedo cerrado con evidencia real;
3. build, backend y browser real quedaron validados sobre la copia final;
4. lo que sigue abierto ya no es migracion, sino control operativo y aceptacion de riesgo residual en integraciones hardcoded y entorno.

## Entregables actualizados

- `docs/MIGRATION_PHASE_30_RELEASE_CANDIDATE.md`
- `docs/MIGRATION_RELEASE_CHECKLIST.md`
- `docs/MIGRATION_DEPLOY_AND_ROLLBACK_RUNBOOK.md`
- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_CHANGELOG.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
- `docs/README.md`
