# FE-3 - Precheck Vue 2.7

Ultima actualizacion: 2026-03-13

## Estado final

Resultado: `GO con salvedades`

El precheck FE-3 se ejecuto sobre `C:\temp\centrodecobros_phase6_fe2_build`, sin crear una nueva copia aislada porque en esta fase no se modificaron paquetes ni runtime; solo se hizo contrastacion documental, inventario tecnico y revalidacion del baseline vivo. La implementacion FE-3 si debe arrancar desde una nueva copia controlada.

## Objetivo logrado

Determinar si el estado posterior a FE-2 y FE-2B ya permite abrir una implementacion aislada del salto Vue `2.5.13 -> 2.7.x` sin romper:

1. `resources/views/principal.blade.php`
2. los nombres de salida `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css`
3. el wiring legacy validado en FE-2B

## Evidencia obligatoria usada

### Documentacion rectora

1. `docs/MIGRATION_MASTER_PLAN.md`
2. `docs/MIGRATION_FE2B_EXECUTION.md`
3. `docs/MIGRATION_PHASE_6_FE2_BUILD.md`
4. `docs/MIGRATION_NEXT_PROMPTS.md`
5. `docs/MIGRATION_RISK_REGISTER.md`
6. `docs/MIGRATION_DECISIONS_LOG.md`
7. `docs/MIGRATION_PACKAGE_COMPATIBILITY_MATRIX.md`
8. `docs/MIGRATION_FRONTEND_PRECHECK.md`

### Codigo real contrastado

1. `package.json`
2. `webpack.mix.js`
3. `scripts/local/run_mix_build.js`
4. `resources/assets/js/app.js`
5. `resources/assets/js/bootstrap.js`
6. `resources/assets/js/components/*.vue`
7. `node_modules/*/package.json` de los paquetes frontend criticos

## Confirmaciones de entrada

1. FE-2 sigue en `GO` sobre `laravel-mix 6.0.49` + `webpack 5`.
2. FE-2B ya quedo ejecutada en esta misma copia con `GO con salvedades`.
3. `php artisan route:list` sigue en `OK` con `100` rutas.
4. `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` sigue en `OK`.
5. `vendor\bin\phpunit tests/Feature/Smoke/LegacyFunctionalAlignmentSmokeTest.php` sigue en `OK (6 tests, 27 assertions)`.
6. `node -v` sigue en `v22.22.1`.
7. `npm -v` sigue en `10.9.4`.
8. `npm run dev` sigue cerrando correctamente con `scripts/local/run_mix_build.js`.

## Inventario real del frontend vivo

### Toolchain actual

| Tema | Estado real |
| --- | --- |
| Lane activa | `Node 22.22.1` / npm `10.9.4` |
| Build | `laravel-mix 6.0.49` |
| Bundler | `webpack 5.105.4` via lockfile FE-2 |
| Runtime Vue | `2.5.13` |
| Compilador Vue 2 | `vue-template-compiler 2.5.13` |
| Loader SFC | `vue-loader 15.11.1` |
| HTTP | `axios 0.17.1` |
| Realtime | `laravel-echo 1.4.0` + `pusher-js 4.3.1` |
| Select enriquecido | `vue-select 2.5.0` |
| Barcode | `vue-barcode 1.1.0` |

### Bootstrap actual de Vue

1. `resources/assets/js/app.js` sigue exponiendo `window.$`, `window.jQuery` y `window.Vue`.
2. El proyecto sigue registrando componentes por `Vue.component(...)` en bootstrap global.
3. Se detectan `20` componentes `.vue` registrados desde el mismo `app.js`.
4. El root sigue siendo `new Vue({ el: '#app' })`.
5. El filtro global `toCurrency` sigue vivo y es consumido por varias tablas/reportes.

### Acoplamientos globales que FE-3 no puede romper

1. `Chart` sigue llegando como global desde `public/js/plantilla.js` y `Dashboard.vue` lo consume directamente.
2. `swal` sigue siendo una global ampliamente usada por los componentes legacy.
3. `Echo.private(...)` sigue suscribiendo notificaciones desde el root Vue.
4. `window.axios` y `window.Pusher` siguen naciendo desde `resources/assets/js/bootstrap.js`.
5. La composicion Blade sigue dependiendo del orden de carga actual de `plantilla.js` y `app.js`.

