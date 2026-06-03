# FE-4 - Evaluacion de Vite

Ultima actualizacion: 2026-03-14

## Estado final

Resultado: `NO-GO documentado`

FE-4 queda ejecutada como evaluacion formal del bundler en el workspace `C:\temp\centrodecobros_phase8_feh1_batch1`.

No se introdujo Vite, no se tocaron `package.json`, `package-lock.json` ni assets compilados, y no se modifico `resources/views/principal.blade.php`.

## Objetivo

Decidir si migrar de `laravel-mix 6` / `webpack 5` a Vite agrega valor real en el contrato frontend actual, preservando:

- `public/js/app.js`
- `public/js/plantilla.js`
- `public/css/plantilla.css`
- el shell Blade legacy
- el bootstrap global de Vue 2 y los globals de vendor assets

## Documentacion rectora usada

1. `docs/MIGRATION_MASTER_PLAN.md`
2. `docs/MIGRATION_FEH1_FRONTEND_HARDENING_PLAN.md`
3. `docs/MIGRATION_VAL_A1_BROWSER_REALTIME_PLAN.md`
4. `docs/MIGRATION_PHASE_6_FE2_BUILD.md`
5. `docs/MIGRATION_FE3_IMPLEMENTATION.md`
6. `docs/MIGRATION_FE3_POSTCHECK.md`
7. `docs/MIGRATION_SMOKE_TESTS.md`
8. `docs/MIGRATION_RISK_REGISTER.md`
9. `docs/MIGRATION_DECISIONS_LOG.md`
10. `docs/STACK_AND_DEPENDENCIES.md`
11. `docs/ENVIRONMENT_AND_OPERATION.md`
12. `docs/FRONTEND_ANALYSIS.md`

Ademas se releyo `docs/` completo y se contrastaron todos los documentos mediante inventario de archivos y busqueda transversal de referencias a `FE-4`, `Vite`, `laravel-mix`, `webpack.mix`, `principal.blade.php`, `app.js`, `plantilla.js`, `plantilla.css` y globals legacy.

## Codigo contrastado

1. `webpack.mix.js`
2. `resources/views/principal.blade.php`
3. `resources/assets/js/app.js`
4. `resources/assets/js/bootstrap.js`
5. `resources/assets/js/components/Dashboard.vue`
6. `resources/assets/plantilla/js/template.js`
7. `scripts/local/run_mix_build.js`
8. `package.json`

## Baseline tecnica observada

1. El build actual ya es reproducible en `Node 22.22.1` / npm `10.9.4`.
2. `laravel-mix 6.0.49` y `webpack 5` ya sustituyeron la deuda historica de `Mix 2` / `webpack 3`.
3. El proyecto no usa `vite` ni `@vitejs/plugin-vue`.
4. El shell Blade sigue cargando assets estaticos por ruta fija:
   - `css/plantilla.css`
   - `js/app.js`
   - `js/plantilla.js`
5. `principal.blade.php` sigue cargando `app.js` antes de `plantilla.js`.

## Hallazgos tecnicos

### 1. El problema original del bundler ya esta resuelto

FE-2 ya saco al proyecto de `Mix 2` / `webpack 3` y dejo una lane moderna estable sobre `Mix 6` / `webpack 5`.

Eso cambia la pregunta de FE-4: ya no se trata de desbloquear un build roto, sino de justificar una reescritura de pipeline que hoy no es necesaria para operar.

### 2. El contrato de assets sigue siendo estatico y no compatible con la integracion normal de Vite

`resources/views/principal.blade.php` sigue cargando assets por nombre fijo y no usa:

- `mix()`
- `@vite`
- manifest de build
- nombres hasheados

La ruta estandar de Laravel + Vite obligaria a cambiar Blade y la estrategia de resolucion de assets. Eso choca con las restricciones vigentes y con la regla historica de preservar el contrato `app.js` / `plantilla.js` / `plantilla.css`.

### 3. `plantilla.js` y `plantilla.css` no son entradas modernas; son una lane vendor concatenada

`webpack.mix.js` sigue usando `.styles()` y `.scripts()` para concatenar vendor assets legacy:

- `jquery.min.js`
- `popper.min.js`
- `bootstrap.min.js`
- `Chart.min.js`
- `pace.min.js`
- `template.js`
- `sweetalert2.all.js`

Vite puede empaquetar multiples entradas, pero no aporta una ganancia clara si primero hay que reimplementar esta lane como una imitacion del comportamiento actual, preservando orden, globals y nombre fijo de salida.

### 4. El shell autenticado sigue dependiendo de globals y del orden de carga actual

La evidencia actual confirma:

