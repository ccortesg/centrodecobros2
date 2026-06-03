# Fase 14 - Vite precheck formal

Fecha de cierre: 2026-03-15
Ruta base usada: `C:\temp\centrodecobros_phase13_vue3_no_compat`
Ruta nueva creada: `C:\temp\centrodecobros_phase14_vite_precheck`

## Resumen ejecutivo

Fase 14 cierra en `GO`.

La copia aislada `C:\temp\centrodecobros_phase14_vite_precheck` se creo correctamente antes de modificar archivos y fue la unica ruta mutada durante la fase. No se implemento Vite, no se toco `principal.blade.php`, no se cambiaron los nombres `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css` y no se alteraron contratos de negocio externos.

El precheck confirma que Vite ya es viable como siguiente fase, pero solo bajo una ruta incremental y reversible: mover primero `app.js` a Vite y dejar `plantilla.js` / `plantilla.css` fuera de Vite en el primer corte. La migracion directa de toda la lane frontend a Vite sigue siendo una mala ruta para esta baseline.

## Contexto de entrada

- Workspace origen consolidado: `C:\temp\centrodecobros_phase13_vue3_no_compat`
- Backend de entrada: Laravel `12.54.1`, PHP `8.2.24`
- Frontend de entrada: Node `22.22.1`, npm `10.9.4`, Vue `3.5.30` puro, `laravel-mix 6.0.49`, `webpack 5`
- Restricciones rectoras respetadas:
  - sin implementar Vite en esta fase;
  - sin cambiar nombres de salida de assets;
  - sin tocar contratos de negocio externos;
  - `principal.blade.php` solo para lectura y diagnostico;
  - `Role.vue`, `ReporteSpei.vue`, `ReporteCargosRecurrentes.vue` y `/url` siguen tratandose como funcionalidad viva.

## Verificacion de entorno real

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `node -v` -> `v22.22.1`
- `npm -v` -> `10.9.4`

## Validaciones ejecutadas

### Dependencias y build

- `npm ci` -> `OK`
  - `7` vulnerabilidades heredadas (`5 low`, `2 moderate`)
  - deprecaciones heredadas del lane Mix/webpack legacy, sin bloqueo de instalacion
- `npm run development` -> `OK`
  - salidas confirmadas:
    - `/js/app.js` -> `2.78 MiB`
    - `/css/plantilla.css` -> `295 KiB`
    - `/js/plantilla.js` -> `429 KiB`
- `npm run production` -> `OK`
  - salidas confirmadas:
    - `/js/app.js` -> `772 KiB`
    - `/css/plantilla.css` -> `244 KiB`
    - `/js/plantilla.js` -> `386 KiB`

### Backend / no regresion