### Uso real de dependencias sensibles

1. `vue-select` se usa solo en:
   - `resources/assets/js/components/ReporteLigas.vue`
   - `resources/assets/js/components/ReporteLigasDom.vue`
   - `resources/assets/js/components/ReporteCargosRecurrentes.vue`
   - `resources/assets/js/components/ReporteSpei.vue`
2. No se detecto uso real de `vue-barcode` en `resources/assets/js`.

## Evaluacion por dependencia

### `vue` + `vue-template-compiler`

Dictamen: `blocker controlado`

1. FE-3 solo es viable si ambos paquetes se actualizan juntos a la misma version exacta `2.7.x`.
2. No hay evidencia de que el proyecto dependa de APIs removidas dentro de Vue 2.x; el riesgo principal es de sincronizacion runtime/compiler, no de arquitectura.
3. La ruta recomendada es conservadora: subir solo runtime + compiler, sin tocar aun Vite ni Vue 3.

### `vue-loader 15.11.1`

Dictamen: `no bloquea FE-3`

1. Ya corre hoy con `webpack 5` y `laravel-mix 6`.
2. Su metadata local sigue declarando compatibilidad con `webpack ^5`.
3. El propio paquete mantiene su entorno de desarrollo sobre Vue `2.7.x`, lo que refuerza que es una ruta razonable para FE-3.

### `vue-select 2.5.0`

Dictamen: `no bloquea FE-3; sigue siendo deuda hacia Vue 3`

1. Su `peerDependencies` local declara `vue: 2.x`.
2. El uso real esta concentrado en cuatro reportes, no repartido por todo el sistema.
3. En FE-3 no se recomienda reemplazarlo preventivamente; solo validar esos cuatro flujos despues del upgrade de runtime.

### `vue-barcode 1.1.0`

Dictamen: `no bloquea FE-3; limpieza posterior`

1. No se encontro ningun uso real en `resources/assets/js`.
2. El paquete sigue instalado, pero hoy actua como deuda muerta potencial, no como requisito funcional.
3. Su retiro debe quedar fuera del salto a Vue `2.7.x`.

### `axios 0.17.1`, `laravel-echo 1.4.0` y `pusher-js 4.3.1`

Dictamen: `no bloquean FE-3; hardening separado`

1. Son dependencias antiguas y parte de `R-30`, pero no son el cuello de botella del salto `2.5.13 -> 2.7.x`.
2. El precheck no encontro acoplamiento directo entre esas versiones y la necesidad de mantener Vue `2.5.13`.
3. Deben quedarse fuera de FE-3 salvo que una incompatibilidad real de build o runtime obligue a tocarlas.

## Validacion ejecutada en este precheck

