# Fase 9 - Precheck Vue 3

Fecha de cierre: 2026-03-14
Workspace auditado: `C:\temp\centrodecobros_phase9_baseline_vue3_precheck`

## Contexto de entrada

El proyecto entra a este precheck con el backend ya consolidado en Laravel `11.48.0` y el frontend estabilizado en:

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

No se implementa Vue 3 en Fase 9. Solo se decide si la siguiente fase es viable, por que ruta, y en que orden con Laravel 12 y Vite.

## Estado actual del frontend

### Hallazgos directos en codigo

- `resources/assets/js/app.js` sigue usando:
  - `window.Vue = Vue`
  - `Vue.component(...)`
  - `Vue.filter('toCurrency', ...)`
  - `new Vue({ el: '#app' })`
  - `Echo.private(...)`
- `resources/views/principal.blade.php` sigue cargando:
  - `css/plantilla.css`
  - `js/app.js`
  - `js/plantilla.js`
- `webpack.mix.js` sigue usando:
  - `.styles(...)`
  - `.scripts(...)`
  - `.js('resources/assets/js/app.js', 'public/js/app.js')`
  - `.vue({ version: 2 })`

### Dependencias auditadas

- `vue-select 2.5.0` sigue importado en reportes, pero en el codigo auditado no se detecto `v-select` renderizado en templates.
- `vue-barcode 1.1.0` sigue instalado, pero no se detecto uso real en `resources/assets/js`.
- `laravel-echo 1.4.0` y `pusher-js 4.3.1` siguen presentes y se usan desde `bootstrap.js`, pero su problema principal hoy es el sandbox realtime, no la compatibilidad directa con Vue.
- `bootstrap-sass 3.4.3` sigue como dependencia legacy de compatibilidad, mientras el runtime JS principal del shell sigue viniendo de `plantilla.js`.

## Bloqueadores de Vue 3

### Bloqueadores confirmados por codigo local

1. `window.Vue` global en `resources/assets/js/app.js`
2. registro global con `Vue.component(...)`
3. root instance con `new Vue({ el: '#app' })`
4. filtros vivos `toCurrency` en componentes sensibles
5. `vue-loader 15.11.1` y `vue-template-compiler 2.7.16`, que siguen anclados al carril Vue 2
6. `principal.blade.php` fijo y contrato de assets sin margen para una migracion simultanea de bundler
7. `vue-select 2.5.0` y `vue-barcode 1.1.0` como dependencias de linea Vue 2 o de uso no confirmado

### Bloqueadores de riesgo, no duros

1. `laravel-echo 1.4.0` y `pusher-js 4.3.1` siguen en una lane realtime no totalmente validada
2. la lane vendor `plantilla.js` mantiene globals legacy (`Chart`, jQuery, Bootstrap JS, swal)
3. el shell mezcla runtime npm y vendor concatenado

## Evaluacion de @vue/compat

### Evidencia oficial relevante

- La guia oficial de Vue 3 para migration build indica que, en webpack custom, hay que subir `vue-loader` a `^16.0.0`.
- La misma guia indica que hay que subir `vue` a `3.x`, instalar `@vue/compat`, reemplazar `vue-template-compiler` por `@vue/compiler-sfc`, aliasar `vue` a `@vue/compat` y habilitar `compatConfig`.
- La guia oficial tambien documenta que el migration build cubre `new Vue() -> createApp`.
- La guia oficial lista compat para `FILTERS` y `COMPILER_FILTERS`, lo que permite entrar a la migracion sin retirar todos los filtros el mismo dia.

Referencias:

- Vue migration build: https://v3-migration.vuejs.org/migration-build
- Global API application instance: https://v3-migration.vuejs.org/breaking-changes/global-api

### Conclusiones para este proyecto

`@vue/compat` si es una ruta viable para `centrodecobros`, pero no como simple bump de paquetes. La fase debe entrar como migracion controlada del bootstrap del runtime:

1. reemplazar `vue-template-compiler` por `@vue/compiler-sfc`;
2. subir `vue-loader` a `^16`;
3. instalar `@vue/compat`;
4. aliasar `vue` a `@vue/compat` en Mix/webpack;
5. mover el entrypoint a `createApp()`;
6. pasar registros globales a `app.component(...)`;
7. sostener temporalmente filtros con compat mientras se elimina la deuda residual.

## Evaluacion de Vite vs Mix 6

### Evidencia oficial relevante

- La documentacion de Laravel Mix `6` soporta alias de modulos (`mix.alias(...)`), lo que permite apuntar `vue` a `@vue/compat`.
- Mix `6` mantiene soporte directo para concatenacion y minificacion de archivos legacy mediante `.scripts()` y `.styles()`.

Referencias:

- Laravel Mix aliases: https://laravel-mix.com/docs/6.0/aliases
- Laravel Mix concatenation: https://laravel-mix.com/docs/6.0/concatenation-and-minification

### Conclusiones para este proyecto

Vite no es el movimiento correcto antes de Vue 3 por estas razones:

1. el build actual ya funciona en Node `22.22.1`;
2. el proyecto depende de una lane legacy `plantilla.js` / `plantilla.css` que Mix ya sostiene;
3. `principal.blade.php` sigue en carga estatica por rutas fijas;
4. introducir Vite antes agregaria un segundo eje de cambio sin reducir los bloqueadores reales del runtime Vue 2.

## Recomendacion unica

Ruta optima recomendada:

1. ejecutar primero una fase corta Laravel `11 -> 12`;
2. ejecutar despues la migracion Vue `2.7 -> 3` sobre Mix `6` usando `@vue/compat`;
3. dejar Vite para despues de estabilizar Vue 3, y solo si se acepta cambiar el contrato de assets.

## Dictamen GO / NO-GO

Dictamen: `GO condicionado`

El proyecto si puede abrir una fase Vue 3 con `@vue/compat`, pero solo si:

1. no se mezcla con Vite;
2. se acepta reescribir el bootstrap a `createApp`;
3. se resuelven dentro de la fase los puntos `vue-loader`, `@vue/compiler-sfc`, `@vue/compat`, filtros y dependencias Vue 2 residuales;
4. se preservan los nombres actuales de assets y `principal.blade.php`.

## Prerrequisitos de la siguiente fase

### Prerrequisitos tecnicos obligatorios

1. cerrar primero Laravel `11 -> 12`;
2. mantener PHP `8.2.x`;
3. conservar Mix `6` y `webpack 5` como bundler base;
4. planear migracion del entrypoint a `createApp`;
5. inventariar y limpiar `vue-select` importado pero no usado;
6. confirmar retiro o sustitucion de `vue-barcode` si sigue sin uso.

### Prerrequisitos de validacion

1. revalidar `route:list`, `schedule:list` y `phpunit`;
2. revalidar `npm ci`, `npm run development` y `npm run production`;
3. validar browser en dashboard, roles, transacciones, respuestas, SPEI y cargos recurrentes;
4. no usar realtime como criterio de bloqueo del core Vue 3 mientras siga sin sandbox aislado.

## Recomendacion sobre Laravel 12

Laravel `12` debe ir antes de Vue 3.

Razon tecnica:

- segun el release note oficial, Laravel `11` tuvo security fixes hasta `2026-03-12`;
- la fecha de este precheck es `2026-03-14`;
- el mismo release note indica que Laravel `12` soporta PHP `8.2 - 8.5` y es una release de cambios minimos.

Referencia:

- Laravel 12 release notes: https://laravel.com/docs/12.x/releases
