# Fase 11 - Vue 3 Compat sobre Mix 6

Fecha de cierre: 2026-03-15
Ruta base usada: `C:\temp\centrodecobros_phase10_l12_short`
Ruta nueva creada: `C:\temp\centrodecobros_phase11_vue3_compat`

## Resumen ejecutivo

Fase 11 cierra en `GO ejecutado`.

La copia aislada `C:\temp\centrodecobros_phase11_vue3_compat` se creo correctamente antes de modificar archivos y fue la unica ruta mutada durante la fase. El frontend migro de Vue `2.7.16` a Vue `3.5.30` usando `@vue/compat`, conservando `laravel-mix 6.0.49`, `webpack 5`, el wiring de `principal.blade.php` y el contrato `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css`.

## Contexto de entrada

- Workspace origen consolidado: `C:\temp\centrodecobros_phase10_l12_short`
- Backend de entrada: Laravel `12.54.1`, PHP `8.2.24`
- Frontend de entrada: Node `22.22.1`, npm `10.9.4`, Vue `2.7.16`, `vue-loader 15.11.1`, `vue-template-compiler 2.7.16`, Mix `6.0.49`
- Restricciones rectoras:
  - sin Vite;
  - sin cambio de nombres de assets;
  - sin tocar rutas, controladores ni contratos de negocio externos;
  - `Role.vue`, `ReporteSpei.vue`, `ReporteCargosRecurrentes.vue` y `/url` siguen siendo funcionalidad viva;
  - `principal.blade.php` solo podia tocarse si era indispensable.

## Verificacion de entorno previa

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `node -v` -> `v22.22.1`
- `npm -v` -> `10.9.4`

## Estrategia aplicada

La migracion siguio la ruta aprobada en Fase 9:

1. mantener `laravel-mix 6` y `webpack 5`;
2. subir el carril SFC a Vue 3:
   - `vue 2.7.16 -> 3.5.30`
   - `vue-loader 15.11.1 -> 16.8.3`
   - `vue-template-compiler -> @vue/compiler-sfc 3.5.30`
   - incorporar `@vue/compat 3.5.30`
3. aliasar `vue` y `vue$` a `@vue/compat` en `webpack.mix.js`;
4. compilar `.vue()` en modo `version: 3` con `compatConfig.MODE = 2`;
5. mover el bootstrap global de `new Vue({ el: '#app' })` a `createApp(...).mount('#app')`;
6. reemplazar `Vue.component(...)` por `app.component(...)`;
7. eliminar el filtro legacy `toCurrency` del template lane y sustituirlo por `$formatCurrency(...)` via `app.config.globalProperties`;
8. retirar dependencias Vue 2 sin uso real para no cargar peer conflicts innecesarios.

Referencia tecnica externa usada para la estrategia:

