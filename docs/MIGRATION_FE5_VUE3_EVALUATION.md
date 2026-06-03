# FE-5 - Evaluacion de Vue 3

Ultima actualizacion: 2026-03-14

## Estado final

Resultado: `NO-GO documentado`

FE-5 queda ejecutada como evaluacion formal de Vue 3 en el workspace `C:\temp\centrodecobros_phase8_feh1_batch1`.

No se tocaron `package.json`, `package-lock.json`, assets compilados ni `resources/views/principal.blade.php`.

## Objetivo

Decidir si es viable migrar de `vue 2.7.16` a Vue `3` dentro del contrato frontend actual, preservando:

- `public/js/app.js`
- `public/js/plantilla.js`
- `public/css/plantilla.css`
- el shell Blade legacy
- el orden de carga actual `app.js` -> `plantilla.js`
- los globals legacy `Chart`, `swal`, `Echo` y jQuery

## Documentacion rectora usada

1. `docs/MIGRATION_MASTER_PLAN.md`
2. `docs/MIGRATION_FE4_VITE_EVALUATION.md`
3. `docs/MIGRATION_FEH1_FRONTEND_HARDENING_PLAN.md`
4. `docs/MIGRATION_VAL_A1_BROWSER_REALTIME_PLAN.md`
5. `docs/MIGRATION_FE3_POSTCHECK.md`
6. `docs/MIGRATION_FE3_IMPLEMENTATION.md`
7. `docs/MIGRATION_RISK_REGISTER.md`
8. `docs/MIGRATION_DECISIONS_LOG.md`
9. `docs/STACK_AND_DEPENDENCIES.md`
10. `docs/ENVIRONMENT_AND_OPERATION.md`
11. `docs/FRONTEND_ANALYSIS.md`

Ademas se releyo `docs/` completo y se contrastaron las referencias a `FE-5`, `Vue 3`, `window.Vue`, `Vue.component`, `Vue.filter`, `new Vue`, `vue-select`, `vue-loader`, `vue-template-compiler`, `principal.blade.php`, `app.js` y `plantilla.js`.

## Codigo contrastado

1. `package.json`
2. `webpack.mix.js`
3. `resources/views/principal.blade.php`
4. `resources/assets/js/app.js`
5. `resources/assets/js/bootstrap.js`
6. `resources/assets/js/components/Dashboard.vue`
7. `resources/assets/js/components/PagoSpei.vue`
8. `resources/assets/js/components/ReporteLigas.vue`
9. `resources/assets/js/components/ReporteLigasDom.vue`
10. `resources/assets/js/components/ReporteCargosRecurrentes.vue`
11. `resources/assets/js/components/ReporteSpei.vue`
12. `resources/assets/js/components/Respuesta.vue`
13. `resources/assets/js/components/Transaccion.vue`
14. `resources/assets/plantilla/js/template.js`
15. `node_modules/vue-select/package.json`
16. `node_modules/vue-barcode/package.json`

## Fuentes oficiales externas contrastadas