- `Dashboard.vue` consume `Chart` como global.
- `app.js` sigue montando `window.Vue`, `window.$`, `window.jQuery` y `Echo.private`.
- `template.js` sigue gobernando dropdowns, sidebar, toggles y clases del shell mediante jQuery.
- `bootstrap.js` sigue exponiendo `window.axios`, `window.Pusher` y `window.Echo`.

Mover el bundler sin cambiar este contrato no reduce realmente el acoplamiento principal del proyecto. Solo trasladaria el mismo problema a otra herramienta.

### 5. La ruta "Vite sin tocar Blade" tiene ROI pobre

La unica forma de sostener Vite sin tocar `principal.blade.php` seria forzar una emision custom que imite exactamente:

- nombres de archivo fijos
- orden de carga actual
- lane vendor concatenada
- globals legacy

Eso equivale a reconstruir en Vite un comportamiento que Mix 6 ya cumple hoy. El costo de migracion y revalidacion superaria el beneficio tecnico inmediato.

### 6. Las deudas mas relevantes ya no dependen del bundler

Tras FE-H1 y VAL-A1, las deudas principales abiertas son:

- `vue 2.7.16`
- `vue-template-compiler 2.7.16`
- `vue-loader 15.11.1`
- runtime realtime bloqueado por sandbox
- vendor assets fuera de npm

Cambiar a Vite no resuelve por si solo ninguno de esos puntos.

## Opciones evaluadas

### Opcion A: migracion estandar a Vite con `@vite`

Dictamen: `NO-GO`

Motivo:

- obliga a tocar `principal.blade.php`;
- rompe el contrato fijo de assets;
- introduce manifest o nombres hasheados no usados hoy por el shell.

### Opcion B: Vite emulando el contrato legacy sin tocar Blade

Dictamen: `NO-GO`

Motivo:

- requiere reimplementar la lane vendor de `plantilla.js` / `plantilla.css`;
- no reduce el acoplamiento a globals ni a orden de carga;
- deja poco beneficio neto frente a `Mix 6` ya operativo.

### Opcion C: mantener `Mix 6` / `webpack 5` como baseline oficial

Dictamen: `GO`

Motivo:

- ya compila en la lane operativa actual;
- preserva el contrato de assets y el shell legacy;
- evita mezclar FE-4 con FE-5 o con limpieza estructural de vendor assets.

## Decision final

FE-4 queda en `NO-GO documentado` para migrar a Vite dentro del contrato actual del proyecto.

La decision vigente es:

1. mantener `laravel-mix 6.0.49` + `webpack 5` como bundler oficial;
2. no abrir una migracion a Vite mientras `principal.blade.php` siga fijo y `plantilla.js` / `plantilla.css` sigan siendo lane vendor concatenada;
3. mover la siguiente fase interna a `FE-5`, evaluando Vue 3 contra el baseline real actual y no contra una hipotetica migracion de bundler.

## Condiciones para reabrir Vite en el futuro

Reabrir FE-4 solo si se acepta explicitamente:

1. modificar `resources/views/principal.blade.php` o la estrategia de resolucion de assets;
2. reemplazar o externalizar la lane vendor `plantilla.js` / `plantilla.css`;
3. desacoplar globals legacy criticos como `Chart`, `swal`, `Echo` y jQuery;
4. revalidar shell, dashboard, toggles, dropdowns, modales y reportes sensibles como una migracion mayor de frontend.

## Validaciones ejecutadas

1. Lectura completa de la documentacion rectora y operativa aplicable a FE-4.
2. Contraste directo de `webpack.mix.js`, `principal.blade.php`, `app.js`, `bootstrap.js`, `Dashboard.vue`, `template.js`, `package.json` y `run_mix_build.js`.
3. `npm ls laravel-mix webpack vite @vitejs/plugin-vue --depth=0` -> confirmado `laravel-mix@6.0.49` instalado y ausencia de `vite`.

No se reejecutaron `npm ci`, builds, `phpunit` ni browser probes porque FE-4 fue una evaluacion documental/tecnica sin cambios de codigo ni paquetes.

## Rollback

No aplica rollback tecnico porque no hubo cambios de runtime ni de dependencias.

La baseline de rollback frontend sigue siendo:

- `C:\temp\centrodecobros_phase7_fe3_vue27`

## Siguiente accion recomendada

1. Ejecutar `FE-5` como evaluacion formal de Vue 3, tomando `Mix 6` / `webpack 5` como baseline vigente.
2. Mantener la deuda realtime fuera de FE-5 salvo que primero exista sandbox/credenciales controladas.
3. Considerar una futura reapertura de Vite solo como migracion estructural separada, no como hardening ni como prerequisito inmediato de Vue 3.
