# Precheck FE-H1: Plan de Hardening Frontend Legacy

Ultima actualizacion: 2026-03-14  
Estado: `Precheck ejecutado; FE-H1-L1, FE-H1-L2 y FE-H1-L3 ejecutadas con GO y salvedades; FE-H1-L4 evaluada con NO-GO documentado; FE-H1-L5 ejecutada y FE-H1 cerrada con salvedades`  
Workspace base validado: `C:\temp\centrodecobros_phase7_fe3_vue27`

## Objetivo

Convertir los warnings observados en `npm ci` y el resultado real de `npm audit` del `2026-03-14` en una ruta de remediacion frontend segura, incremental y compatible con la baseline FE-3 ya postvalidada.

## Alcance

- Dependencias frontend directas y transitivas instaladas por `npm ci`.
- Vendor assets cargados fuera de npm pero consumidos por el runtime actual, en especial `resources/assets/plantilla/js/*`.
- Definicion de lotes, prerequisitos, riesgos, pruebas y criterio `GO / NO-GO`.

## Fuera de alcance

- Migrar a `Vite`.
- Migrar a `Vue 3`.
- Modificar `resources/views/principal.blade.php`.
- Hacer refactors cosmeticos o limpieza de componentes como objetivo principal.

## Evidencia usada

### Documentos rectores

- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_FE3_POSTCHECK.md`
- `docs/MIGRATION_FE3_IMPLEMENTATION.md`
- `docs/MIGRATION_SMOKE_TESTS.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/STACK_AND_DEPENDENCIES.md`

### Comandos y verificaciones ejecutados el 2026-03-14

- `npm audit --json`
- `npm ls --depth=0`
- `npm outdated --json`
- `npm explain axios lodash jquery bootstrap-sass vue vue-template-compiler vue-loader laravel-mix laravel-echo pusher-js vue-select`
- `php artisan --version`
- `php artisan route:list --json`
- `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1`
- `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1`
- `php vendor/bin/phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php`

### Baseline de entrada ya validada

- `Node 22.22.1` y `npm 10.9.4` como lane operativa actual.
- `npm ci`, `npm run development` y `npm run production` en verde.
- Browser validation manual en verde sobre modulos criticos y exportacion de `Ligas de pago`.
- `phpunit` en `OK (21 tests, 114 assertions)`.
- `route:list` en `100` rutas y `check_route_alignment.ps1` en `OK`.

## Estado actual tras FE-H1 cerrada

Las tablas de inventario y `npm audit` del precheck que aparecen mas abajo reflejan el corte inicial del `2026-03-14`, antes de ejecutar lotes.  
El estado vivo del workspace `C:\temp\centrodecobros_phase8_feh1_batch1` despues de `FE-H1-L1`, `FE-H1-L2`, `FE-H1-L3` y del cierre documental de `L5` es:

- `lodash` ya estaba en `4.17.23`.
- `follow-redirects` y `websocket-extensions` ya estaban fijados via `overrides` a `1.15.11` y `0.1.4`.
- `jquery` quedo convergido en `3.7.1` tanto en npm como en `resources/assets/plantilla/js/jquery.min.js`.
- `bootstrap-sass` quedo en `3.4.3`, pero el shell autenticado ya no lo inyecta como segundo runtime JS desde `resources/assets/js/bootstrap.js`.
- `axios` quedo actualizado a `1.13.6`, preservando el contrato global de `window.axios` desde `resources/assets/js/bootstrap.js`.
- `Cliente.vue` y `Transaccion.vue` ya no fuerzan manualmente `multipart/form-data` en los envios con `FormData`.
- `plantilla.js` mantiene `Bootstrap 4.0.0-beta` como unica fuente efectiva de Bootstrap JS en el shell autenticado, sin tocar `resources/views/principal.blade.php`.
- `npm audit` bajo a `13` vulnerabilidades (`7` low, `6` moderate, `0` high, `0` critical).
- `npm ci`, `npm run development`, `npm run production`, `check_baseline.ps1`, `check_route_alignment.ps1`, `route:list`, `schedule:list` y `phpunit` quedaron en verde despues de `L3`.
- La validacion browser autenticada confirmo shell, polling de notificaciones, `cliente`, `user`, `transaccion` y reportes sensibles; los downloads `CSV` y `XLSX` siguieron operativos y el submit `FormData` de importacion devolvio `422` controlado con archivo invalido.
- `FE-H1-L4` ya fue evaluada y quedo bloqueada en esta copia: el backend local resuelve `broadcasting.default=log`, el browser probe muestra `window.Echo` / `window.Pusher` presentes pero con `pusherState=disconnected`, y la captura de red no mostro `broadcasting/auth`.
- `resources/assets/js/bootstrap.js` sigue hardcodeando `key` y `cluster` de Pusher; esos valores coinciden con la configuracion backend vigente, por lo que habilitar `BROADCAST_DRIVER=pusher` en local para forzar la prueba conectaria a un servicio externo real sin lane aislada.
- `FE-H1-L5` ya quedo ejecutada como cierre documental del alcance: `vue 2`, `vue-template-compiler`, `vue-loader`, `laravel-mix`, `vue-select` y `vue-barcode` quedan formalmente diferidos fuera de FE-H1, y la reapertura de realtime pasa a depender de `VAL-A1`.

## Resultado real de `npm audit`

- Vulnerabilidades totales: `19`
- `7` low
- `8` moderate
- `3` high
- `1` critical

### Lectura tecnica del resultado

1. La deuda mas urgente y realmente accionable hoy no esta en `vue` sino en paquetes directos o transitivos que todavia permiten remediacion incremental sin romper la linea FE-3.
2. Varias sugerencias automaticas de `npm audit` no son aceptables en FE-H1 porque implican saltos mayores o cambios de linea tecnologica.
3. `npm audit` no cubre por si solo toda la deuda real del runtime, porque `principal.blade.php` sigue cargando `plantilla.js`, que concatena vendor assets fuera de npm.

## Inventario de dependencias directas y uso real

Informacion de versiones "latest" verificada contra el registro npm con `npm outdated --json` y `npm view` el `2026-03-14`.

| Paquete | Actual | Latest verificado | Tipo | Uso real detectado | Hallazgo principal | Clasificacion FE-H1 |
| --- | --- | --- | --- | --- | --- | --- |
| `axios` | `0.17.1` | `1.13.6` | Runtime directo | Global en `bootstrap.js`; CRUD, uploads, exports, blobs e importacion en casi todos los componentes Vue | Advisory `high` y dependencia deprecada; el salto es mayor | `GO condicional` en lote dedicado |
| `lodash` | `4.17.4` | `4.17.23` | Runtime directo | Solo se detecto `window._ = require('lodash')`; no hubo usos reales adicionales en `resources/assets/js` | Advisory `critical`; candidato claro a parche dentro de la misma linea | `GO` de bajo riesgo |
| `jquery` | `3.3.1` en npm | `4.0.0` latest; `3.7.1` linea compatible | Runtime directo | `app.js` usa `window.$`; `template.js` depende fuertemente de jQuery global; ademas `plantilla.js` concatena `jQuery 3.2.1` fuera de npm | Advisory `moderate`, deprecated y doble fuente de verdad en runtime | `GO condicional` solo como lote acoplado |
| `bootstrap-sass` | `3.3.7` | `3.4.3` | Runtime/build directo | `bootstrap.js` lo requiere; coexiste con `Bootstrap v4.0.0-beta` dentro de `plantilla.js` | Advisory `moderate`; mezcla de Bootstrap 3 y 4 en el runtime real | `GO condicional` solo como lote acoplado |
| `vue` | `2.7.16` | `3.5.30` | Runtime core | Todos los componentes Vue y el bootstrap global legacy | Advisory `low`, EOL y `fixAvailable` solo por salto mayor a `Vue 3` | `NO-GO` dentro de FE-H1 |
| `vue-template-compiler` | `2.7.16` | `2.7.16` | Build directo | Compilacion SFC Vue 2.7 | Advisory `moderate`; no hay release segura nueva dentro de la misma linea | `NO-GO` dentro de FE-H1 |
| `vue-loader` | `15.11.1` | `17.4.2` | Build directo | Compilacion `.vue` sobre Mix 6 / webpack 5 | Advisory `moderate` via transitivas; el salto sugerido es mayor | `NO-GO` dentro de FE-H1 |
| `laravel-mix` | `6.0.49` | `6.0.49` | Build directo | Pipeline oficial actual con `run_mix_build.js` | Advisories transitivos, pero sin ruta de fix directa desde Mix 6 | `NO-GO` dentro de FE-H1 salvo overrides compatibles |
| `laravel-echo` | `1.4.0` | `2.3.1` | Runtime directo | Canal privado `App.User.{id}` para notificaciones | Sin advisory directo actual, pero muy desactualizado | `GO condicional` solo junto con `pusher-js` |
| `pusher-js` | `4.3.1` | `8.4.0` | Runtime directo | Consumido via `laravel-echo`; activa notificaciones realtime | Arrastra `faye-websocket 0.9.4` y `websocket-extensions 0.1.3` | `GO condicional` en lote acoplado |
| `vue-select` | `2.5.0` | `3.20.4` | Runtime directo | Importado en 4 reportes, pero no se detectaron tags `<v-select>` en templates | No es driver principal del audit; es drift funcional documentado | `Diferir` |
| `vue-barcode` | `1.1.0` | `1.3.0` | Runtime directo | No hubo usos reales detectados | Deuda de inventario, no prioridad de seguridad actual | `Diferir` |

## Inventario de dependencias transitivas y deprecations

| Paquete | Version observada | Arrastrado por | Hallazgo | Ruta recomendada |
| --- | --- | --- | --- | --- |
| `follow-redirects` | `1.4.1` | `axios 0.17.1` y `http-proxy` de `webpack-dev-server` | Advisory `high`; ambos arboles aceptan una version mas nueva dentro del rango | Candidato a `override` o resolucion compatible en lote de bajo riesgo |
| `websocket-extensions` | `0.1.3` | `pusher-js` via `faye-websocket` y `webpack-dev-server` via `sockjs` | Advisory `high`; `websocket-driver` declara rango `>=0.1.1` | Candidato a `override` o parche compatible antes de tocar `pusher-js` |
| `@vue/component-compiler-utils` | `3.3.0` | `vue-loader 15.11.1` | Advisory `moderate`; ruta de salida real pasa por `vue-loader 17` | Diferir, fuera de FE-H1 |
| `postcss` | `7.0.39` bajo `@vue/component-compiler-utils` | `vue-loader 15.11.1` | Advisory `moderate`; no se corrige sin mover la linea de loader/compiler | Diferir, fuera de FE-H1 |
| `webpack-dev-server` | `4.15.2` | `laravel-mix 6.0.49` | Advisory `moderate`; afecta sobre todo el entorno de desarrollo | Mantener documentado; no mezclar con FE-4 |
| `node-libs-browser`, `crypto-browserify`, `browserify-sign`, `create-ecdh`, `elliptic` | varias | `laravel-mix 6.0.49` | Advisories `low` y deuda del pipeline | Mantener documentado hasta decision de bundler |
| `inflight`, `stable`, `consolidate`, `rimraf@3`, `glob@7`, `@babel/plugin-proposal-object-rest-spread` | varias | `laravel-mix` y `vue-loader` | Deprecations sin remediacion segura inmediata en esta fase | Documentar y no usar `npm audit fix --force` |

## Hallazgos clave

1. El audit de npm no refleja toda la superficie real del runtime porque `plantilla.js` concatena vendor assets fuera de `package.json`.
2. `jquery` y `bootstrap` no pueden endurecerse como paquetes aislados: hoy viven duplicados entre npm y vendor assets.
3. La mayor parte del riesgo FE-H1 ejecutable se puede atacar primero sin tocar `Vue 2`, `Mix 6` ni `principal.blade.php`.
4. `npm audit fix --force` es explicitamente `NO-GO` para este proyecto porque intentaria forzar saltos a `Vue 3`, `vue-loader 17` o rutas incompatibles con FE-3.

## Lotes propuestos

### FE-H1-L0: Gate de seguridad y rollback

- Objetivo: congelar la baseline FE-3 aprobada antes de tocar dependencias.
- Cambios de codigo: ninguno.
- Prerrequisitos:
  - `npm ci`, `npm run development`, `npm run production` en verde.
  - `check_baseline.ps1`, `check_route_alignment.ps1` y `phpunit` en verde.
  - Copia aislada nueva o snapshot de rollback del workspace.
- Pruebas obligatorias:
  - Las mismas de FE-3 postcheck.
  - Navegacion shell, dashboard, transacciones y reportes.
- Dictamen: `GO`.

### FE-H1-L1: Parches y overrides de menor riesgo

- Objetivo: bajar primero el riesgo mas severo sin tocar contratos mayores de runtime.
- Alcance propuesto:
  - Subir `lodash` a la ultima version segura de la misma linea (`4.17.23` al corte del `2026-03-14`).
  - Probar `overrides` o resolucion equivalente para `follow-redirects` y `websocket-extensions` a versiones parchadas compatibles.
- Justificacion:
  - `lodash` tiene advisory `critical` y no presenta uso real adicional detectado.
  - `follow-redirects` y `websocket-extensions` muestran rangos compatibles que permiten una remediacion incremental sin saltos mayores.
- Riesgo:
  - Bajo para `lodash`.
  - Bajo a medio para overrides transitivos, porque tocan flujo HTTP y websocket.
- Pruebas obligatorias:
  - `npm ci`
  - `npm run development`
  - `npm run production`
  - `php vendor/bin/phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php`
  - Browser smoke de listados, exportaciones, importacion de ligas y notificaciones.
- Estado real en `phase8_feh1_batch1`:
  - `package.json` y `package-lock.json` ya traian `lodash 4.17.23`.
  - `follow-redirects 1.15.11` y `websocket-extensions 0.1.4` ya estaban aplicados via `overrides`.
  - `npm ls` confirmo esas versiones efectivamente instaladas antes de abrir `L2`.
- Dictamen actual: `GO ejecutado`.

### FE-H1-L2: Convergencia controlada de `jquery` y `bootstrap`

- Objetivo: tratar el runtime real, no solo el arbol npm.
- Alcance propuesto:
  - Actualizar el `jquery` de npm a una version segura de la linea `3.x`.
  - Inventariar y alinear el `jQuery 3.2.1` actualmente concatenado en `resources/assets/plantilla/js/jquery.min.js`.
  - Evaluar si `bootstrap-sass 3.3.7 -> 3.4.3` es viable sin romper la convivencia actual con `Bootstrap 4.0.0-beta` dentro de `plantilla.js`.
- Justificacion:
  - Un upgrade solo en npm dejaria intacta la copia de vendor que hoy sigue cargandose en el runtime.
  - `template.js` depende de jQuery global y el shell usa dropdowns, toggles y modales.
- Riesgo:
  - Medio a alto.
  - Toca comportamiento visual y de interaccion del shell, sidebar, navbar y modales.
- Pruebas obligatorias:
  - Navegacion completa del shell.
  - Dropdown de usuario, sidebar, toggles y modales CRUD.
  - Pantallas `main`, `dashboard`, `transaccion`, `respuesta`, `cliente`, `user`.
- Dictamen:
  - `NO-GO` si se pretende actualizar solo el paquete npm o solo `bootstrap-sass`.
  - `GO condicional` si se ejecuta como convergencia acoplada y con validacion visual dirigida.

#### Resultado ejecutado en `C:\temp\centrodecobros_phase8_feh1_batch1`

- Cambios aplicados:
  - `jquery` de npm: `3.3.1 -> 3.7.1`.
  - Vendor `resources/assets/plantilla/js/jquery.min.js`: `jQuery 3.2.1 -> 3.7.1`.
  - `bootstrap-sass`: `3.3.7 -> 3.4.3`.
  - `resources/assets/js/bootstrap.js` deja de ejecutar `require('bootstrap-sass')`, para eliminar el segundo runtime Bootstrap JS del shell autenticado.
- Resultado tecnico:
  - Se cierra la doble fuente de verdad de jQuery entre npm y vendor assets.
  - El shell autenticado conserva `Bootstrap 4.0.0-beta` desde `plantilla.js` como unica fuente real de Bootstrap JS, sin tocar Blade ni reordenar assets.
  - El upgrade del vendor `bootstrap.min.js` permanece `NO-GO` dentro de este lote; no se forzo un cambio mayor sobre esa linea.
- Validacion ejecutada:
  - `npm ci` -> `OK`
  - `npm run development` -> `OK`
  - `npm run production` -> `OK`
  - `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1` -> `OK`
  - `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` -> `OK`
  - `php artisan route:list` -> `OK`
  - `php artisan schedule:list` -> `OK`
  - `php vendor/bin/phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php` -> `OK (21 tests, 114 assertions)`
  - Browser validation autenticada en `http://127.0.0.1:8010/main` -> shell, notificaciones, dropdown de usuario, sidebar, `cliente`, `user`, `transaccion`, `respuesta`, `transaccionDom`, `Reporte Ingresos por Ligas de Pago` y `Reporte Ingresos SPEI` operativos; consola `0` errores.
