# Fase 15 - Implementacion Vite incremental

Ultima actualizacion: 2026-03-15

## Resumen ejecutivo

Fase 15 cierra en `GO ejecutado`.

La implementacion aprobada en Fase 14 se ejecuto sin contaminar la baseline previa:

1. `resources/assets/js/app.js` ya compila por Vite usando `laravel-vite-plugin 2.1.0`, `vite 7.3.1` y `@vitejs/plugin-vue 6.0.5`.
2. `plantilla.js` y `plantilla.css` siguen fuera de Vite y ahora se generan por un script dedicado de lane legacy.
3. El contrato publico `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css` queda preservado.
4. `principal.blade.php` y las vistas guest sensibles quedaron intactas respecto de Fase 14.
5. Las validaciones de build, backend, smoke suite y browser focalizado pasaron sobre la nueva copia aislada.

## Contexto de entrada

- Baseline de origen real: `C:\temp\centrodecobros_phase14_vite_precheck`
- Nueva copia aislada creada para la fase: `C:\temp\centrodecobros_phase15_vite_impl`
- Backend confirmado de entrada: Laravel `12.54.1` con PHP `8.2.24`
- Frontend confirmado de entrada: Vue `3.5.30` puro sobre Mix `6`
- Recomendacion rectora heredada de Fase 14:
  - mover primero `app.js` a Vite;
  - dejar `plantilla.js` y `plantilla.css` fuera de Vite;
  - no exigir HMR ni romper Blade legacy en este primer corte.

## Ruta base usada

`C:\temp\centrodecobros_phase14_vite_precheck`

## Ruta nueva creada

`C:\temp\centrodecobros_phase15_vite_impl`

Confirmacion operativa:

- la copia aislada se creo correctamente antes de modificar archivos;
- no se trabajo directamente sobre `C:\temp\centrodecobros_phase14_vite_precheck`;
- todos los cambios de la fase quedaron en `C:\temp\centrodecobros_phase15_vite_impl`.

## Verificacion de entorno real

