# Matriz de Entorno por Fase

Ultima actualizacion: 2026-03-24

## Entorno observado en el programa Fases 22-24

| Item | Valor observado |
| --- | --- |
| Workspace auditado | `C:\temp\centrodecobros_phase22_24_template_guest_hash_program` |
| Fuente clonada | `C:\temp\centrodecobros_phase21_verify_cleanup_assessment` |
| SO | Windows local |
| PHP | `8.2.24` |
| Composer | `2.7.3` |
| Laravel | `12.54.1` |
| Node | `20.20.0` |
| npm | `10.8.2` |
| npx | `10.8.2` |
| `php artisan route:list` | `OK`, `97` rutas |
| `php artisan schedule:list` | `OK`, `2` tareas |
| `php vendor/bin/phpunit` | `OK (23 tests, 134 assertions)` |
| `npm ci` | `OK`; `7` vulnerabilidades (`5 low`, `2 moderate`) heredadas del lane legacy |
| `npm run development` | `OK`; lane legacy segmentada y artefactos verificados |
| `npm run production` | `OK`; contrato publico preservado |
| Browser semiautomatico | `OK`; `/login`, `/main`, `/url`, dropdown de cuenta, dropdown de notificaciones, sidebar y modulos `Roles`, `Clientes`, `Usuarios`, `Reporte Ingresos SPEI` y `Reporte Ingresos por Cargos Recurrentes` con `0` errores y `0` warnings |
| Servicio MySQL local | `wampmysqld64` se levanto temporalmente para la bateria browser autenticada y luego se restauro a `Stopped` |
| Estado Phase 22-24 | `GO ejecutado` |

## Lockfiles y manifests auditados

| Archivo | Estado |
| --- | --- |
| `composer.json` | Coherente con Laravel `12` y PHP `^8.2` |
| `composer.lock` | Resuelve `laravel/framework v12.54.1` |
| `package.json` | Ya incluye Vite para `app.js` y mantiene la lane legacy separada |
| `package-lock.json` | `lockfileVersion=3`; fija `vite 7.3.1`, `laravel-vite-plugin 2.1.0`, `@vitejs/plugin-vue 6.0.5` y `esbuild 0.27.4` como dependencia directa |
| `vite.config.js` | Define la entrada `resources/assets/js/app.js`, plugin oficial Laravel y alias `vue` con compilador |
| `webpack.mix.js` | Se conserva como referencia historica del orden de `plantilla.*` y ahora refleja `template.js`, `template.shared.js`, `template.guest.js` y `template.ajax-hash.js` |
| `public/build/manifest.json` | Manifest activo de Vite para `app.js` |
| `public/js/app.js` | Bridge estable hacia el asset hasheado de Vite con validacion de manifest/assets |
| `public/js/plantilla.js` | Salida legacy dedicada fuera de Vite, ahora con `template.js` segmentado y residual ajax/hash encapsulado |
| `public/css/plantilla.css` | Salida legacy dedicada fuera de Vite, validada como no vacia |

## Versiones directas confirmadas desde lockfiles y paquetes instalados

### Composer

| Paquete | Version |
| --- | --- |
| `laravel/framework` | `v12.54.1` |
| `laravel/ui` | `v4.6.2` |
| `pusher/pusher-php-server` | `7.2.7` |
| `maatwebsite/excel` | `3.1.67` |
| `barryvdh/laravel-dompdf` | `v3.1.1` |
| `dompdf/dompdf` | `v3.1.5` |
| `phpunit/phpunit` | `11.5.55` |

### npm

| Paquete | Version |
| --- | --- |
| `esbuild` | `0.27.4` |
| `vue` | `3.5.30` |
| `@vue/compiler-sfc` | `3.5.30` |
| `@vitejs/plugin-vue` | `6.0.5` |
| `vite` | `7.3.1` |
| `laravel-vite-plugin` | `2.1.0` |
| `laravel-mix` | `6.0.49` |
| `laravel-echo` | `1.4.0` |
| `pusher-js` | `4.3.1` |
| `axios` | `1.13.6` |
| `jquery` | `3.7.1` |

## Salidas observadas del build vigente

### Desarrollo

| Archivo | Estado observado |
| --- | --- |
| `public/js/app.js` | Bridge estable generado desde Vite y validado |
| `public/js/plantilla.js` | Salida legacy dedicada y reproducible |
| `public/css/plantilla.css` | Salida legacy dedicada |

### Produccion