- Delta de `npm audit` atribuible al lote:
  - Antes de `L2`: `16` vulnerabilidades (`7` low, `8` moderate, `1` high, `0` critical).
  - Despues de `L2`: `14` vulnerabilidades (`7` low, `6` moderate, `1` high, `0` critical).
  - Quedan fuera del audit directo `jquery` y `bootstrap-sass`; la deuda principal pendiente pasa a `axios`, `vue`, `vue-template-compiler`, `vue-loader` y transitivas de `laravel-mix`.
- Salvedad de validacion publica:
  - El smoke publico sobre `/verify/1` devolvio `500` por desalineacion preexistente entre `TransaccionController@showVerifyForm` y `resources/views/verificar/verify.blade.php` (`transaccion` vs `$participante`).
  - Ese hallazgo no se considero regresion de `L2` porque falla antes de ejecutar el JavaScript compartido de `plantilla.js`.
- Dictamen actual: `GO ejecutado con salvedades`.

### FE-H1-L3: Modernizacion de `axios`

- Objetivo: retirar `axios 0.17.1` como dependencia directa deprecada y vulnerable.
- Alcance propuesto:
  - Upgrade controlado a `axios 1.13.6` o release equivalente vigente al momento de ejecucion.
  - Revisar diferencias en manejo de errores, serializacion, blobs, headers y `FormData`.
