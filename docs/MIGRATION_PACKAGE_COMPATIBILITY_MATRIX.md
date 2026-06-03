# Matriz de Compatibilidad de Paquetes

Fecha de corte: 2026-03-11

## Criterio de lectura

- `Sí`: la versión actual declara o sugiere compatibilidad razonable.
- `No`: la versión actual no declara compatibilidad o hay evidencia fuerte en contra.
- `Parcial`: puede operar de manera indirecta, pero no es base segura.
- `Pendiente`: no hay evidencia suficiente; debe validarse antes de tocar producción.

## Backend

| Paquete | Versión actual | Rol en el sistema | Criticidad | Laravel 9 | Laravel 10 | Laravel 11 | Vue 3 / FE mayor | Estrategia sugerida | Evidencia/nota |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `laravel/framework` | `8.83.23` | Framework principal | Crítica | Objetivo del salto | Objetivo del salto | Objetivo del salto | N/A | Actualizar por fases | Ruta 8 -> 9 -> 10 -> 11 |
| `laravel/ui` | `3.4.6` | Auth scaffolding legacy | Media | Sí | No | No | N/A | Mantener en L9; después actualizar o retirar | El auth real es custom por `usuario`; parte del scaffold parece residual |
| `maatwebsite/excel` | `3.1.40` | Exportaciones e importación masiva | Crítica | Sí | No | No | N/A | Actualizar antes o durante L10 | La versión instalada declara soporte hasta L9 |
| `barryvdh/laravel-dompdf` | `0.8.7` | PDFs/reportes | Alta | No | No | No | N/A | Actualizar o encapsular; confirmar uso real | La versión instalada soporta hasta L8 |
| `pusher/pusher-php-server` | `3.3.1` | Broadcasting backend | Alta | No | No | No | Indirecto | Reemplazar por rama moderna antes de estabilizar realtime | Versión muy antigua; Laravel 9 exige rama más nueva |
| `telesign/telesign` | `3.0.0` | OTP/SMS | Alta | Sí | Sí | Sí | N/A | Mantener y validar con PHP objetivo | Sin acoplamiento Laravel directo; sigue siendo sensible por credenciales |
| `wildbit/swiftmailer-postmark` | `3.3.0` | Transporte de correo | Media-alta | Pendiente, asumir no | Pendiente, asumir no | Pendiente, asumir no | N/A | Reemplazar por estrategia mail moderna | Laravel 9 migra a Symfony Mailer |
| `guzzlehttp/guzzle` | `7.4.5` | HTTP cliente para proveedor/callbacks | Crítica | Sí | Sí | Sí | N/A | Mantener y retestar | SDK base para integraciones |
| `fideloper/proxy` | `4.4.2` | Proxies confiables | Media | Sí | No | No | N/A | Retirar/absorber en config nativa | Paquete abandonado |
| `laravel/helpers` | `1.5.0` | Helpers legacy | Media | Sí | No | No | N/A | Eliminar gradualmente | Paquete abandonado |
| `phpunit/phpunit` | `9.5.22` | Pruebas | Media | Sí | Sí | Sí | N/A | Mantener o ajustar según versión Laravel | El problema real no es el paquete sino la falta de tests útiles |

## Frontend y build

| Paquete | Versión actual | Rol en el sistema | Criticidad | Laravel 9 | Laravel 10 | Laravel 11 | Vue 3 / FE mayor | Estrategia sugerida | Evidencia/nota |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `vue` | `2.5.13` | Runtime principal UI | Crítica | Parcial | Parcial | Parcial | No | Subir primero a 2.7 | Puede seguir sirviéndose con bundles estáticos mientras no cambie el frontend |
| `vue-template-compiler` | `2.5.13` | Compilación SFC Vue 2 | Alta | Parcial | Parcial | Parcial | No | Reemplazar al mover runtime/toolchain | Atado a Vue 2 |
| `vue-loader` | `13.7.1` | Loader SFC | Alta | Parcial | Parcial | Parcial | No | Actualizar con el build | Muy antiguo para una ruta moderna |
| `laravel-mix` | `2.0.0` | Build frontend | Alta | Parcial | Parcial | Parcial | No | Reemplazar/actualizar antes de tocar Vue mayor | Runtime puede seguir, pero el toolchain no es mantenible |
| `webpack` | `3.10.0` | Bundler | Alta | Parcial | Parcial | Parcial | No | Salir de webpack 3 | Dependencia heredada de Mix 2 |
| `node-sass` | `4.7.2` | Compilación Sass | Alta | Parcial | Parcial | Parcial | No | Sustituir por `sass` moderno | Bloquea Node moderno |
| `bootstrap-sass` | `3.3.7` | Framework CSS legacy | Media-alta | Sí, indirecto | Sí, indirecto | Sí, indirecto | Parcial | Encapsular y reemplazar después | No es bloqueador del backend, sí del mantenimiento frontend |
| `jquery` | `3.3.1` | DOM legacy y plantilla | Alta | Sí, indirecto | Sí, indirecto | Sí, indirecto | Sí, coexistencia | Mantener temporalmente; encapsular | Sigue vivo en OTP y plantilla |
| `axios` | `0.17.1` | HTTP cliente frontend | Alta | Sí, indirecto | Sí, indirecto | Sí, indirecto | Parcial | Actualizar en ruta Vue 2.7 | Muy antiguo |
| `laravel-echo` | `1.4.0` | Realtime frontend | Media-alta | Sí, indirecto | Sí, indirecto | Sí, indirecto | Parcial | Actualizar junto con Pusher | No es bloqueador del backend, sí de mantenimiento |
| `pusher-js` | `4.3.1` | Realtime frontend | Media-alta | Sí, indirecto | Sí, indirecto | Sí, indirecto | Parcial | Actualizar junto con Echo | Frontend además tiene key/cluster hardcoded |
| `vue-select` | `2.5.0` | Selectores enriquecidos en reportes | Media-alta | Sí, indirecto | Sí, indirecto | Sí, indirecto | No | Actualizar o reemplazar antes de Vue 3 | Declara `peerDependencies` para Vue 2.x |
| `vue-barcode` | `1.1.0` | Código de barras | Baja | Sí, indirecto | Sí, indirecto | Sí, indirecto | Pendiente | Confirmar uso y retirar si aplica | No se detectó uso en código actual |
| `lodash` | `4.17.4` | Utilidades JS | Baja-media | Sí, indirecto | Sí, indirecto | Sí, indirecto | Sí | Mantener temporalmente | Antigua, pero no es el cuello de botella principal |

## Dependencias transversales y observaciones

| Tema | Estado | Estrategia |
| --- | --- | --- |
| Auth real vs scaffolding `laravel/ui` | Inconsistente | Preservar custom login y no confiar en scaffold legacy |
| Mail/Postmark | Alto riesgo desde Laravel 9 | Tratar como tema propio de fase, no accesorio |
| Broadcasting | Configuración dividida entre backend y frontend hardcoded | Homologar por ambiente antes de producción |
| Build actual sin `node/npm` en PATH | Bloqueo de Fase 1 | Preparar carril legacy y documentar versión operativa |
