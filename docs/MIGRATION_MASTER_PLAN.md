# Plan Maestro de Modernizacion

Ultima actualizacion: 2026-06-03
Estado general: `Fases de modernizacion 8-35 cerradas; Laravel 12 estable; Vue 3 puro estable; Vite incremental estable para app.js; plantilla.* sigue fuera de Vite como deuda residual aceptada; repo vigente en main; produccion Docker funcionando segun propietario; pendientes vivos: sandbox Pagadetodo oficial, firma/origen de webhooks, realtime E2E, npm audit completo y documentacion del compose productivo`

## Objetivo rector

Modernizar y mantener `centrodecobros` sin alterar contratos de negocio externos, sin usar `database/migrations` como fuente unica de verdad y sin romper la produccion Docker actual.

## Reglas vigentes

1. La referencia primaria de schema es MySQL productivo o dump autorizado fuera de Git, mas el uso real en codigo.
2. Desde 2026-06-03 todo cambio se trabaja en `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`; no crear copias nuevas de fase salvo instruccion explicita posterior.
3. No se deben mezclar upgrades mayores de backend, runtime Vue y bundler en una sola fase.
4. `principal.blade.php` y el contrato `app.js` / `plantilla.js` / `plantilla.css` / `guest-public.js` siguen siendo restriccion estructural hasta una decision posterior explicita.
5. `Role.vue`, `ReporteSpei.vue`, `ReporteCargosRecurrentes.vue` y `/url` siguen tratandose como funcionalidad viva.
6. Las integraciones financieras y realtime solo se tocan con evidencia y validacion controlada.
7. No ejecutar migraciones, activar scheduler ni usar credenciales productivas para pruebas sin instruccion explicita.

## Baseline real consolidada despues de Fase 34

| Area | Estado real |
| --- | --- |
| Workspace actual | `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` |
| Estado Git | Repo activo en `main` con remoto GitHub configurado |
| Produccion | Docker funcionando en servidor; compose no versionado en repo |
| PHP observado en shell | `8.3.27` |
| Composer observado en shell | `2.2.6` |
| Laravel | `12.54.1` |
| `league/commonmark` | `2.8.2` |
| Node | `20.20.0` |
| npm | `10.8.2` |
| Vue | `3.5.30` puro |
| Lane `app.js` | `Vite 7.3.1` + `laravel-vite-plugin 2.1.0` + `@vitejs/plugin-vue 6.0.5` |
| Lane `plantilla.*` | script legacy dedicado fuera de Vite |
| Contrato publico vigente | `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css`, `public/js/guest-public.js` |
| Principal Blade | intacto respecto de Fase 29 |
| Smoke suite | `tests/Feature/Smoke` |
| Scripts locales vivos | `scripts/local/run_phase15_build.js`, `scripts/local/build_legacy_lane.js`, `scripts/local/build_guest_public_lane.js`, `scripts/local/build_vite_bridge.js`, `scripts/local/run_mix_build.js` |
| Resultado rector | `GO operativo para mantenimiento controlado en repo actual; pendientes externos no bloquean documentacion/cambios seguros` |

Nota del corte 2026-06-03: `node v20.20.0` y `npm 10.8.2` estan confirmados por `cmd.exe`; en bash directo `node -v` puede no estar disponible. La carpeta actual ya es checkout Git activo en rama `main`.

## Estado real por fase confirmada