- Justificacion:
  - `axios` esta en el centro del runtime Vue actual.
  - La remediacion completa del paquete requiere un salto mayor, no solo un override transitorio.
- Riesgo:
  - Medio a alto.
  - Afecta listados, CRUD, importaciones y exportaciones.
- Pruebas obligatorias:
  - CRUD de catalogos y usuarios.
  - Busqueda y exportacion de `Ligas de pago`.
  - Importacion de ligas.
  - Reportes con descarga de archivos.
  - Validacion de errores y mensajes `swal`.

#### Resultado ejecutado en `C:\temp\centrodecobros_phase8_feh1_batch1`

- Cambios aplicados:
  - `axios` de npm: `0.17.1 -> 1.13.6`.
  - `resources/assets/js/components/Cliente.vue` y `resources/assets/js/components/Transaccion.vue` dejan de forzar manualmente `multipart/form-data` al usar `FormData`.
- Resultado tecnico:
  - Se elimina el advisory directo y la deprecacion visible de `axios`.
  - `resources/assets/js/bootstrap.js` conserva el contrato global de `window.axios`, `X-Requested-With` y `X-CSRF-TOKEN`.
  - `follow-redirects` permanece fijado via `overrides` a `1.15.11`.
- Validacion ejecutada:
  - `npm ci` -> `OK`
  - `npm run development` -> `OK`
  - `npm run production` -> `OK`
  - `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1` -> `OK`
  - `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` -> `OK`
  - `php artisan route:list` -> `OK`
  - `php artisan schedule:list` -> `OK`
  - `php vendor/bin/phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php` -> `OK (21 tests, 114 assertions)`
  - Browser validation autenticada en `http://127.0.0.1:8010/main` -> polling `POST /notification/get`, listados `user`, `cliente`, `transaccion`, export general `transacciones.csv`, export filtrado `reporteTransacciones.xlsx` y submit `FormData` de `/transaccion/importar/iniciar` con `422` controlado sobre archivo invalido.
