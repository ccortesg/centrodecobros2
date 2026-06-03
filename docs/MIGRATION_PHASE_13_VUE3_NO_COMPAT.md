# Fase 13 - Vue 3 puro sin @vue/compat

Fecha de cierre: 2026-03-15
Ruta base usada: `C:\temp\centrodecobros_phase12_vue3_cleanup`
Ruta nueva creada: `C:\temp\centrodecobros_phase13_vue3_no_compat`

## Resumen ejecutivo

Fase 13 cierra en `GO ejecutado`.

La copia aislada `C:\temp\centrodecobros_phase13_vue3_no_compat` se creo correctamente antes de modificar archivos y fue la unica ruta mutada durante la fase. El frontend queda corriendo en Vue `3.5.30` puro, sin alias a `@vue/compat`, sin `configureCompat(...)`, manteniendo `laravel-mix 6.0.49`, `webpack 5`, `principal.blade.php` intacto y el contrato `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css`.

## Contexto de entrada

- Workspace origen consolidado: `C:\temp\centrodecobros_phase12_vue3_cleanup`
- Backend de entrada: Laravel `12.54.1`, PHP `8.2.24`
- Frontend de entrada: Node `22.22.1`, npm `10.9.4`, Vue `3.5.30`, `@vue/compat 3.5.30`, `laravel-mix 6.0.49`
- Restricciones rectoras respetadas:
  - sin Vite;
  - sin cambio de nombres de assets;
  - sin tocar contratos de negocio externos;
  - `Role.vue`, `ReporteSpei.vue`, `ReporteCargosRecurrentes.vue` y `/url` tratados como funcionalidad viva;
  - `principal.blade.php` sin cambios.

## Verificacion de entorno previa

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `node -v` -> `v22.22.1`
- `npm -v` -> `10.9.4`

## Estrategia aplicada

1. auditar la copia nueva antes de tocar codigo:
   - documentos rectores de Fase 11 y Fase 12;
   - `package.json`, `package-lock.json`, `webpack.mix.js`, `resources/assets/js/app.js`, `resources/assets/js/bootstrap.js`, `resources/views/principal.blade.php`;
   - todos los componentes montados desde `app.js`;
   - `tests/Feature/Smoke` y `scripts/local/`.
2. revalidar en codigo que no quedaran dependencias funcionales vivas a compat:
   - sin `window.Vue`;
   - sin `Vue.component(...)`;
   - sin `new Vue(...)`;
   - sin filtros legacy;
   - sin `compatConfig` fuera de `webpack.mix.js`;
   - sin `configureCompat(...)` fuera de `app.js`;
   - sin patrones legacy evidentes como `$listeners`, `$scopedSlots`, `.sync`, `inline-template`, `beforeDestroy`, `destroyed`, `model:` o contratos custom de `v-model`.
3. retirar la capa compat con el cambio minimo:
   - eliminar `@vue/compat` de `package.json`;
   - regenerar `package-lock.json` con `npm uninstall @vue/compat --save-dev` seguido de `npm ci`;
   - quitar alias `vue` / `vue$` a `@vue/compat` en `webpack.mix.js`;
   - quitar `compatConfig.MODE` del compiler SFC;
   - quitar `configureCompat(...)` e importar solo `createApp` desde `vue`.
4. revalidar build, phpunit y browser contra la copia nueva, no contra la baseline de Fase 12.

## Dependencias y configuracion retiradas o ajustadas

### Retiradas

- `@vue/compat 3.5.30` de `package.json`
- alias `vue` / `vue$` hacia `@vue/compat` en `webpack.mix.js`
- `compatConfig.MODE = 3` en la compilacion SFC de Mix
- `configureCompat({ MODE: 3 })` en `resources/assets/js/app.js`

### Confirmadas como vigentes

- `vue 3.5.30`
- `@vue/compiler-sfc 3.5.30`
- `vue-loader 16.8.3`
- `laravel-mix 6.0.49`
- `webpack 5`
- `laravel-echo 1.4.0`
- `pusher-js 4.3.1`

### Sin cambios intencionales

- `resources/assets/js/bootstrap.js`
- `resources/views/principal.blade.php`
- contrato `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css`
- backend Laravel `12.54.1`

### Nota sobre residuos de compatibilidad

Un grep directo sobre el bundle final puede seguir encontrando identificadores `COMPAT_*` dentro del codigo interno del compilador de Vue distribuido por `vue`/`@vue/compiler-sfc`. No corresponden a `@vue/compat` cargado en runtime:

1. `npm ls @vue/compat --depth=0` ya no lista el paquete;
2. `package.json`, `package-lock.json`, `webpack.mix.js` y `resources/assets/js/app.js` ya no contienen alias ni configuracion compat;
3. la consola del browser en los modulos auditados cerro con `0` errores y `0` warnings, incluyendo ausencia de warnings de compatibilidad.