- `php artisan route:list` -> `OK`, `100` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 116 assertions)`

### Artefactos finales observados tras `npm run production`

- `public/js/app.js` -> `790530` bytes
- `public/js/plantilla.js` -> `395720` bytes
- `public/css/plantilla.css` -> `250320` bytes
- `public/mix-manifest.json` -> sigue mapeando nombres fijos a si mismos

## Estado actual del build

### Wiring real de build

`package.json` mantiene toda la lane de scripts ligada a Mix:

- `development` -> `node scripts/local/run_mix_build.js development`
- `production` -> `node scripts/local/run_mix_build.js production`
- `watch` / `watch-poll` / `hot` -> `mix watch`

`scripts/local/run_mix_build.js` existe solo para cargar la configuracion interna de `laravel-mix` y cerrar el compilador webpack de forma controlada en Node `22.22.1`.

### Build graph actual

`webpack.mix.js` define exactamente tres salidas operativas:

1. `resources/assets/js/app.js` -> `public/js/app.js`
2. `resources/assets/plantilla/js/*` concatenado -> `public/js/plantilla.js`
3. `resources/assets/plantilla/css/*` concatenado -> `public/css/plantilla.css`

No hay `mix.version()`, no hay hashes expuestos al Blade y no hay uso vivo de `mix()` en las vistas auditadas. El contrato visible sigue siendo de rutas fijas.

## Asset graph auditado

### Lane `app.js`

Entrada real:

- `resources/assets/js/app.js`

Dependencias directas auditadas:

- `resources/assets/js/bootstrap.js`
- `vue 3.5.30`
- `jquery 3.7.1`
- `axios 1.13.6`
- `laravel-echo 1.4.0`
- `pusher-js 4.3.1`
- `20` componentes Vue montados desde el root

Bootstrap real observado:

- registra `window.$` y `window.jQuery`
- registra `window.axios`
- registra `window.Pusher`
- registra `window.Echo`
- crea el root Vue con `createApp(...)`
- registra componentes globales
- monta solo si existe `#app`

Componentes montados desde `app.js`:

- `rol`, `role`, `user`, `estado`, `ciudad`, `cliente`
- `clienteconsolidar`, `clientedepurar`
- `dashboard`, `notification`
- `transaccion`, `respuesta`, `transacciondom`
- `reporteligas`, `reporteligasdom`
- `reportecargosrecurrentes`, `consultaspei`, `pagospei`, `cancelaspei`, `reportespei`

### Lane `plantilla.js`

Entrada real concatenada por Mix:

- `resources/assets/plantilla/js/jquery.min.js`
- `resources/assets/plantilla/js/popper.min.js`
- `resources/assets/plantilla/js/bootstrap.min.js`
- `resources/assets/plantilla/js/Chart.min.js`
- `resources/assets/plantilla/js/pace.min.js`
- `resources/assets/plantilla/js/template.js`
- `resources/assets/plantilla/js/sweetalert2.all.js`

Dependencias funcionales reales sobre esta lane:

- `Dashboard.vue` consume `Chart` como global
- multiples componentes usan `swal(...)` como global
- `template.js` gobierna toggles y wiring del shell legacy
- las vistas guest `auth/contenido.blade.php`, `transaccion/contenido.blade.php` y `verificar/contenido.blade.php` dependen solo de `plantilla.js`
- `verificar/contenido.blade.php` contiene jQuery inline y AJAX inline que asume `plantilla.js` ya cargado

### Lane `plantilla.css`

Entrada real concatenada por Mix:

- `resources/assets/plantilla/css/font-awesome.min.css`
- `resources/assets/plantilla/css/simple-line-icons.min.css`
- `resources/assets/plantilla/css/style.css`

Consumidores reales:

- `principal.blade.php`
- `auth/contenido.blade.php`
- `transaccion/contenido.blade.php`
- `verificar/contenido.blade.php`

### Blade contract real

`principal.blade.php` sigue cargando:

- `css/plantilla.css`
- `js/app.js`
- `js/plantilla.js`

en ese orden y por ruta fija. La vista autenticada sigue siendo el shell critico. El menu Vue vivo sigue renderizando `dashboard`, `rol`, `reportespei`, `reportecargosrecurrentes` y el resto del catalogo desde `resources/views/contenido/contenido.blade.php`.

### Artefactos no rectores

- `resources/assets/sass/app.scss` existe, pero no participa en la lane viva del build
- `resources/views/layouts/app.blade.php` apunta a `css/app.css` y `js/app.js`, pero solo `home.blade.php` la extiende; no es la vista rectora del shell legacy

## Viabilidad de Vite sobre la baseline actual

### Lo que ya no bloquea

1. Laravel `12.54.1` esta estable y fuera del problema de bundler.
2. Vue `3.5.30` puro ya corre sobre una sola entrada modular real: `resources/assets/js/app.js`.
3. El codigo vivo montado desde `app.js` ya no depende de `window.Vue`, `new Vue(...)` ni `@vue/compat`.
4. El contrato de build del lane Vue es pequeno y claro: un solo entrypoint, SFCs, `axios`, `jquery`, `laravel-echo` y `pusher-js`.
5. `mix-manifest.json` no gobierna las vistas vivas; por lo tanto no existe dependencia funcional a `mix()` que obligue a conservar Mix.

### Lo que si sigue bloqueando una migracion directa

1. `principal.blade.php` y otras vistas legacy usan rutas fijas y no `@vite`.
2. `plantilla.js` y `plantilla.css` no son entradas modernas; son concatenaciones de vendor assets legacy con orden rigido.
3. `Dashboard.vue` depende de `Chart` global y varios modulos dependen de `swal` global; eso amarra `app.js` al orden de carga del lane vendor.
4. La salida ESM y el HMR estandar de Vite cambiarian la semantica de carga frente al script clasico actual.
5. `verificar/contenido.blade.php` contiene jQuery inline y depende de que `plantilla.js` siga publicando globals.

## Opciones evaluadas para Vite

### Opcion A - migracion directa de toda la lane a Vite conservando los tres outputs dentro de Vite

Dictamen: `NO-GO`

Motivo:

- fuerza a reimplementar en Vite la lane `plantilla.*` completa;
- mezcla el riesgo del shell legacy con el del lane Vue;
- no aporta beneficio tecnico inmediato frente al riesgo de orden de carga, globals e inline scripts.

### Opcion B - migracion incremental: `app.js` primero en Vite y `plantilla.*` fuera de Vite en el primer corte

Dictamen: `GO`

Motivo:

- separa el lane Vue moderno del lane vendor legacy;
- preserva `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css`;
- permite sacar `laravel-mix` y `webpack` del carril `app.js` sin tocar primero el shell legacy;
- mantiene rollback simple porque el corte inicial solo cambia el bundler del lane Vue.

### Opcion C - Vite con emulacion custom completa del contrato legacy desde el dia 1

Dictamen: `NO-GO`

Motivo:

- tecnicamente posible, pero peor que la opcion B;
- reintroduce complejidad para imitar en Vite el mismo comportamiento que Mix ya entrega hoy para `plantilla.*`;
- deja poco ROI y aumenta el riesgo operativo.

## Recomendacion unica

La ruta optima para Fase 15 es la `Opcion B`.

Eso significa:

1. introducir Vite solo para `resources/assets/js/app.js`;
2. mantener `plantilla.js` y `plantilla.css` fuera de Vite en el primer corte;
3. preservar el contrato publico:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
4. evitar `@vite`, hashes visibles o HMR como parte obligatoria del primer corte;
5. sustituir Mix por:
   - `vite` + `@vitejs/plugin-vue` para `app.js`;
   - un script dedicado de concat/copia para `plantilla.js` y `plantilla.css`.

## Dictamen GO / NO-GO

Dictamen formal: `GO`

Interpretacion del dictamen:

1. la siguiente fase puede implementar Vite con cambios controlados;
2. el `GO` no aplica a una migracion directa de toda la lane legacy;
3. el `GO` aplica a una implementacion incremental y reversible, con `app.js` primero y `plantilla.*` fuera de Vite en el primer corte.

## Bloqueadores y riesgos vigentes

### Bloqueadores que la Fase 15 debe controlar

1. contrato fijo de assets en Blade legacy;
2. lane `plantilla.*` usada por shell autenticado y vistas guest;
3. globals `Chart` y `swal` consumidos por el lane Vue;
4. diferencia entre script clasico actual y salida modulo/HMR por defecto en Vite;
5. bootstrap realtime con `laravel-echo 1.4.0` y `pusher-js 4.3.1`, que sigue siendo legacy pero no bloqueante para el lane `app.js`.

### Riesgos abiertos relevantes

- R-04: rutas fijas en Blade
- R-05: mezcla de lane npm con vendor assets legacy
- R-10: realtime legacy
- R-11: deuda residual Mix/webpack
- R-16: cambio de semantica de carga si se usa Vite por defecto
- R-17: vistas guest e inline jQuery ligadas a `plantilla.js`

## Prerrequisitos de Fase 15

1. crear una nueva copia aislada desde `C:\temp\centrodecobros_phase14_vite_precheck`;
2. introducir `vite` y `@vitejs/plugin-vue` solo para el lane `app.js`;
3. definir una salida estable para `public/js/app.js` sin exponer hashes al Blade legacy;
4. reemplazar `.scripts()` / `.styles()` de Mix por un script dedicado para `plantilla.js` y `plantilla.css`;
5. mantener `principal.blade.php`, `auth/contenido.blade.php`, `transaccion/contenido.blade.php` y `verificar/contenido.blade.php` funcionalmente intactos en rutas de assets;
6. desactivar HMR como requisito del primer corte y priorizar build reproducible;
7. revalidar como minimo:
   - `npm ci`
   - `npm run development`
   - `npm run production`
   - `php artisan route:list`
   - `php artisan schedule:list`
   - `php vendor/bin/phpunit`
   - smoke funcional sobre `/main`, `/role`, `ReporteSpei`, `ReporteCargosRecurrentes` y `/url`

## Archivos modificados en Fase 14

- `docs/MIGRATION_PHASE_14_VITE_PRECHECK.md`
- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_CHANGELOG.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
- `docs/README.md`

## Rollback

1. No usar `C:\temp\centrodecobros_phase14_vite_precheck` como workspace activo si se desea revertir la fase.
2. Volver a tomar `C:\temp\centrodecobros_phase13_vue3_no_compat` como baseline valida anterior.
3. Descartar `C:\temp\centrodecobros_phase14_vite_precheck` para revertir completamente la fase.
4. Si se desea continuar, abrir Fase 15 desde `C:\temp\centrodecobros_phase14_vite_precheck` y no volver a mutar la baseline de Fase 13.
