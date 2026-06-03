# Fase 9 - Baseline Consolidation

Fecha de cierre: 2026-03-14
Ruta base usada: `C:\temp\centrodecobros_phase8_feh1_batch1`
Ruta nueva creada: `C:\temp\centrodecobros_phase9_baseline_vue3_precheck`

## Resumen ejecutivo

Fase 9 cierra como una fase documental y de auditoria real. No se implemento Vue 3, no se implemento Vite y no se implemento Laravel 12. El trabajo consistio en:

1. clonar la ultima copia validada a una nueva ruta aislada;
2. comprobar que manifests, lockfiles, codigo, rutas y docs reflejan el mismo estado funcional;
3. corregir la documentacion rectora para que describa fielmente la baseline real actual.

La conclusion principal es que la baseline de phase8 sigue siendo funcional y coherente en codigo, pero la documentacion rectora arrastraba cierres historicos de `FE-4` y `FE-5` como si fueran la decision viva final del programa. Fase 9 corrige eso y deja una nueva baseline auditable.

## Inventario auditado

### Archivos y carpetas obligatorias leidas

- `composer.json`
- `composer.lock`
- `package.json`
- `package-lock.json`
- `webpack.mix.js`
- `app/`
- `resources/`
- `routes/`
- `config/`
- `database/centrodecobros.sql`
- `docs/`

### Versiones reales confirmadas

- `php artisan --version` -> `Laravel Framework 11.48.0`
- `composer show laravel/framework` -> `v11.48.0`
- `composer show laravel/framework --locked` -> `v11.48.0`
- `php -r "echo PHP_VERSION;"` -> `8.2.24`
- `composer --version` -> `2.7.3`
- `node -v` -> `v22.22.1`
- `npm -v` -> `10.9.4`

### Validaciones ejecutadas

- `php artisan route:list` -> `OK`, `100` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 116 assertions)`
- `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` -> `OK`
- `npm run development` -> `OK`
- `composer validate --no-check-publish` -> `OK`
- `composer audit` -> `OK`
- `npm audit` -> `13` vulnerabilidades (`7 low`, `6 moderate`)

## Coherencia detectada

### Backend

- `composer.json` y `composer.lock` estan alineados al backend actual Laravel `11.48.0` con PHP `^8.2`.
- El codigo sigue operando sobre la estructura clasica de Laravel, sin migracion a la estructura nueva.
- `composer audit` salio limpio.

### Frontend

- `package.json`, `package-lock.json` y `webpack.mix.js` estan alineados a Vue `2.7.16`, Mix `6.0.49` y `webpack 5`.
- El contrato de salida de assets sigue siendo:
  - `public/js/app.js`
  - `public/js/plantilla.js`
  - `public/css/plantilla.css`
- `principal.blade.php` sigue cargando esos assets por ruta fija.

### Funcionalidad viva heredada

Fase 9 confirmo que siguen vivos y presentes en codigo:

- `/role`
- `/pagospei/exportarReporteSpei`
- `/transaccionDom/exportarTransacciones`
- `/url`
- `Role.vue`
- `ReporteSpei.vue`
- `ReporteCargosRecurrentes.vue`

## Inconsistencias detectadas

1. La documentacion rectora aun presentaba `FE-4` y `FE-5` como cierres operativos definitivos del programa, con la siguiente accion en `RT-PR1`.
2. Esa conclusion ya no era suficiente para la nueva etapa porque el usuario pidio una nueva decision formal sobre Vue 3 y sobre el orden de Laravel 12 y Vite.
3. La carpeta `tests/smokes` no existe. La smoke suite real esta en `tests/Feature/Smoke`.
4. `yarn.lock` sigue presente como artefacto historico, pero la lane operativa validada es `npm`.
5. `database/migrations` sigue existiendo, pero no puede usarse como schema canonico.

## Correcciones documentales realizadas

1. Se crearon:
   - `docs/MIGRATION_PHASE_9_BASELINE_CONSOLIDATION.md`
   - `docs/MIGRATION_PHASE_9_PRECHECK_VUE3.md`
2. Se actualizaron:
   - `docs/MIGRATION_MASTER_PLAN.md`
   - `docs/MIGRATION_CHANGELOG.md`
   - `docs/MIGRATION_DECISIONS_LOG.md`
   - `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
   - `docs/MIGRATION_RISK_REGISTER.md`
   - `docs/MIGRATION_NEXT_PROMPTS.md`
   - `docs/README.md`
3. La documentacion rectora ahora deja a `FE-4` y `FE-5` como snapshots historicos y mueve la decision viva a Fase 9.
4. La documentacion rectora ahora referencia `tests/Feature/Smoke` como smoke suite real.

## Estado real consolidado del proyecto

### Backend

- Laravel `11.48.0`
- PHP `8.2.24`
- Composer `2.7.3`
- Fuente real de schema: `database/centrodecobros.sql`

### Frontend

- Node `22.22.1`
- npm `10.9.4`
- Vue `2.7.16`
- `laravel-mix 6.0.49`
- `webpack 5`
- `vue-loader 15.11.1`
- `vue-template-compiler 2.7.16`
- `vue-select 2.5.0`
- `vue-barcode 1.1.0`
- `laravel-echo 1.4.0`
- `pusher-js 4.3.1`
- `bootstrap-sass 3.4.3`

### Testing y tooling

- Suite PHPUnit funcional
- Smoke suite funcional
- Scripts locales funcionales
- Build frontend funcional

## Rollback

Fase 9 no cambia codigo funcional del sistema. El rollback es inmediato:

1. descartar `C:\temp\centrodecobros_phase9_baseline_vue3_precheck`;
2. volver a usar `C:\temp\centrodecobros_phase8_feh1_batch1` como copia validada anterior;
3. abrir una nueva copia aislada para la siguiente fase.