## Archivos modificados

- `package.json`
- `package-lock.json`
- `webpack.mix.js`
- `resources/assets/js/app.js`
- `docs/MIGRATION_PHASE_13_VUE3_NO_COMPAT.md`
- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_CHANGELOG.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
- `docs/README.md`

## Validaciones ejecutadas

### Dependencias y build

- `npm ci` -> `OK`
- `npm ls vue @vue/compiler-sfc vue-loader @vue/compat --depth=0` -> `OK`; solo quedan `vue`, `@vue/compiler-sfc` y `vue-loader`
- `npm run development` -> `OK`
- `npm run production` -> `OK`

### Artefactos

- `public/js/app.js` generado -> `OK`
- `public/js/plantilla.js` generado -> `OK`
- `public/css/plantilla.css` generado -> `OK`

### Backend / no regresion

- `php artisan route:list` -> `OK`, `100` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 116 assertions)`

### Browser / validacion semiautomatica focalizada

La validacion browser se ejecuto con `playwright-cli` contra `http://127.0.0.1:8013`, sirviendo la copia Phase 13 con `php -S 127.0.0.1:8013 -t public server.php`.

Para no depender de credenciales manuales ni contaminar otras copias, la sesion autenticada se genero localmente dentro de `storage/framework/sessions` de `C:\temp\centrodecobros_phase13_vue3_no_compat` y se cargo en el browser como cookie Laravel valida.

Checks ejecutados:

- `/login`
  - renderiza correctamente;
  - consola: `0` errores, `0` warnings.
- shell autenticado `/main`
  - `window.CentroDeCobrosVueApp = true`;
  - `window.CentroDeCobrosVueRoot = true`;
  - `window.Vue = false`;
  - requests:
    - `POST /notification/get` -> `200`
    - `GET /dashboard` -> `200` dos veces;
  - consola: `0` errores, `0` warnings.
- Roles
  - `menu = 4` renderiza `Roles`;
  - `GET /rol?page=1&buscar=&criterio=nombre` -> `200`;
  - consola: `0` errores, `0` warnings.
- Reporte Ingresos SPEI
  - `menu = 20` renderiza `Reporte Ingresos SPEI`;
  - `GET /cliente/selectCliente` -> `200`;
  - click en `Listar`:
    - `GET /pagospei/reportePagoSpei?idcliente=0&fechaInicio=null&fechaFin=null` -> `200`;
    - `2058` filas visibles;
    - `Total Neto` visible;
  - consola: `0` errores, `0` warnings.
- Reporte Ingresos por Cargos Recurrentes
  - `menu = 25` renderiza `Reporte Ingresos por Cargos Recurrentes`;
  - `GET /cliente/selectCliente` -> `200`;
  - click en `Listar`:
    - `GET /transaccionDom/reporteTransaccionesDom?idcliente=0&fechaInicio=null&fechaFin=null` -> `200`;
    - `424` filas visibles;
    - `Total Neto` visible;
  - consola: `0` errores, `0` warnings.

## Resultado final

Estado B: `GO ejecutado`

Resultado concreto de la fase:

1. Vue `3.5.30` queda operando en modo puro sobre Mix `6`, sin `@vue/compat`;
2. el bootstrap sigue basado en `createApp(...)`, `app.component(...)` y `mount(...)`;
3. `principal.blade.php` no requirio cambios;
4. el contrato de assets se preserva intacto;
5. Laravel `12.54.1` queda intacto;
6. la siguiente fase recomendada pasa a ser un precheck formal de Vite, no otra fase de compat.

## Riesgos residuales

1. `principal.blade.php` y la lane vendor `plantilla.js` / `plantilla.css` siguen acopladas a rutas fijas; cualquier paso hacia Vite requiere precheck dedicado.
2. `laravel-echo 1.4.0` y `pusher-js 4.3.1` siguen sin validacion websocket end-to-end; en esta fase solo se confirmo que no rompen el shell auditado.
3. `npm audit` sigue reportando `7` vulnerabilidades (`5 low`, `2 moderate`) del lane Mix/webpack legado.
4. La validacion browser cubrio login, shell, Roles, SPEI y cargos recurrentes; el resto del menu autenticado sigue con cobertura exploratoria parcial.

## Rollback detallado

1. No usar `C:\temp\centrodecobros_phase13_vue3_no_compat` como workspace activo si se desea revertir la fase.
2. Volver a tomar `C:\temp\centrodecobros_phase12_vue3_cleanup` como baseline valida anterior.
3. Descartar `C:\temp\centrodecobros_phase13_vue3_no_compat` para revertir completamente la fase.
4. Si se desea continuar, abrir la fase siguiente desde `C:\temp\centrodecobros_phase13_vue3_no_compat` y no volver a mutar Fase 12.