| Archivo | Estado observado |
| --- | --- |
| `public/js/app.js` | `1859` bytes; bridge estable |
| `public/build/assets/app-CrHxfLHs.js` | bundle Vite de produccion |
| `public/build/assets/app-BBf2Dnin.css` | CSS de produccion del lane Vue |
| `public/js/plantilla.js` | `405445` bytes |
| `public/css/plantilla.css` | `246986` bytes |
| `public/build/manifest.json` | `OK` |

## Browser auditado en Fases 22-24

| Flujo | Resultado |
| --- | --- |
| `/login` | `OK`; render limpio, `data-template-view="auth"`, `data-template-guest-ready="true"`, `$.ajaxLoad=false` |
| `/main` | `OK`; shell autenticado limpio y `menu = 0` al entrar |
| `/url` | `OK`; render limpio, `data-template-view="transaccion"`, `data-template-guest-ready="true"`, `$.ajaxLoad=false` |
| Dropdown de cuenta | `OK`; abre y expone `Cuenta` / `Cerrar sesion` sin Bootstrap dropdown legacy |
| Dropdown de notificaciones | `OK`; abre sin `data-toggle="dropdown"` legacy |
| Sidebar `Acceso` | `OK`; cierra y reabre sobre el DOM vivo |
| `Roles` | `OK`; render del modulo y `window.CentroDeCobrosVueRoot.menu = 4` |
| `Clientes` | `OK`; render del modulo y `window.CentroDeCobrosVueRoot.menu = 9` |
| `Usuarios` | `OK`; render del modulo y `window.CentroDeCobrosVueRoot.menu = 3` |
| `Reporte Ingresos SPEI` | `OK`; render del modulo y `window.CentroDeCobrosVueRoot.menu = 20` |
| `Reporte Ingresos por Cargos Recurrentes` | `OK`; render del modulo y `window.CentroDeCobrosVueRoot.menu = 25` |
| Residual ajax/hash | `OK`; `window.CentroDeCobrosLegacyTemplate.state.ajaxHashMode = "disabled"` en guest auditada |
| Consola | `0` errores y `0` warnings en `/login`, `/main`, `/url` y los modulos auditados |
| Globals | `Chart`, `swal`, `jQuery`, `axios`, `Echo`, `Pusher` presentes |

## Lane operativa y lane de rollback

| Tipo | Valor |
| --- | --- |
| Lane backend operativa | PHP `8.2.24` + Composer `2.7.3` + Laravel `12.54.1` |
| Lane frontend operativa actual | Node `20.20.0` + npm `10.8.2` + Vue `3.5.30` puro + Vite para `app.js` + lane legacy segmentada para `plantilla.*` |
| Lane frontend historica | Mix `6` / webpack `5` |
| Estado de la lane historica | Conservada solo como referencia y rollback tecnico |
| Lane de rollback inmediato | `C:\temp\centrodecobros_phase21_verify_cleanup_assessment` |

## Base de datos y testing

| Tema | Estado real |
| --- | --- |
| Fuente de verdad del schema | `database/centrodecobros.sql` |
| `database/migrations` | Artefacto historico, no canonico |
| Smoke suite real | `tests/Feature/Smoke` |
| `tests/smokes` | No existe en esta copia |
| Scripts locales reales | `scripts/local/build_legacy_lane.js`, `scripts/local/build_vite_bridge.js`, `scripts/local/run_phase15_build.js`, `scripts/local/run_mix_build.js` |
| Funcionalidad viva revalidada | `/login`, `/url`, `/role`, shell autenticado, reporte SPEI, reporte cargos recurrentes y rutas de exportacion |

## Compatibilidad para la siguiente secuencia

| Paso siguiente | Factibilidad en el host actual |
| --- | --- |
| Laravel `11 -> 12` | `CERRADO` |
| Vue `2.7 -> 3` con `@vue/compat` | `CERRADO` |
| Limpieza post-compat / `MODE: 3` | `CERRADO` |
| Salida de `@vue/compat` | `CERRADO` |
| Implementacion Vite incremental | `CERRADO`: Fase 15 ejecutada |
| Fase 18 integracion gradual del shell legacy de `plantilla.*` | `CERRADO`: ejecutado en `GO` |
| Fase 19 integracion puntual del shell restante | `CERRADO`: ejecutado en `GO` |
| Fase 20 integracion puntual de la navegacion shell hash/ajax restante | `CERRADO`: ejecutado en `GO` |
| Fase 21 limpieza verify/SMS heredado | `CERRADO`: ejecutado en `GO` |
| Fases 22-24 segmentacion `template.js`, guest lane viva y residual ajax/hash | `CERRADO`: ejecutado en `GO` |
| Fase 25 reduccion adicional de la guest/legacy lane viva | `GO`: siguiente fase recomendada |