| Validacion | Resultado |
| --- | --- |
| `node -v` | `v22.22.1` |
| `npm -v` | `10.9.4` |
| `npm ls vue vue-template-compiler vue-loader vue-select vue-barcode axios laravel-echo pusher-js --depth=0` | OK |
| `npm run dev` | OK |
| `php artisan route:list` | OK; `100` rutas |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` | OK |
| `vendor\bin\phpunit tests/Feature/Smoke/LegacyFunctionalAlignmentSmokeTest.php` | `OK (6 tests, 27 assertions)` |
| Hash SHA-256 `resources/views/principal.blade.php` | `91287760C37DCA437CA12F8B3DAE385D4E6950A904EF38A6B0EBAA75755179E9` |
| Hash SHA-256 `public/js/plantilla.js` | `3C31EFB0DBB99914807C6B46980EE42FED63C7E8C2C890228B6C0EF73272FA8D` |
| Hash SHA-256 `public/css/plantilla.css` | `F960DD80201C42062DA3753308E7FD646C6B33C2DFA56D14494705AF58E4F306` |
| Hash SHA-256 `public/js/app.js` post-FE-2B | `FD73EF2806108EC0C066440269DF6BE2FA68BD8A1264A873DE9D92B62A54C68B` |

## Desalineaciones documentales detectadas

1. `docs/MIGRATION_PACKAGE_COMPATIBILITY_MATRIX.md` sigue siendo util como matriz de direccion tecnica, pero ya no representa el inventario vivo exacto de versiones backend/frontend posteriores a Fase 4 y FE-2.
2. `docs/MIGRATION_FRONTEND_PRECHECK.md` conserva correctamente la foto historica del frente legacy pre-FE-2B, pero sus gaps `/url`, `/role`, `exportarTransacciones` y `exportarReporteSpei` ya no describen el estado actual del codigo.
3. Para FE-3, la fuente viva de verdad debe ser: `package.json`, `node_modules/*/package.json`, `resources/assets/js/*` y el plan maestro actualizado.

## Dictamen GO / NO-GO

### `GO con salvedades`

FE-3 puede abrirse si se respetan estas condiciones:

1. crear primero una nueva copia aislada a partir de `C:\temp\centrodecobros_phase6_fe2_build`;
2. actualizar solo `vue` y `vue-template-compiler` en lockstep a la misma version exacta `2.7.x`;
3. mantener `laravel-mix 6`, `webpack 5`, `vue-loader 15.11.1`, `webpack.mix.js` y `scripts/local/run_mix_build.js` como primera ruta tecnica;
4. no cambiar `resources/views/principal.blade.php` ni el contrato de salida de assets;
5. revalidar especificamente `Dashboard.vue`, notificaciones realtime y los cuatro reportes que usan `vue-select`.

### `NO-GO`

FE-3 no debe arrancar si el cambio pretende:

1. mezclar Vue `2.7.x` con Vue `3`, Vite o limpieza mayor del frontend;
2. tocar simultaneamente `axios`, `jquery`, `laravel-echo`, `pusher-js` o dependencias de seguridad sin evidencia de bloqueo real;
3. reescribir el bootstrap global (`window.Vue`, `Vue.component`, `new Vue`, filtro `toCurrency`) en la misma fase;
4. cambiar el orden de carga de `plantilla.js` y `app.js`, o tratar `Chart` / `swal` / `Echo` como deuda removible en este mismo salto.

## Ruta tecnica recomendada para la implementacion FE-3

1. Crear una nueva copia aislada, por ejemplo `C:\temp\centrodecobros_phase7_fe3_vue27`, a partir de `C:\temp\centrodecobros_phase6_fe2_build`.
2. Fijar `vue` y `vue-template-compiler` a la misma version exacta `2.7.x`.
3. Mantener `vue-loader 15.11.1` en la primera iteracion; solo moverlo si el runtime `2.7.x` demuestra una incompatibilidad real.
4. Mantener `webpack.mix.js` sin introducir Vite ni cambiar `.styles()` / `.scripts()`.
5. Mantener el bootstrap de `resources/assets/js/app.js` basado en globals y registro explicito de componentes.
6. Correr `npm ci`, `npm run dev` y `npm run production`.
7. Revalidar:
   - `php artisan route:list`
   - `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1`
   - `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php`
   - validacion manual autenticada de dashboard, roles, reportes con `vue-select`, alta de transaccion y notificaciones
8. Solo si aparece una incompatibilidad real, documentar el parche minimo necesario en componentes o bootstrap.

## Criterio minimo de salida para FE-3 implementacion

1. `vue` y `vue-template-compiler` quedan sincronizados en `2.7.x`.
2. `npm run dev` y `npm run production` quedan en `OK` sobre `Node 22.22.1`.
3. `resources/views/principal.blade.php` sigue intacto.
4. Se preservan `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css`.
5. `Dashboard.vue` sigue consumiendo `Chart` sin cambio de contrato.
6. Los cuatro componentes con `vue-select` siguen funcionando.
7. FE-2B no presenta regresion visible en Roles, reportes ni `/url`.

## Rollback recomendado

1. Si FE-3 falla, descartar por completo la nueva copia aislada de implementacion.
2. Retomar `C:\temp\centrodecobros_phase6_fe2_build` como baseline aprobada de FE-2B.
3. Mantener `Node 8.17.0` / npm `6.13.4` como rollback documental del frente legacy historico.
4. No mezclar un rollback de FE-3 con limpieza de dependencias ni con cambios backend.

## Siguiente accion recomendada

1. Abrir la implementacion aislada de FE-3 sobre una nueva copia controlada.
2. Mantener FE-3 separado del hardening de dependencias frontend legacy.
3. Dejar FE-4 (evaluacion Vite) estrictamente despues del resultado real de FE-3.