1. Vue 3 Migration Guide - Global API: [https://v3-migration.vuejs.org/breaking-changes/global-api](https://v3-migration.vuejs.org/breaking-changes/global-api)
2. Vue 3 Migration Guide - Filters: [https://v3-migration.vuejs.org/breaking-changes/filters](https://v3-migration.vuejs.org/breaking-changes/filters)
3. Vue 3 Migration Guide - Migration Build: [https://v3-migration.vuejs.org/migration-build](https://v3-migration.vuejs.org/migration-build)
4. Vue Select - Installation guide: [https://vue-select.org/guide/install.html](https://vue-select.org/guide/install.html)

## Baseline tecnica observada

1. El workspace actual sigue en `vue 2.7.16`, `vue-template-compiler 2.7.16`, `vue-loader 15.11.1` y `vue-select 2.5.0`.
2. `npm ls vue vue-loader vue-template-compiler vue-select @vue/compat @vue/compiler-sfc --depth=0` confirma ausencia de `@vue/compat` y `@vue/compiler-sfc`.
3. `webpack.mix.js` sigue declarando `.vue({ version: 2 })`.
4. `principal.blade.php` sigue cargando `js/app.js` antes de `js/plantilla.js`.
5. `app.js` sigue montando `window.Vue`, componentes globales, filtro global y una unica instancia `new Vue({ el: '#app' })`.
6. `Dashboard.vue` sigue consumiendo `Chart` como global.
7. `template.js` sigue gobernando dropdowns, sidebar, toggles y modales del shell mediante jQuery.

## Hallazgos tecnicos

### 1. El bootstrap actual sigue anclado a la API global de Vue 2

`app.js` depende hoy de:

- `window.Vue = Vue`
- `Vue.component(...)`
- `Vue.filter(...)`
- `new Vue({ el: '#app' })`

La guia oficial de migracion de Vue 3 mueve ese modelo a `createApp(...)` y `app.component(...)`. En esta copia no existe todavia una app aislada lista para ese cambio; el bootstrap esta acoplado al shell Blade global y a `Echo.private(...)`.

### 2. Los filtros siguen vivos en plantillas y Vue 3 los elimina

La guia oficial de Vue 3 elimina los filtros del template. El proyecto todavia registra `Vue.filter('toCurrency', ...)` y usa `| toCurrency` en `PagoSpei.vue`, `ReporteLigas.vue`, `ReporteLigasDom.vue`, `ReporteCargosRecurrentes.vue`, `ReporteSpei.vue`, `Respuesta.vue` y `Transaccion.vue`.

Eso convierte a FE-5 en algo mas amplio que un simple upgrade de paquete: obligaria a tocar pantallas de negocio y reportes sensibles.

### 3. El carril compiler/build sigue siendo de Vue 2

La guia oficial del migration build para setups custom con webpack exige:

- alias `vue` -> `@vue/compat`
- `vue-loader >= 16`
- `compilerOptions.compatConfig`

En este workspace siguen presentes `vue-loader 15.11.1`, `vue-template-compiler 2.7.16` y `webpack.mix.js` en modo Vue 2. No estan instalados `@vue/compat` ni `@vue/compiler-sfc`.

Inferencia a partir de esas fuentes y del estado local: abrir Vue 3 aqui requeriria cambiar a la vez bootstrap, compiler y estrategia de validacion.

### 4. `vue-select` no tiene una ruta estable y directa dentro del contrato actual

La documentacion oficial de `vue-select` distingue:

- `Vue 2 / Vue Select 3.x`
- `Vue 3 / Vue Select 4.x-beta`

En esta copia sigue instalado `vue-select 2.5.0`, y su `package.json` local declara `peerDependencies: { "vue": "2.x" }`. El codigo todavia lo importa en cuatro reportes, aunque no se detectaron tags `v-select` activos.

Eso deja dos rutas posibles si algun dia se reabre Vue 3:

1. retirar primero esos imports y limpiar el drift; o
2. aceptar una dependencia `beta` para Vue 3, lo cual hoy no encaja con la politica conservadora del plan.

### 5. El shell legacy sigue imponiendo una validacion mas amplia que la de un SPA aislado

`principal.blade.php` sigue cargando un shell compartido donde conviven:

- Vue
- jQuery
- `template.js`
- `Chart` global
- `Echo`
- Bootstrap JS desde `plantilla.js`

Eso no significa que jQuery o `plantilla.js` bloqueen por si solos Vue 3, pero si significa que un salto de runtime no puede declararse "local al bundle". Cualquier rewire exige revalidar dashboard, shell autenticado, dropdowns, toggles, modales, reportes y notificaciones.

## Opciones evaluadas

### Opcion A: subir directo a Vue 3 en el workspace actual

Dictamen: `NO-GO`

Motivo:

- rompe el bootstrap global actual;
- exige tocar filtros vivos en pantallas criticas;
- obliga a mover compiler/build a una lane distinta;
- deja abierto el problema de `vue-select`.

### Opcion B: usar migration build / `@vue/compat` sin cambiar el contrato general

Dictamen: `NO-GO`

Motivo:

- sigue requiriendo rewire de bootstrap y compiler;
- introduce una etapa transicional adicional sin resolver el acoplamiento estructural del shell;
- no cabe como paso incremental pequeno dentro de este workspace.

### Opcion C: mantener Vue `2.7.16` como baseline oficial y diferir Vue 3

Dictamen: `GO`

Motivo:

- preserva el runtime validado en FE-3;
- evita mezclar FE-5 con una reescritura estructural;
- deja Vue 3 solo para una reapertura explicita con prerequisitos tecnicos y funcionales mas amplios.

## Decision final

FE-5 queda en `NO-GO documentado` para migrar a Vue 3 dentro del contrato actual del proyecto.

La decision vigente es:

1. mantener `vue 2.7.16` como runtime oficial de esta copia;
2. no abrir upgrades a Vue 3, `vue-loader 16+` ni compiler lane nueva dentro del workspace actual;
3. tratar Vue 3 solo como reapertura estructural futura, no como siguiente tarea operativa.

## Condiciones para reabrir Vue 3 en el futuro

Reabrir FE-5 solo si se acepta explicitamente:

1. reemplazar el bootstrap global `window.Vue` / `Vue.component` / `new Vue` por una entrada basada en `createApp`;
2. retirar los filtros de template y migrar `toCurrency` a helpers, methods o computed;
3. decidir la limpieza o sustitucion real de `vue-select` antes de cualquier salto;
4. abrir una lane aislada para el compiler/runtime de Vue 3 (`@vue/compat` o ruta directa con `vue-loader 16+` y `@vue/compiler-sfc`);
5. revalidar shell autenticado, dashboard, notificaciones, reportes y modulos criticos como migracion mayor de frontend.

## Validaciones ejecutadas

1. Lectura completa de la documentacion rectora y operativa aplicable a FE-5.
2. `npm ls vue vue-loader vue-template-compiler vue-select @vue/compat @vue/compiler-sfc --depth=0` -> confirmado `vue 2.7.16`, `vue-loader 15.11.1`, `vue-template-compiler 2.7.16`, `vue-select 2.5.0` y ausencia de `@vue/compat` / `@vue/compiler-sfc`.
3. Contraste directo de `app.js`, `bootstrap.js`, `webpack.mix.js`, `principal.blade.php`, `Dashboard.vue`, reportes con `toCurrency` y metadata local de `vue-select`.
4. Busqueda local de patrones -> confirmados `window.Vue`, `Vue.component`, `Vue.filter`, `new Vue`, `Echo.private`, imports de `vue-select` y ausencia de tags `v-select` activos.
5. Contraste con la documentacion oficial de Vue 3 y de `vue-select`.

No se reejecutaron `npm ci`, builds, `phpunit` ni browser probes porque FE-5 fue una evaluacion documental/tecnica sin cambios de runtime ni de dependencias.

## Rollback

No aplica rollback tecnico porque no hubo cambios de codigo, paquetes ni assets.

La baseline de rollback frontend sigue siendo:

- `C:\temp\centrodecobros_phase7_fe3_vue27`

## Siguiente accion recomendada

1. Ejecutar `RT-PR1` como siguiente etapa accionable si se quiere reabrir realtime con sandbox/credenciales controladas.
2. Mantener `FE-4` y `FE-5` cerradas en `NO-GO documentado` mientras no cambie el contrato estructural del frontend.
3. Preservar `Mix 6` / `webpack 5` + `Vue 2.7.16` como baseline oficial de esta copia.