- Delta de `npm audit` atribuible al lote:
  - Antes de `L3`: `14` vulnerabilidades (`7` low, `6` moderate, `1` high, `0` critical).
  - Despues de `L3`: `13` vulnerabilidades (`7` low, `6` moderate, `0` high, `0` critical).
- Salvedad de validacion:
  - La unica entrada de consola observada en la pasada browser fue el `422` esperado de `/transaccion/importar/iniciar` al enviar un archivo invalido de prueba; se considero una respuesta controlada del backend y no una regresion de `axios 1.x`.
- Dictamen actual: `GO ejecutado con salvedades`.

### FE-H1-L4: Lote de realtime `laravel-echo` + `pusher-js`

- Objetivo: tratar la obsolescencia del stack de notificaciones sin mezclarla con Vue 3.
- Alcance propuesto:
  - Evaluar upgrade conjunto de `laravel-echo` y `pusher-js`.
  - Verificar si el lote elimina la dependencia vieja de `faye-websocket` o vuelve innecesario el override de `websocket-extensions`.
- Justificacion:
  - `pusher-js 4.3.1` trae una cadena websocket envejecida.
  - El proyecto usa un patron simple de `Echo.private`, lo que hace viable un upgrade controlado si existe sandbox de validacion.
- Riesgo:
  - Medio.
  - El fallo no rompe todo el sistema, pero si rompe notificaciones.
