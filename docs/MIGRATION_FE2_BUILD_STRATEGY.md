# FE-2 Build Strategy

Ultima actualizacion: 2026-03-12  
Estado: `Discovery completado; recomendacion unica lista`

## Resumen ejecutivo

1. El build legacy actual sigue siendo reproducible en su baseline oficial `Node 8.17.0` / npm `6.13.4`.
2. El contrato actual de salida depende de tres artefactos estaticos cargados desde Blade:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
3. `app.js` se genera con `laravel-mix 2.0.0` sobre `webpack 3.10.0`; `plantilla.js` y `plantilla.css` se generan por tareas de concatenacion de Mix y no por entradas webpack independientes.
4. La ruta de menor riesgo para FE-2 implementacion es modernizar primero a `laravel-mix 6` + `webpack 5`, preservando por ahora el runtime Vue `2.5.x`, los nombres de assets y el wiring Blade actual.
5. Los gaps `Role.vue`, `ReporteSpei.vue`, `ReporteCargosRecurrentes.vue` y `/url` no son codigo muerto; quedan fuera de FE-2 y deben corregirse en FE-2B antes de cualquier migracion de runtime Vue.

## Estado actual del build

| Tema | Estado confirmado |
| --- | --- |
| Baseline oficial actual | `Node 8.17.0` / npm `6.13.4` |
| Runtime Vue | `2.5.13` |
| Build wrapper | `laravel-mix 2.0.0` |
| Bundler principal | `webpack 3.10.0` |
| Vue compiler | `vue-loader 13.7.1` + `vue-template-compiler 2.5.13` |
| Sass legacy | `node-sass 4.7.2` + `sass-loader 6.0.6` |
| Pipeline oficial validado | `npm ci` + `npm run dev` |
| Contrato Blade | `resources/views/principal.blade.php` carga assets de forma estatica; no usa `mix()` |

## Asset graph

### 1. Entrada webpack real

| Entrada | Dependencias directas | Salida |
| --- | --- | --- |
| `resources/assets/js/app.js` | `resources/assets/js/bootstrap.js`, Vue global, axios, Echo, componentes Vue registrados | `public/js/app.js` |

### 2. Concatenacion JS legacy

| Fuente | Archivos | Salida |
| --- | --- | --- |
| `resources/assets/plantilla/js` | `jquery.min.js`, `popper.min.js`, `bootstrap.min.js`, `Chart.min.js`, `pace.min.js`, `template.js`, `sweetalert2.all.js` | `public/js/plantilla.js` |

### 3. Concatenacion CSS legacy

| Fuente | Archivos | Salida |
| --- | --- | --- |
| `resources/assets/plantilla/css` | `font-awesome.min.css`, `simple-line-icons.min.css`, `style.css` | `public/css/plantilla.css` |

### 4. Acoplamientos funcionales relevantes

1. `resources/views/principal.blade.php` depende de que los tres assets mantengan exactamente esos nombres y rutas.
2. `Dashboard.vue` depende de `Chart` como global expuesto por `public/js/plantilla.js`.
3. `app.js` monta la app Vue sobre `#app` y conserva el wiring actual por `menu`.
4. `bootstrap.js` sigue inyectando globals legacy (`axios`, `Echo`, `Pusher`, `bootstrap-sass` JS).

### 5. Componentes registrados desde `app.js`

1. `rol`, `user`, `estado`, `ciudad`, `cliente`, `clienteconsolidar`, `clientedepurar`
2. `dashboard`, `notification`
3. `transaccion`, `respuesta`, `transacciondom`
4. `reporteligas`, `reporteligasdom`, `reportecargosrecurrentes`
5. `consultaspei`, `pagospei`, `cancelaspei`, `reportespei`

## Dependencias criticas del pipeline

| Dependencia | Rol real en el build | Observacion para FE-2 |
| --- | --- | --- |
| `laravel-mix 2.0.0` | Orquesta webpack y las tareas `.styles()` / `.scripts()` | La preservacion del contrato actual favorece seguir en Mix durante FE-2 |
| `webpack 3.10.0` | Empaqueta `app.js` | Obsoleto; es el principal objetivo tecnico de salida |
| `vue-loader 13.7.1` | Compila `.vue` con Vue `2.5.13` | Debe modernizarse sin cambiar todavia runtime Vue |
| `node-sass 4.7.2` | Toolchain Sass legado de Mix 2 | Puede retirarse en FE-2 sin tocar runtime Vue porque el contrato oficial actual no compila `resources/assets/sass/app.scss` |
| `bootstrap-sass 3.3.7` + jQuery | Dependencias globales de UI y DOM | Se preservan en FE-2; no se reescriben componentes |
| `laravel-echo 1.4.0` + `pusher-js 4.3.1` | Realtime en frontend | No cambiar contrato ni wiring en FE-2 |