| Fase | Estado real confirmado | Evidencia rectora |
| --- | --- | --- |
| Fase 10 | Laravel `12.54.1` estable sin alterar el baseline frontend | `docs/MIGRATION_PHASE_10_LARAVEL12.md` |
| Fase 13 | `@vue/compat` retirado; Vue `3.5.30` puro validado | `docs/MIGRATION_PHASE_13_VUE3_NO_COMPAT.md` |
| Fase 15 | Vite incremental implementado para `app.js`; `plantilla.*` sigue legacy | `docs/MIGRATION_PHASE_15_VITE_IMPLEMENTATION.md` |
| Fase 20 | Shell autenticado modernizado sin romper Blade ni assets publicos | `docs/MIGRATION_PHASE_20_SHELL_NAVIGATION_MODERN.md` |
| Fase 25 | `/login` y `/url` salen de `plantilla.js` hacia `public/js/guest-public.js` | `docs/MIGRATION_PHASE_25_GUEST_PUBLIC_EXTRACTION.md` |
| Fase 28 | Cierre de migracion dentro del alcance actual | `docs/MIGRATION_PHASE_28_MIGRATION_CLOSURE.md` |
| Fase 29 | Release readiness formal emitido en `GO con condiciones previas` | `docs/MIGRATION_PHASE_29_RELEASE_READINESS.md` |
| Fase 30 | Release candidate final emitido en `GO con condiciones adicionales`; `league/commonmark` queda en `2.8.2` y `composer audit` limpio | `docs/MIGRATION_PHASE_30_RELEASE_CANDIDATE.md` |
| Fase 31 | Estabilizacion funcional, accesos y seguridad; bloqueadores criticos corregidos y DB local documentada como bloqueo de Feature completos | `docs/MIGRATION_PHASE_31_ESTABILIZACION_FUNCIONAL_ACCESOS_SEGURIDAD.md` |
| Fase 32 | Pruebas funcionales, ownership y contratos API controlados; guards por recurso y mock Pagadetodo agregados; DB local sigue bloqueando Feature completos | `docs/MIGRATION_PHASE_32_PRUEBAS_OWNERSHIP_CONTRATOS_API.md` |
| Fase 33 | Entorno de pruebas y E2E controlado; Feature completos pasan con PHP WAMP/SQLite y browser guest/admin/cliente queda validado | `docs/MIGRATION_PHASE_33_ENTORNO_SANDBOX_E2E.md` |
| Fase 34 | Validacion Pagadetodo, webhooks e idempotencia; Service/* endurecido y Feature completo queda en verde | `docs/MIGRATION_PHASE_34_VALIDACION_PAGADETODO_WEBHOOKS_IDEMPOTENCIA.md` |
| Fase 35 | Preparacion GitHub/release sandbox; repo `main`, `.gitignore`, workflow CI y externalizacion de secretos | `docs/MIGRATION_PHASE_35_GITHUB_SANDBOX_RELEASE.md` |

## Confirmaciones operativas vigentes

| Item | Estado confirmado |
| --- | --- |
| `/login` | Sigue publicado y usa `public/js/guest-public.js` |
| `/url` | Sigue publicado y usa `public/js/guest-public.js` |
| `/main` | Sigue publicado y operativo |
| `Roles` | Sigue publicado y operativo |
| `Clientes` | Sigue publicado y operativo |
| `Usuarios` | Sigue publicado y operativo |
| `Reporte Ingresos SPEI` | Sigue publicado y operativo |
| `Reporte Ingresos por Cargos Recurrentes` | Sigue publicado y operativo |
| `verify`, `verifySMS`, `sendSMS` | Retirados y no reabiertos |
| `principal.blade.php` | Sigue cargando `css/plantilla.css`, `js/app.js` y `js/plantilla.js` por ruta fija |
| Contrato de assets | Preservado |

## Fase 29 - cierre operativo y release readiness

Estado: `cerrado`

1. La baseline de Fase 28 se revalido sin abrir una nueva migracion estructural.
2. Build, backend y browser real quedaron reproducibles en la copia aislada.
3. La fase documento predeploy, postdeploy, rollback operativo, deuda residual y riesgos vigentes.
4. `composer audit` abrio el advisory `CVE-2026-33347` en `league/commonmark`.
5. La conclusion formal de esa fase fue `GO con condiciones previas`.

## Fase 30 - release candidate final

Estado: `cerrado en GO con condiciones adicionales`

1. Se creo la copia `C:\temp\centrodecobros_phase30_release_candidate` desde `C:\temp\centrodecobros_phase29_release_readiness`.
2. `league/commonmark` subio de `2.8.1` a `2.8.2` sin mover `laravel/framework` fuera de `12.54.1`.
3. `composer audit` quedo limpio.
4. El contrato publico de assets se mantuvo intacto.
5. `principal.blade.php` se mantuvo intacto.
6. Browser real confirmo `/login`, `/url`, `/main`, topbar, sidebar y los modulos vivos auditados con `0` errores y `0` warnings.
7. La conclusion final ya no es `GO` incondicional:
   - el advisory de Composer quedo cerrado;
   - en ese corte seguian abiertas condiciones operativas sobre integraciones/realtime hardcoded y deuda residual npm; despues Fase 35 externalizo configuracion, pero realtime E2E y sandbox oficial siguen pendientes.

## Fase 31 - estabilizacion funcional, accesos y seguridad

Estado: `cerrado en GO tecnico parcial`

1. Se creo la copia `C:\temp\centrodecobros_phase31_estabilizacion_funcional_accesos_seguridad` desde `C:\temp\centrodecobros_phase30_release_candidate`.
2. El middleware `Administrador` dejo de ser permisivo y aplica matriz minima por rol.
3. `CancelarDomiciliacion` dejo de fallar por `$e` indefinido.
4. APIs SPEI, lector, domiciliacion y cargo recurrente tienen validaciones tempranas para evitar 500 por campos faltantes.
5. SPEI corrige filtros `ClientReference`.
6. `UserController` deja de seleccionar hash de password y solo actualiza password si se manda valor.
7. Pruebas unitarias y Feature aisladas pasan; suite completa sigue bloqueada por DB local.
8. El dictamen no habilita liberacion directa: quedan abiertos DB local, npm audit, ownership por registro y validacion externa Pagadetodo.

## Fase 32 - pruebas funcionales, ownership y contratos API controlados

Estado: `cerrado en GO tecnico parcial`

1. Se creo la copia `C:\temp\centrodecobros_phase32_pruebas_ownership_contratos_api` desde `C:\temp\centrodecobros_phase31_estabilizacion_funcional_accesos_seguridad`.
2. Se agregaron helpers compartidos para scopes de propietario, respuestas `403`, whitelists, paginacion acotada y mock Pagadetodo.
3. Clientes, archivos, transacciones, respuestas, SPEI, domiciliacion y exportaciones criticas aplican ownership por `idusuario` para rol cliente.
4. Las busquedas dinamicas criticas usan whitelists para evitar columnas arbitrarias.
5. Se agregaron Feature tests de matriz admin/cliente y contratos API, pero el host no puede ejecutarlos por falta de `pdo_sqlite`.
6. Se agregaron Unit tests sin DB para cubrir regresiones de guards, whitelists, exports y mock.
7. Browser guest fue validado en `/login`, `/` y `/main`; browser admin/cliente sigue bloqueado por MySQL local.
8. El dictamen no habilitaba liberacion directa: quedaban abiertos DB local, sandbox externo Pagadetodo, npm audit completo, secretos en codigo y webhooks sin firma/idempotencia.

## Fase 33 - entorno de pruebas, sandbox Pagadetodo y validacion E2E controlada

Estado: `cerrado en GO tecnico parcial fuerte`

1. Se creo la copia `C:\temp\centrodecobros_phase33_entorno_sandbox_e2e` desde `C:\temp\centrodecobros_phase32_pruebas_ownership_contratos_api`.
2. Se uso PHP WAMP `8.3.0` con `pdo_sqlite` como runner local funcional sin tocar produccion.
3. Se preparo `storage/phase33_browser.sqlite` con dataset minimo controlado y script local de regeneracion.
4. `vendor/bin/phpunit --testsuite Feature` queda en verde con 44 tests y 199 assertions.
5. Browser real valida guest, administrador y cliente con 0 errores de consola.
6. Se corrigieron compatibilidades de SQLite detectadas por tests/browser sin cambiar contratos externos.
7. El sandbox Pagadetodo oficial no se conecto por falta de credenciales/URL no productivas; se mantiene simulacion controlada.
8. El dictamen no habilitaba liberacion directa: quedaban abiertos sandbox oficial, webhooks/idempotencia, npm audit completo y secretos en codigo.

## Recomendacion posterior al cierre

| Paso | Objetivo | Estado recomendado |
| --- | --- | --- |
| Liberacion controlada | Ejecutar go-live solo cuando se cierren las condiciones adicionales de Fase 31 | Bloqueada temporalmente |
| Backlog residual | Tratar como backlog separado la deuda legacy aceptada (`plantilla.css`, layouts guest, residual ajax/hash opt-in, realtime E2E, vulnerabilidades npm heredadas y una futura modernizacion integral de `plantilla.*` si alguna vez se decide) | Recomendacion complementaria |
| Sandbox oficial Pagadetodo | Obtener credenciales/URL no productivas y comparar contra mocks | Bloqueado por falta de credenciales |
| GitHub/release | Repo limpio ya preparado en `main`; continuar con commits/pushes controlados desde la carpeta actual | Operativo |
| Produccion Docker | Documentar compose/servicios reales cuando se tenga acceso al servidor | Pendiente operativo |
| Secretos/integraciones | Credenciales externalizadas; falta provision/rotacion segura en servidor y sandbox oficial | Mitigado parcial |

## Corte de revalidacion 2026-06-02

Estado: `revalidado sin cambios funcionales`

1. `php artisan route:list` confirma 97 rutas.
2. `php artisan schedule:list` confirma 2 tareas: cargo recurrente diario y revision de status cada 5 minutos.
3. `php vendor/bin/phpunit --testsuite Unit` queda en verde con 13 tests y 72 assertions.
4. Feature aislado con WAMP PHP 8.3 + SQLite + mock Pagadetodo queda en verde con 44 tests y 199 assertions.
5. Feature contra `.env` MySQL local falla por acceso denegado a `centro_user`, no por una regresion demostrada del codigo.
6. `npm run production` queda en verde.
7. `npm audit --omit=dev` queda limpio; `npm audit` completo mantiene 29 hallazgos dev/tooling.
8. Este punto fue superado en Fase 35: la carpeta actual ya es repo Git en `main`, con `.gitignore` y workflow CI.

## Fase 34 - validacion Pagadetodo, webhooks e idempotencia

Estado: `cerrado en GO tecnico parcial fuerte`

1. Se creo la copia `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` desde `C:\temp\centrodecobros_phase33_entorno_sandbox_e2e`.
2. No se conecto sandbox oficial Pagadetodo por falta de credenciales/URL no productivas; se mantiene `PAGADETODO_MOCK=true`.
3. `RespuestaController` ahora valida payload minimo, evita llaves opcionales obligatorias y deduplica `Service/EntregarPagoLiga*` y `Service/EntregarPagoLector`.
4. `TransaccionController` corrige errores nulos en `ConsultaClabe` y agrega validacion/idempotencia a `PagoClabe` y `CancelaClabe`.
5. Se agrego `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`.
6. `vendor/bin/phpunit --testsuite Feature` queda en verde con 54 tests y 234 assertions usando WAMP PHP 8.3/SQLite.
7. `php vendor/bin/phpunit --testsuite Unit` queda en verde con 13 tests y 72 assertions.
8. `npm run production` queda en verde y el contrato de assets se preserva.
9. El dictamen no habilita cobro real ni liberacion directa: faltan sandbox oficial, firma/origen de webhooks, secretos y GitHub limpio.

## Rollback general vigente

El rollback futuro ya no es por descarte de copia aislada. Desde 2026-06-03 el trabajo vive en el repo actual y el rollback debe hacerse con Git y con el runbook Docker:

1. usar commits pequenos y trazables en `main`;
2. antes de deploy, registrar hash desplegado;
3. para revertir codigo, usar `git revert <commit>` o checkout del commit anterior en servidor segun pipeline;
4. para produccion Docker, seguir `docs/MIGRATION_DEPLOY_AND_ROLLBACK_RUNBOOK.md`;
5. no restaurar DB salvo analisis manual y respaldo, porque no deben ejecutarse migraciones productivas por defecto.