- Pruebas obligatorias:
  - Carga inicial de notificaciones con `axios.post('notification/get')`.
  - Suscripcion al canal privado `App.User.{id}`.
  - Verificacion de ausencia de errores de consola y websocket.
- Dictamen base: `GO condicional`.

#### Resultado evaluado en `C:\temp\centrodecobros_phase8_feh1_batch1`

- Verificaciones ejecutadas:
  - `php artisan tinker` confirmo `broadcasting.default=log`, con configuracion `pusher` poblada y `cluster us2`.
  - Probe browser autenticado sobre `http://127.0.0.1:8010/main` confirmo `window.Echo` y `window.Pusher` activos, suscripcion al canal `private-App.User.1` y estado de conexion `disconnected`.
  - La captura de red del probe solo mostro `POST /notification/get` y navegacion shell; no aparecio `broadcasting/auth` ni trafico websocket autenticado.
- Lectura tecnica:
  - El backend sigue emitiendo `database` + `broadcast` en `NotifyAdmin`, pero la lane local vigente no tiene baseline websocket end-to-end en verde.
  - El frontend mantiene `key` y `cluster` hardcoded en `resources/assets/js/bootstrap.js`; esos valores coinciden con la configuracion backend real, por lo que cambiar a `BROADCAST_DRIVER=pusher` solo para validar `L4` abriria una dependencia externa no aislada.
  - El polling de `notification/get` sigue cubierto y verde, pero no sustituye la validacion realtime real requerida por este lote.
- Dictamen real del workspace: `NO-GO documentado`.
- Criterio para reabrir `L4`:
  - disponer de sandbox o lane aislada con credenciales controladas;
  - validar `broadcasting/auth`, canal privado `App.User.{id}` y ausencia de errores websocket en navegador real;
  - no reutilizar por defecto las credenciales productivas/hardcodeadas del runtime actual.

### FE-H1-L5: Items explicitamente diferidos

| Item | Motivo de diferimiento | Fase natural |
| --- | --- | --- |
| `vue 2.7.16` | EOL y advisory que solo se cierra con `Vue 3` | `FE-5` |
| `vue-template-compiler 2.7.16` | No hay linea segura nueva dentro de Vue 2 | `FE-5` |
| `vue-loader 15.11.1` | El salto sugerido es mayor y cambia la linea del compilador | `FE-4` o `FE-5` |
| `laravel-mix 6.0.49` y transitivas de bundler | No hay fix directa compatible y mezclar esto con bundler migration ampliaria el alcance | `FE-4` |
| Limpieza de `vue-select` y retiro de `vue-barcode` | No son el driver principal de seguridad y se consideran limpieza controlada, no remediacion urgente | Hardening posterior |

#### Resultado ejecutado en `C:\temp\centrodecobros_phase8_feh1_batch1`