## Confirmacion sobre `node-sass 4`

1. `webpack.mix.js` no genera ningun asset oficial desde `resources/assets/sass/app.scss`.
2. El contrato actual solo exige `public/css/plantilla.css`, que hoy sale de una concatenacion de CSS plano.
3. Con esa evidencia, el proyecto puede salir de `node-sass 4` en FE-2 sin cambiar todavia runtime Vue, siempre que se mantenga intacta la salida de `plantilla.css`.

## Opciones tecnicas evaluadas

| Opcion | Descripcion | Riesgo tecnico | Archivos tocados | Probabilidad de romper build | Compatibilidad con Laravel 11 | Facilidad de rollback |
| --- | --- | --- | --- | --- | --- | --- |
| A | Subir a `laravel-mix 6` + `webpack 5`, preservando Vue `2.x`, Blade y nombres de salida | Media | Media | Media | Alta | Alta |
| B | Reemplazar Mix por webpack moderno directo | Alta | Alta | Alta | Alta | Media |
| C | Crear un puente minimo previo a Vite sin cerrar aun la deuda de Mix | Media-alta | Media-alta | Media-alta | Media | Media |

## Analisis comparativo

### Opcion A: Mix 6 + webpack 5 preservando Vue 2.x

1. Reutiliza la expresion actual del build en `webpack.mix.js`.
2. Conserva el soporte natural a `.styles()` y `.scripts()`, que hoy son parte del contrato de `plantilla.js` y `plantilla.css`.
3. Reduce la cantidad de archivos a tocar respecto de reescribir el pipeline completo.
4. Permite rollback simple descartando la copia aislada y volviendo al baseline Node 8.

### Opcion B: webpack moderno sin Mix

1. Obliga a reimplementar manualmente las tareas de concatenacion de `plantilla.js` y `plantilla.css`.
2. Aumenta el numero de archivos de build y el riesgo de drift respecto del contrato Blade.
3. Hace mas caro el rollback porque FE-2 pasaria de "modernizar el wrapper" a "redisenar todo el pipeline".

### Opcion C: puente minimo antes de Vite

1. Introduce una capa transicional adicional sin cerrar todavia la deuda principal.
2. Duplica complejidad operativa entre baseline legacy y pipeline puente.
3. No mejora el riesgo de salida respecto de Opcion A y deja mas trabajo temporal que luego habria que retirar.

## Recomendacion unica

La ruta recomendada para FE-2 implementacion es la `Opcion A`: migrar el build a `laravel-mix 6` + `webpack 5`, preservando por ahora:

1. Vue `2.5.x` como runtime funcional actual.
2. `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css` como salidas oficiales.
3. `resources/views/principal.blade.php` sin cambios.
4. El orden de carga y los globals actuales, especialmente la dependencia de `Chart` desde `plantilla.js`.
5. El baseline Node `8.17.0` / npm `6.13.4` como carril de rollback.

## Prerequisitos para FE-2 implementacion

1. Trabajar en una nueva copia aislada, separada de esta lane de discovery.
2. Mantener documentados los hashes baseline de:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
3. Instalar y activar la lane moderna objetivo `Node 22.22.1` en el host antes de intentar la implementacion.
4. Mantener disponible la lane legacy `Node 8.17.0` / npm `6.13.4` para rollback y comparacion.
5. No mezclar FE-2 con FE-2B ni con Vue `2.7`.

## GO / NO-GO para FE-2 implementacion

### GO

1. Existe copia aislada nueva para FE-2 implementacion.
2. La lane `Node 22.22.1` esta instalada y puede activarse en local.
3. El contrato de salida sigue siendo exactamente:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
4. `resources/views/principal.blade.php` no cambia.
5. FE-2B sigue separado y no se intenta corregir funcionalidad viva dentro del cambio de build.

### NO-GO

1. Renombrar assets o cambiar a `mix()` / Vite en Blade.
2. Mezclar FE-2 con migracion de Vue `2.7` o Vue `3`.
3. Reescribir componentes `.vue` o UX para "aprovechar" el cambio de build.
4. Declarar valida la lane moderna sin comparacion contra el baseline Node 8.
5. Tratar `Role.vue`, `ReporteSpei.vue`, `ReporteCargosRecurrentes.vue` o `/url` como codigo muerto.

## Rollback conceptual

1. Descartar la copia aislada de FE-2 implementacion si el build moderno rompe el contrato.
2. Volver al baseline validado en `Node 8.17.0` / npm `6.13.4`.
3. Reutilizar los hashes documentados para confirmar recuperacion exacta de `app.js`, `plantilla.js` y `plantilla.css`.
4. No tocar esta lane de discovery ni la copia aprobada de Fase 4 mientras FE-2 no tenga `GO` formal.