| Comando | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 12.54.1` |
| `composer show laravel/framework` | `v12.54.1` |
| `node -v` | `v22.22.1` |
| `npm -v` | `10.9.4` |
| `npx -v` | `10.9.4` |

## Estrategia aplicada

### Objetivo de implementacion

Separar el lane Vue moderno del lane vendor legacy sin cambiar el contrato publico de assets ni tocar el wiring Blade sensible.

### Como quedo dividido el build

#### Lane Vite para `app.js`

- Entrada Vite: `resources/assets/js/app.js`
- Configuracion oficial: `vite.config.js` con `laravel-vite-plugin` y `@vitejs/plugin-vue`
- Salida interna de Vite: `public/build/manifest.json` + `public/build/assets/app-*.js` + `public/build/assets/app-*.css`
- Salida publica estable preservada: `public/js/app.js`

`public/js/app.js` ya no es un bundle Webpack/Mix. Ahora es un bridge estable generado desde el manifest de Vite que:

1. inyecta el CSS emitido por Vite para el entrypoint `app.js`;
2. carga el JS modulo hasheado desde `public/build/assets/*`;
3. preserva la ruta publica fija que consume `principal.blade.php`.

#### Lane legacy para `plantilla.*`

- `public/js/plantilla.js`
- `public/css/plantilla.css`

Estas salidas siguen fuera de Vite. Ahora se generan con `scripts/local/build_legacy_lane.js`, que conserva el orden historico heredado de `webpack.mix.js` y usa `esbuild` solo como minificador en modo `production`.

### Flujo general de build

Los comandos `npm run development` y `npm run production` ahora ejecutan:

1. `scripts/local/build_legacy_lane.js`
2. `vite build`
3. `scripts/local/build_vite_bridge.js`

El orquestador de la fase es `scripts/local/run_phase15_build.js`.

### Cambio tecnico critico resuelto durante la fase

El shell autenticado monta el root Vue sobre el template HTML ya existente en Blade. En el primer intento, Vite emitio una advertencia real:

- `Component provided template option but runtime compilation is not supported in this build of Vue`

La correccion definitiva fue aliasar `vue` a `vue/dist/vue.esm-bundler.js` en `vite.config.js`. Con eso se preservo el wiring Blade actual sin tocar `principal.blade.php`.

## Archivos modificados

### Codigo y tooling

- `package.json`
- `package-lock.json`
- `vite.config.js`
- `scripts/local/build_legacy_lane.js`
- `scripts/local/build_vite_bridge.js`
- `scripts/local/run_phase15_build.js`
- `resources/assets/js/app.js`
- `resources/assets/js/bootstrap.js`

### Documentacion

- `docs/MIGRATION_PHASE_15_VITE_IMPLEMENTATION.md`
- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_CHANGELOG.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
- `docs/README.md`

### Archivos sensibles auditados y conservados sin cambios

- `resources/views/principal.blade.php`
- `resources/views/auth/contenido.blade.php`
- `resources/views/transaccion/contenido.blade.php`
- `resources/views/verificar/contenido.blade.php`
- `webpack.mix.js`
- `scripts/local/run_mix_build.js`

## Impacto explicitamente revisado

| Area auditada | Resultado |
| --- | --- |
| `principal.blade.php` | `SIN CAMBIOS`; sigue consumiendo `js/app.js` y `js/plantilla.js` |
| `auth/contenido.blade.php` | `SIN CAMBIOS`; sigue consumiendo `plantilla.*` |
| `transaccion/contenido.blade.php` | `SIN CAMBIOS`; sigue consumiendo `plantilla.*` |
| `verificar/contenido.blade.php` | `SIN CAMBIOS`; sigue consumiendo `plantilla.*` y jQuery inline |
| `Chart` global | `OK`; sigue provisto por `plantilla.js` |
| `swal` global | `OK`; sigue provisto por `plantilla.js` |
| `bootstrap.js` | migrado a imports ESM compatibles con Vite |
| `axios` | `OK`; sigue disponible en `window.axios` con CSRF configurado |
| `laravel-echo` y `pusher-js` | `OK`; `window.Echo`, `window.Pusher` y `window.Echo.private` siguieron disponibles |

## Validaciones ejecutadas

### Dependencias y build

- `npm install --save-dev vite@^7.3.1 laravel-vite-plugin@^2.1.0 @vitejs/plugin-vue@^6.0.5`
- `npm ci`
- `npm run development`
- `npm run production`
- `npm ls vite laravel-vite-plugin @vitejs/plugin-vue vue --depth=0`

Resultados:

- `npm ci`: `OK`; se mantienen `7` vulnerabilidades heredadas (`5 low`, `2 moderate`) del ecosistema legacy restante.
- `npm run development`: `OK`
- `npm run production`: `OK`
- `npm ls`: `vite 7.3.1`, `laravel-vite-plugin 2.1.0`, `@vitejs/plugin-vue 6.0.5`, `vue 3.5.30`

### Backend y no regresion

- `php artisan route:list` -> `OK`, `100` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 116 assertions)`

### Browser / validacion semiautomatica focalizada

Se ejecuto sobre `http://127.0.0.1:8015` usando `playwright-cli` y una cookie de sesion generada localmente desde Laravel para no tocar credenciales reales ni la base.

Rutas y modulos auditados:

1. `/login`
2. `/url`
3. shell autenticado `/main`
4. `Roles`
5. `Reporte Ingresos SPEI`
6. `Reporte Ingresos por Cargos Recurrentes`

Resultado browser:

- `0` errores y `0` warnings en consola en todas las rutas y modulos auditados al cierre.
- `/main` cargo sin regresion y mantuvo `notification/get` y `dashboard` en `200`.
- `Roles` cargo y consulto `GET /rol?page=1&buscar=&criterio=nombre` en `200`.
- `Reporte Ingresos SPEI` cargo, consulto clientes y ejecuto `GET /pagospei/reportePagoSpei?...` en `200`.
- `Reporte Ingresos por Cargos Recurrentes` cargo, consulto clientes y ejecuto `GET /transaccionDom/reporteTransaccionesDom?...` en `200`.
- `/url` siguio operativo y limpio en consola.

Nota de metodologia:

- El sidebar Vue sigue usando `@click` en elementos `li`.
- El CLI de Playwright no propago ese evento de forma fiable en esta sesion.
- Para no mezclar la fase con debugging del driver, la navegacion entre modulos auditados se hizo cambiando `window.CentroDeCobrosVueRoot.menu`, que es el mismo estado reactivo del shell.

## Resultado final

Estado B: `GO ejecutado`

Confirmaciones finales:

1. Vite quedo operativo para `app.js`.
2. `plantilla.js` y `plantilla.css` siguieron generandose correctamente fuera de Vite.
3. El contrato de assets quedo preservado:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
4. `principal.blade.php` quedo intacto.
5. Laravel `12.54.1` y Vue `3.5.30` puro siguieron estables.

## Riesgos residuales

1. El shell sigue dependiendo de un build de Vue con compilador porque el template raiz sigue viviendo en Blade.
2. `plantilla.*` sigue siendo una lane vendor legacy separada y todavia no entra a Vite.
3. `laravel-echo` / `pusher-js` siguen acoplados a credenciales hardcoded y no hubo sandbox realtime end-to-end.
4. `npm ci` sigue heredando vulnerabilidades del ecosistema legacy restante.
5. HMR no es requisito ni resultado de esta fase; la implementacion cierra por build reproducible y contrato publico estable.

## Rollback detallado

1. Detener cualquier servidor local o sesion browser abierta de la copia Phase 15.
2. Descartar por completo `C:\temp\centrodecobros_phase15_vite_impl`.
3. Volver a usar `C:\temp\centrodecobros_phase14_vite_precheck` como baseline valida anterior.
4. Si se requiere rehacer la fase, volver a clonar desde `C:\temp\centrodecobros_phase14_vite_precheck` hacia una nueva copia aislada.
5. No copiar archivos de regreso a Fase 14.

## Recomendacion unica para la fase siguiente

Abrir `Fase 16 - estabilizacion post-Vite`, con foco unico en:

1. consolidar el bridge estable de `public/js/app.js`;
2. decidir si la lane `plantilla.*` debe seguir separada o entrar gradualmente en una integracion posterior;
3. no mezclar esa fase con refactors de negocio ni con realtime end-to-end.