- Resultado documental:
  - `vue 2.7.16`, `vue-template-compiler 2.7.16`, `vue-loader 15.11.1` y la deuda estructural de `laravel-mix 6.0.49` quedan formalmente transferidos a `FE-4` / `FE-5`.
  - `laravel-echo 1.4.0` y `pusher-js 4.3.1` no se mueven dentro de FE-H1; cualquier reapertura de esa deuda depende primero de `VAL-A1`.
  - `vue-select 2.5.0` y `vue-barcode 1.1.0` quedan como limpieza posterior, no como objetivo de hardening de este workspace.
- Evidencia de salida:
  - `docs/MIGRATION_VAL_A1_BROWSER_REALTIME_PLAN.md` creado como siguiente carril operativo.
  - `docs/MIGRATION_MASTER_PLAN.md`, `docs/MIGRATION_RISK_REGISTER.md`, `docs/MIGRATION_DECISIONS_LOG.md`, `docs/MIGRATION_CHANGELOG.md`, `docs/MIGRATION_NEXT_PROMPTS.md`, `docs/STACK_AND_DEPENDENCIES.md` y documentos operativos asociados sincronizados al cierre real de FE-H1.
- Dictamen actual de `L5`: `GO ejecutado`.
- Salida de FE-H1: `Cerrada con salvedades`.

## GO / NO-GO por categoria

| Categoria | Dictamen |
| --- | --- |
| `npm audit fix --force` | `NO-GO` |
| Parche directo de `lodash` | `GO` |
| Overrides compatibles para `follow-redirects` y `websocket-extensions` | `GO condicional` |
| Convergencia `jquery` npm + vendor a `3.7.1` | `GO ejecutado en L2` |
| Retiro del segundo runtime Bootstrap JS desde `bootstrap.js` | `GO ejecutado en L2` |
| Upgrade solo del `jquery` de npm | `NO-GO` |
| Upgrade aislado solo de `bootstrap-sass` | `NO-GO` |
| Convergencia acoplada `jquery` + vendor assets + revisita de `bootstrap` | `GO condicional` |
| Upgrade mayor de `axios` | `GO ejecutado en L3` |
| Upgrade conjunto `laravel-echo` + `pusher-js` | `Diferido; reabrir solo fuera de FE-H1 despues de VAL-A1` |
| Cierre documental de `L5` y aceptacion formal de diferidos | `GO ejecutado` |
| Cierre de advisories de `vue`, `vue-template-compiler`, `vue-loader` o `laravel-mix` mediante upgrades mayores | `NO-GO` en FE-H1 |

## Secuencia recomendada

1. `FE-H1-L0` y `FE-H1-L1` ya quedaron ejecutadas en esta copia.
2. `FE-H1-L2` ya quedo ejecutada como convergencia acoplada de `jquery` y reduccion del runtime duplicado de Bootstrap JS.
3. `FE-H1-L3` ya quedo ejecutada sobre `axios 1.13.6` con validacion tecnica y browser dirigida.
4. `FE-H1-L4` ya fue evaluada y quedo `NO-GO` en este workspace; solo debe reabrirse con sandbox realtime y validacion websocket end-to-end.
5. `FE-H1-L5` ya fue ejecutada como cierre documental y formalizacion de diferidos.
6. La siguiente accion optima ya no pertenece a FE-H1: es `VAL-A1` o una lane equivalente de validacion browser/realtime aislada.
7. `FE-4` y `FE-5` retoman la deuda mayor hoy aceptada fuera de hardening incremental.

## Resultado esperado de FE-H1

- Reducir primero advisories `critical` y `high` realmente remediables sin cambiar de bundler ni de framework.
- Salir de la dependencia directa mas expuesta y deprecada sin perder reproducibilidad.
- Dejar documentado el residual aceptado: `Vue 2`, su compilador y el pipeline Mix 6 seguiran siendo deuda formal hasta que exista decision separada de `FE-4` y `FE-5`.
- Estado real tras `L5` ejecutada: FE-H1 queda cerrada con salvedades; el shell autenticado ya no duplica jQuery entre npm y vendor, ni Bootstrap JS entre `bootstrap-sass` y `plantilla.js`, y `axios` ya quedo en `1.13.6`; la deuda principal abierta pasa a `Vue 2`, `vue-template-compiler`, `vue-loader`, `laravel-mix` y realtime, pero ya no pertenece a nuevos lotes FE-H1 en esta copia.