- Vue 3 migration build: [v3-migration.vuejs.org/migration-build](https://v3-migration.vuejs.org/migration-build)
- Vue global API / application instance: [v3-migration.vuejs.org/breaking-changes/global-api](https://v3-migration.vuejs.org/breaking-changes/global-api)
- Laravel Mix 6 con Vue: [laravel-mix.com/docs/6.0/vue](https://laravel-mix.com/docs/6.0/vue)
- Laravel Mix 6 upgrade / Vue support: [laravel-mix.com/docs/6.0/upgrade](https://laravel-mix.com/docs/6.0/upgrade)

## Bloqueadores Vue 3 tratados explicitamente

### `window.Vue`

- Resuelto por eliminacion del bootstrap basado en `window.Vue`.
- El proyecto ya no registra ni monta la app desde un global Vue 2.
- Se expone solo un handle no rector para debug/controlado:
  - `window.CentroDeCobrosVueApp`
  - `window.CentroDeCobrosVueRoot`

### `Vue.component(...)`

- Resuelto en `resources/assets/js/app.js`.
- Todo el registro global paso a `app.component(...)`.

### `new Vue({ el: '#app' })`

- Resuelto en `resources/assets/js/app.js`.
- El root ahora usa `createApp({...})` y `mount('#app')`.

### Filtros legacy

- Resueltos sin depender de la API removida de filtros.
- Se elimino `Vue.filter('toCurrency', ...)`.
- Los templates con `| toCurrency` migraron a `$formatCurrency(...)`.

### `v-model` legado

- Auditado en todos los componentes montados desde `app.js`.
- No se detectaron componentes custom con contrato `value` / `input`, `.sync`, `model:` o patrones Vue 2 que exigieran rewiring en esta fase.
- Los usos vivos son `v-model` sobre inputs nativos, checkboxes y selects, compatibles en esta baseline.

### Clases de transicion legacy

- Auditado el codigo Vue montado desde `app.js`.
- No se detectaron `transition` / `transition-group` ni clases legacy que exigieran compat o reescritura en esta fase.

### Plugins o componentes Vue 2 no compatibles

- `vue-select 2.5.0`:
  - importado en cuatro reportes, pero no se renderizaba `v-select` en templates;
  - removido del codigo y del lockfile;
  - alternativa efectiva: el proyecto ya usaba `<select>` nativo en esos mismos formularios;
  - impacto funcional observado: ninguno en la UI declarada.
- `vue-barcode 1.1.0`:
  - no se detecto uso real en `resources/assets/js`;
  - removido del lockfile y manifest;
  - impacto funcional observado: ninguno en la funcionalidad viva auditada.

## Dependencias actualizadas o reemplazadas

### Actualizadas

- `vue`: `2.7.16 -> 3.5.30`
- `vue-loader`: `15.11.1 -> 16.8.3`
- `@vue/compiler-sfc`: `3.5.30` agregado
- `@vue/compat`: `3.5.30` agregado

### Retiradas

- `vue-template-compiler 2.7.16`
- `vue-select 2.5.0`
- `vue-barcode 1.1.0`

### Sin cambios intencionales

- `laravel-mix 6.0.49`
- `webpack 5` via Mix
- `laravel-echo 1.4.0`
- `pusher-js 4.3.1`
- `principal.blade.php`
- lane vendor `plantilla.js` / `plantilla.css`

## Archivos modificados

- `package.json`
- `package-lock.json`
- `webpack.mix.js`
- `resources/assets/js/app.js`
- `resources/assets/js/components/PagoSpei.vue`
- `resources/assets/js/components/ReporteSpei.vue`
- `resources/assets/js/components/ReporteCargosRecurrentes.vue`
- `resources/assets/js/components/ReporteLigas.vue`
- `resources/assets/js/components/ReporteLigasDom.vue`
- `resources/assets/js/components/Respuesta.vue`
- `resources/assets/js/components/Transaccion.vue`
- `docs/MIGRATION_PHASE_11_VUE3_COMPAT.md`
- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_CHANGELOG.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
- `docs/README.md`

## Validaciones ejecutadas

### Entorno

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `node -v` -> `v22.22.1`
- `npm -v` -> `10.9.4`

### Dependencias y build

- `npm install` -> `OK`
- `npm ci` -> `OK`
- `npm ls vue @vue/compat @vue/compiler-sfc vue-loader` -> `OK`
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

## Resultado final

Estado B: `GO ejecutado`

- Vue `3.5.30` ya corre sobre `@vue/compat`
- Mix `6` y el contrato de assets se preservaron
- `principal.blade.php` no requirio cambios
- Laravel `12.54.1` quedo intacto
- la siguiente fase razonable deja de ser la migracion inicial y pasa a ser limpieza post-compat

## Riesgos residuales

1. El proyecto sigue dependiendo de `@vue/compat`; la salida definitiva de compat queda para Fase 12.
2. `laravel-echo 1.4.0` y `pusher-js 4.3.1` no se revalidaron en tiempo real end-to-end dentro de esta fase.
3. La lane vendor `plantilla.js` / `plantilla.css` sigue siendo legacy y continua fuera de `package.json`.
4. `npm ci` sigue reportando `7` vulnerabilidades (`5 low`, `2 moderate`) heredadas principalmente del ecosistema Mix/webpack legacy restante.
5. La cobertura automatica sigue sin sustituir QA manual focalizado en dashboard, notificaciones y flujos SPEI/recurrentes.

## Rollback detallado

1. No usar `C:\temp\centrodecobros_phase11_vue3_compat` como workspace activo si se quiere revertir la fase.
2. Volver a tomar `C:\temp\centrodecobros_phase10_l12_short` como baseline valida anterior.
3. Descartar `C:\temp\centrodecobros_phase11_vue3_compat` para revertir completamente la fase.
4. Si se desea reintentar, abrir una copia nueva desde `C:\temp\centrodecobros_phase10_l12_short`.
