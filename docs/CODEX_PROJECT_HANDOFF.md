# Handoff Canonico Del Proyecto Centro de Cobros

Ultima revision: 2026-08-04

Alcance: auditoria integral de codigo propio, configuracion, manifiestos, rutas, migraciones, pruebas, documentacion, historial Git visible y contexto confirmado por el propietario.

Documento rector para continuidad: este archivo sustituye como estado vigente a los snapshots fechados anteriores, sin borrar su valor historico.

## 1. Identificacion

| Dato | Estado |
| --- | --- |
| Proyecto | Centro de Cobros |
| Repositorio | `centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` |
| Origen preservado | `/mnt/c/temp/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` |
| Destino canonico WSL | `/home/ccortesg/workspace/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` |
| Distribucion WSL | `Ubuntu` |
| Rama | `main` |
| HEAD auditado | `4299d1984e2e75cbea5a2275ef9e9d79224519f5` |
| Remoto | `origin` -> `https://github.com/ccortesg/centrodecobros2.git` |
| Produccion | Docker en `/var/www/centrodecobros2`, reportado por el propietario |

## 2. Resumen ejecutivo

- [VERIFICADO EN ESTA EJECUCION] El checkout esta limpio antes de la documentacion, en `main`, alineado con `origin/main`, con 390 archivos rastreados, tres etiquetas de release sandbox y sin stashes ni submodulos.
- [VERIFICADO EN ESTA EJECUCION] Laravel carga 122 rutas. El scheduler registra cargos recurrentes diarios a las 07:00, revision SPEI cada cinco minutos y reconciliacion diaria de estados a las 00:05 de Hermosillo.
- [VERIFICADO EN ESTA EJECUCION] El codigo propio PHP tiene sintaxis valida en 169 archivos; Composer valida y la suite Unit pasa con 28 tests y 171 aserciones.
- [VALIDADO POR EL USUARIO] Las llamadas Pagadetodo fueron probadas exitosamente desde el servidor tanto en sandbox como en productivo. No son reproducibles localmente por la restriccion de IP de origen del proveedor.
- [EVIDENCIA EN CODIGO] La aplicacion cubre ligas de pago, domiciliacion, cargos recurrentes, SPEI, terminal, respuestas, pagos recibidos, clientes, reportes, auditoria de integraciones y webhooks salientes configurables.
- [EVIDENCIA EN CODIGO] La principal deuda esta en controladores financieros monoliticos, esquema historico no reproducible solo con migrations, autenticacion API legacy, concurrencia de folios/cargos y reglas de ownership inconsistentes en algunos caminos API.
- [PENDIENTE DE VALIDACION] No se hizo UAT con MySQL, browser smoke, build frontend ni trafico externo en esta auditoria. Tampoco se ejecutaron migrations.

El estado es apto para continuidad tecnica en WSL, pero no equivale por si solo a una certificacion financiera o de despliegue productivo.

## 3. Jerarquia de evidencia

1. Codigo y configuracion actuales.
2. Comandos reproducibles de esta auditoria.
3. Estado e historial Git.
4. Validaciones explicitamente reportadas por el propietario.
5. Documentacion vigente.
6. Documentos historicos y supuestos.

Las etiquetas usadas en este documento son: `[VERIFICADO EN ESTA EJECUCION]`, `[VALIDADO POR EL USUARIO]`, `[EVIDENCIA EN CODIGO]`, `[PENDIENTE DE VALIDACION]`, `[VALIDACION FALLIDA]`, `[DOCUMENTACION HISTORICA]` y `[OBSOLETO]`.

## 4. Proposito y alcance funcional

Centro de Cobros administra clientes y genera/monitorea instrumentos de cobro para varios canales. Persiste transacciones financieras, recibe notificaciones de plataformas externas, ejecuta cargos domiciliados, exporta reportes y reenvia eventos a sistemas cliente. El shell autenticado diferencia Administrador y Cliente; las APIs externas conservan contratos legacy sin prefijo `/api`.

## 5. Stack confirmado

| Capa | Version/contrato | Evidencia |
| --- | --- | --- |
| PHP | `8.3.31` local; Composer exige `^8.2` | Runtime y `composer.json` |
| Framework | Laravel `12.54.1` | `php artisan --version`, lock |
| Tests | PHPUnit `11.5.55` | `composer.lock` |
| HTTP | Guzzle `7.10.0` | `composer.lock` |
| Export | maatwebsite/excel `3.1.67` | `composer.lock` |
| Frontend | Vue `3.5.30` | `package-lock.json` |
| Build | Vite `7.3.5`, plugin Vue `6.0.7`, Mix `6.0.49` residual | lock y scripts |
| Node | `.nvmrc` `22.22.1`; WSL observado `22.23.1` | archivo/runtime |
| npm | `10.9.8` observado | runtime |
| Base de datos | MySQL operativo; Feature local en MySQL desechable y CI en SQLite aislado | codigo/docs |
| Zona horaria | `America/Hermosillo` | `config/app.php` y reglas explicitas |

Composer local es `2.2.6`; valida el manifiesto, pero emite avisos deprecados por su propia version con PHP 8.3. No se actualizaron dependencias.

## 6. Arquitectura real

```text
Blade principal + sidebars
        |
contenido.blade.php (menu 0..35)
        |
Vue 3 components + Axios
        |
routes/web.php ---- auth + Administrador middleware
routes/api.php ---- throttle + incoming audit, sin prefijo /api
        |
Controllers legacy / Services recientes
        |
Eloquent + Query Builder
        |
MySQL / Pagadetodo / callbacks cliente / Database Queue
```

- El patron dominante es MVC Laravel clasico.
- `TransaccionController` tiene 4,629 lineas y concentra generacion, SPEI, terminal, cancelacion, imports y reportes.
- `TransaccionDomController` concentra cargos manuales/API/cron y cancelacion automatica.
- Los servicios nuevos separan auditoria, firma/fanout/entrega webhook y reconciliacion de estados.
- Vue 3 se monta sobre una plantilla heredada. `principal.blade.php` es una frontera estable.
- Vite genera el bundle principal; scripts locales conservan lanes legacy/guest y el bridge `public/js/app.js`.
- El compose productivo no esta versionado. El repositorio por si solo no reconstruye la infraestructura Docker.

## 7. Directorios y archivos clave

| Ruta | Responsabilidad |
| --- | --- |
| `app/Http/Controllers` | Casos de uso y reglas legacy |
| `app/Services` | Auditoria, webhooks y sincronizacion de estados |
| `app/Console/Kernel.php` | Programacion de tres procesos |
| `app/Console/Commands` | Purga, sync de estados, import/replay webhook |
| `routes/web.php` | UI/autenticacion/endpoints internos |
| `routes/api.php` | Contratos externos legacy sin `/api` |
| `resources/assets/js/components` | 25 componentes Vue |
| `resources/views/contenido/contenido.blade.php` | Enrutamiento de menu a componentes |
| `database/migrations` | Base legacy parcial y cambios 2026 |
| `database/centrodecobros.sql` | Dump local ignorado; contiene datos y no debe publicarse |
| `tests/Support` | Esquema/fixtures aislados con guarda MySQL local y compatibilidad SQLite CI |
| `scripts/local` | Build hibrido, checks y preparacion SQLite |
| `.github/workflows/sandbox-release-validation.yml` | CI PHP/Node/build/Unit/Feature |
| `docs/MODULES` | Fichas funcionales por dominio |

No existen `AGENTS.md` anidados, submodulos, symlinks rastreados ni colisiones de mayusculas detectadas en archivos Git.

## 8. Modulos y estado funcional

Los estados son cualitativos; no se asignan porcentajes sin una metrica de aceptacion acordada.

| Modulo | Rutas/UI/datos principales | Estado real | Pendiente principal | Severidad / complejidad |
| --- | --- | --- | --- | --- |
| Shell y dashboard | `/main`, `/dashboard`; `Dashboard.vue`, sidebars | Implementado para roles 1/2 | UAT con proxy HTTPS y datos reales | Media / media |
| Login/actividad | `/login`, `/logout`, `user-activity/module`; `user_activity_logs` | Login exitoso/fallido/logout y acceso de menu auditados | Rechazar keys de menu desconocidas/no permitidas por rol | Media / baja |
| Estados/Ciudades | `estado/*`, `ciudad/*`; `estados`, `ciudades` | CRUD, filtros y selectores operativos | Validaciones/UAT de volumen | Media / baja |
| Clientes | `cliente/*`; `Cliente.vue`; `personas`, `clientes` | Alta, edicion, busqueda, export y ownership; folio inmutable al editar | Carrera `max(num_documento)+1`; reglas mas formales | Alta / media |
| Archivos cliente | `archivo/*`; `archivos` | Listado, alta, descarga y borrado con ownership | Typo `hasname`/`hashname` en `$fillable` | Baja / baja |
| Consolidar clientes | `cliente/consolidar*` | Merge administrativo transaccional | UAT con dataset real y auditoria explicita | Alta / media |
| Depurar clientes | `cliente/depurar*` | Borrado fisico admin con elegibilidad | Decidir soft-delete/retencion | Alta / media |
| Usuarios/Roles | `user/*`, `rol`, alias `role`; `users`, `roles` | Admin CRUD/listado; password no se expone | Migration legacy llama rol 2 `Vendedor`, codigo lo trata como Cliente | Alta / media |
| Liga unica | tipo 1; `transaccion/*`, `GenerarLigaPago`; `Transaccion.vue` | Generacion web/API, import, filtros, estado/error y export | Concurrencia de folios y adapter proveedor | Alta / alta |
| Domiciliacion liga | tipo 2; `registrarDom`, `GenerarLigaDomiciliacion` | Pendiente/activo/error/vencido, token y proximo cargo | Robustecer consistencia API/ownership | Critica / alta |
| Domiciliacion activa | `domiciliacion-activa`, export, cancelar, proximo cargo | Lista productivas aprobadas activas/canceladas; cargo manual | Cliente no tiene export en allowlist aunque UI/ruta existen | Media / baja |
| Cargos recurrentes | `transaccionDom/*`, `CargoDomiciliacion`; `transaccionesDom` | Manual/API/cron, intentos, siguiente fecha y eventos | JOIN cron duplicable y falta lock global; ownership API | Critica / alta |
| Respuestas | `respuesta/*`, `Service/EntregarPagoLiga*` | Lista/export/CRUD, todos los intentos, sync inmediata | Firma/origen y politica definitiva de duplicados | Critica / alta |
| Terminal | tipo 4, `GenerarLigaLector`, `EntregarPagoLector` | Generacion, respuesta, pago/cancelacion y eventos | Validar semanticamente respuesta de cancelacion | Alta / alta |
| Referencia SPEI | tipo 3, `GenerarSpei` | Genera CLABE/referencia y estado error si falta CLABE | UAT de contrato real | Alta / alta |
| Consulta SPEI | `Service/ConsultaClabe`, `consultaspei` | Consulta y auditoria legacy | Firma/origen y datos reales | Alta / media |
| Pago SPEI | `Service/PagoClabe`, `pagospei` | Idempotencia por `transaccion`, pago y notificacion | Atomicidad entre condicion y fila de pago; vencimiento | Critica / alta |
| Cancelacion SPEI | `Service/CancelaClabe`, `cancelaspei` | Idempotencia por transaccion/autorizacion | Contrato real y firma/origen | Alta / media |
| Pago en Caja | comparte tipo 3 y respuestas | UI/respuestas/reportes parciales | Diferenciar historicamente Caja de SPEI | Media / media |
| Pagos Recibidos | `pagos-recibidos`, export; tres fuentes | Lista pagos aprobados y export admin/cliente con ownership | CSV omite folio/autorizacion visibles como filtros; canal tipo 3 ambiguo | Media / baja |
| Reportes | menus 18-21/25 y exports | Reportes por liga, domiciliacion, SPEI, terminal y recurrentes | UAT de volumen y joins legacy | Alta / media |
| Importacion masiva | `transaccion/importar/*` | Progreso, cancelacion y log | Validar concurrencia, retencion y plantilla real | Alta / media |
| Auditoria integraciones | menus 31-33; tres tablas | Incoming/outgoing/actividad, sanitizacion y export admin | Retencion manual y crecimiento DB | Alta / media |
| Webhook config | menu 34; endpoints/subs/settings | Modos legacy/shadow/hybrid/active/disabled, HTTPS y HMAC | Test endpoint en hybrid se acepta en controlador pero job lo cancela | Alta / baja |
| Webhook deliveries | menu 35; events/deliveries/attempts | Cola, reintentos, rate limit, idempotencia y ACK | Operacion requiere worker persistente y monitoreo | Critica / media |
| Notificaciones UI | `notification/get`, broadcasting | Polling; Echo opcional | Realtime no validado E2E | Media / media |
| UX responsive | Vue + `ux-ui.css` | Mejoras aplicadas en tablas financieras | Smoke visual integral mobile/desktop | Media / media |
| Build/deploy | scripts Vite/legacy; Docker externo | Build definido y CI existente | Alinear CI Node con `.nvmrc`; versionar/respaldar runbook de infraestructura | Alta / media |

## 9. Flujos financieros principales

### Generacion

1. Usuario web o API entrega datos de cliente/transaccion.
2. El controlador resuelve credenciales/ambiente y genera folio.
3. `postJsonControlado()` usa mock o Pagadetodo y audita salida.
4. La transaccion se persiste. Falta URL, CLABE o identificador terminal, o `code=error`, produce `condicion=5`.
5. UI y exportaciones presentan el estado segun tipo.

### Respuestas de liga/terminal

1. Pagadetodo llama `Service/EntregarPagoLiga*` o `Service/EntregarPagoLector`.
2. Middleware registra request/response sanitizados.
3. `RespuestaController` valida JSON minimo y busca `transacciones.responseReference`.
4. Se guarda una fila `respuestas` por intento. La deduplicacion esta comentada por decision temporal del propietario.
5. `approved` cambia tipo 1/4 a Pagado y tipo 2 a Activo o Error segun token.
6. Se publica evento configurable y, segun modo, se conserva/reemplaza callback legacy.

### Domiciliacion recurrente

- El cron diario selecciona tipo 2, activo, productivo, `users.recurrente=1`, respuesta aprobada y `ProximoCargo=hoy`.
- Un cargo aprobado avanza desde la fecha programada segun frecuencia y reinicia intentos.
- Un rechazo reintenta al dia siguiente; al limite configurado (default 3) detiene cargos e inicia cancelacion.
- El cargo manual usa la misma regla de avance. El API publico de cargo no avanza `ProximoCargo`.

### Estados y SPEI

- Cada cinco minutos `revisarStatus()` mantiene el flujo SPEI legacy/shadow.
- A las 00:05 `transacciones:sincronizar-status` vence tipos 1/3/4 activos y tipo 2 pendiente sin aprobacion; tambien reconcilia token aprobado.
- `PagoClabe` marca tipo 3 pagado y registra `pagospei`; revisar atomicidad antes de cambios.

## 10. Modelo de datos y relaciones

Relaciones centrales inferidas y usadas por codigo:

```text
personas.id = clientes.id
personas.id = users.id
clientes.idusuario -> users.id
transacciones.idcliente -> clientes.id
transacciones.idusuario -> users.id
respuestas.idtransaccion -> transacciones.id
transaccionesDom.idtransaccion -> transacciones.id
pagospei.idtransaccion -> transacciones.id
consultaspei.idtransaccion -> transacciones.id
cancelaspei.idtransaccion -> transacciones.id
cancelacionesDom.idtransaccion -> transacciones.id (columna reciente)
cancelacionesLector.idtransaccion -> transacciones.id (columna reciente)
webhook_events.idusuario/idtransaccion/source_id -> relaciones logicas
webhook_deliveries.webhook_event_id -> webhook_events.id
webhook_endpoint_subscriptions.webhook_endpoint_id -> webhook_endpoints.id
```

Muchas relaciones no tienen FK declarada. El esquema operativo real proviene de MySQL/dump autorizado; las migrations legacy incluyen tablas ajenas al dominio actual y no reconstruyen el sistema completo.

Migrations recientes:

- 2026-06-04: `ProximoCargoBase`, `intentos`, `pagos_recibidos`.
- 2026-07-03: tres bitacoras de auditoria.
- 2026-07-10: motor webhook, tablas de queue y correlacion de cancelaciones.
- 2026-07-14: canal webhook y lifecycle de cancelacion; incluye un `UPDATE` de filas tipo 2 existentes.

Los `up()` son aditivos salvo el backfill del 2026-07-14. Los `down()` si eliminan columnas/tablas y no deben ejecutarse sin plan. Ninguna migration fue ejecutada en esta auditoria.

## 11. Autenticacion, roles y permisos

| Actor | Acceso real | Gaps |
| --- | --- | --- |
| Administrador `idrol=1` | Todo el grupo web protegido y menus 0-35 aplicables | Falta Policies por accion; un controlador monolitico aumenta blast radius |
| Cliente `idrol=2` | Allowlist exacta y ownership/productivo en modulos operativos | Export de Domiciliacion Activa no esta permitido; UI puede montar componentes admin si se fuerza `menu`, aunque endpoints bloquean |
| Consulta de respuestas `idrol=4` | Lectura de Respuestas tipos 1-4 y Pagos Recibidos del Cliente activo vinculado | Sin exportacion ni operaciones; el vinculo se valida en aplicacion porque `users.id` no tiene unicidad declarada |
| Otros roles | Middleware devuelve 403 | Migration legacy define roles 2/3 con otros nombres; no hay experiencia ni matriz vigente |
| Guest | login y flujos publicos `/`/`url` | Flujo raiz legacy requiere smoke |
| Cliente API | `User`/`Password` en payload | Contrato legacy, rate limit general 60/min y scopes inconsistentes |
| Proveedor webhook | rutas `Service/*` | Sin firma/origen por falta de contrato Pagadetodo |

Ocultar columnas/menus es UX, no control de seguridad. La fuente de autorizacion es middleware mas ownership de controlador.

## 12. APIs e integraciones

### Pagadetodo

Los endpoints externos se configuran con `PAGADETODO_URL_*`; credenciales e IDs viven en variables `PAGADETODO_*`. Los contratos propios en `routes/api.php` no llevan prefijo `/api` y deben conservarse.

- [VALIDADO POR EL USUARIO] Sandbox y productivo funcionaron desde el servidor/IP autorizado.
- [PENDIENTE DE VALIDACION] Firma/origen de inbound webhooks.
- [EVIDENCIA EN CODIGO] Auditoria de outgoing/incoming no bloquea la operacion si su persistencia falla.

### Webhooks a clientes

- Legacy usa `users.notificaPago`, `ligaPago` y `ligaRecurrente`.
- `legacy` conserva comportamiento previo.
- `shadow` conserva legacy y crea entregas sin HTTP adicional.
- `hybrid` usa el motor nuevo para tipos suscritos y legacy para los demas.
- `active` reemplaza legacy por cola.
- HMAC opcional: `sha256` sobre `{timestamp}.{event_id}.{raw_request_body}` y headers `X-Soportetech-*`.
- URL HTTPS es obligatoria; no hay allowlist/DNS/SSRF por decision funcional del propietario.
- `soportetech_v1`, `soportetech_v1_1` y `legacy_exact` tienen responsabilidades distintas; consultar la ficha del modulo antes de cambiar payloads.

### Otras dependencias

- Pusher/Echo: opcional, no validado E2E.
- SMTP/Postmark: configurado por entorno, no validado aqui.
- TeleSign: dependencia historica residual; flujo OTP publico retirado.
- Google Fonts/CDN/social links: referencias externas en vistas legacy.

## 13. Colas, jobs, cron y comandos

| Proceso | Programacion/uso | Estado |
| --- | --- | --- |
| `TransaccionDomController@ejecutarCron` | diario 07:00 | Financiero; no tiene `withoutOverlapping()` en scheduler |
| `revisar-status-pagos-spei` | cada 5 minutos, overlap 10 min | Sincronizacion SPEI/callback legacy |
| `transacciones:sincronizar-status` | 00:05 Hermosillo, overlap 120 min | Reconciliacion diaria con metricas |
| `DeliverWebhookJob` | queue `webhooks` | Entrega, rate limit, reintentos, HMAC |
| `FanoutWebhookEventJob` | queue configurable | Crea entregas por suscripcion |
| `audit:purge` | manual, default 365 dias | Destructivo sin `--dry-run`; no programado |
| `webhooks:import-legacy` | manual; soporta `--dry-run` | Importa URLs de users a endpoints |
| `webhooks:replay-response` | manual; soporta `--dry-run` | Republica aprobacion tipo 1 idempotente |

Produccion reportada usa servicios Compose `app` y `queue`. Los comandos deben preferir `sudo docker compose exec app ...`; no depender del nombre fisico del contenedor.

## 14. Variables de entorno necesarias

No documentar valores. Familias relevantes:

- Laravel: `APP_*`, `LOG_*`, `DB_*`, `CACHE_*`, `SESSION_*`, `QUEUE_*`.
- Pagadetodo: `PAGADETODO_MOCK`, `PAGADETODO_URL_*`, usuarios/passwords/IDs.
- Webhooks: `WEBHOOK_NOTIFICATIONS_ENABLED`, `WEBHOOK_QUEUE_CONNECTION`, `WEBHOOK_QUEUE_NAME`, timeouts, attempts y rate limit.
- Domiciliacion: maximo de rechazos y ventana de reintento de cancelacion.
- Pusher/Vite: `PUSHER_*`, `VITE_PUSHER_*`.
- Mail: `MAIL_*`, Postmark si aplica.

`config/app.php` tiene un fallback de URL con una IP historica. Produccion debe definir `APP_URL`; no depender del fallback.

## 15. Instalacion y ejecucion en WSL

La replica contiene `vendor`, `node_modules` y `.env`, pero copiar dependencias desde NTFS no garantiza binarios nativos Linux compatibles. Primero inspeccionar, no reinstalar de forma automatica.

```bash
cd /home/ccortesg/workspace/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia
git status --short --branch
php -v
composer --version
node -v
npm -v
php artisan --version
```

Para una instalacion limpia autorizada:

```bash
composer install --no-interaction
npm ci
```

Para servidor local, solo con DB/configuracion de desarrollo autorizada:

```bash
php artisan serve
```

No usar la DB productiva desde WSL. Feature local usa `pdo_mysql` y la base desechable indicada en `.env.testing`; CI conserva `pdo_sqlite` y su SQLite efimero.

## 16. Build y assets

```bash
npm run development
npm run production
```

El runner `scripts/local/run_phase15_build.js` genera lanes legacy/guest, Vite y bridge. Los resultados bajo `public/build`, `public/js`, `public/css` y `public/mix-manifest.json` estan ignorados y no se versionan.

No se ejecuto build en esta auditoria porque modifica artefactos fisicos y no hubo cambio frontend.

## 17. Pruebas y validaciones

Directorio de ejecucion: origen detectado. Fecha: 2026-08-04.

| Estado | Comando | Resultado y alcance | Duracion |
| --- | --- | --- | --- |
| [VERIFICADO EN ESTA EJECUCION] | `php artisan --version` | Laravel 12.54.1 | Incluido en bloque de inspeccion |
| [VERIFICADO EN ESTA EJECUCION] | `php artisan route:list --no-ansi` | 121 rutas | Aproximadamente 2 min sobre `/mnt/c` |
| [VERIFICADO EN ESTA EJECUCION] | `php artisan schedule:list --no-ansi` | 3 tareas, sin ejecutarlas | Mismo bloque |
| [VERIFICADO EN ESTA EJECUCION] | lint en `app bootstrap config database routes tests` | 169/169 PHP sin error | Aproximadamente 25 s |
| [VERIFICADO EN ESTA EJECUCION] | `composer validate --no-check-publish --no-interaction` | Valido; avisos del Composer antiguo | Menos de 10 s |
| [VERIFICADO EN ESTA EJECUCION] | `php vendor/bin/phpunit --testsuite Unit --do-not-cache-result` | 28 tests, 171 assertions | 23.476 s |
| [VERIFICADO EN ESTA EJECUCION] | `git diff --check` | Sin errores antes de editar docs | Menos de 5 s |
| [VALIDADO POR EL USUARIO] | Pagadetodo sandbox/productivo desde servidor | Exitoso; no ejecutado por esta auditoria | Historico reportado |
| [PENDIENTE DE VALIDACION] | Feature SQLite | No ejecutado: escribe una DB SQLite y el prompt prohibe modificar DB | N/A |
| [PENDIENTE DE VALIDACION] | Build frontend | No ejecutado: regenera assets y no hubo cambios frontend | N/A |
| [PENDIENTE DE VALIDACION] | Browser smoke admin/cliente | Requiere app y DB de testing | N/A |
| [PENDIENTE DE VALIDACION] | MySQL/UAT productivo | Fuera de alcance y no autorizado | N/A |
| [VALIDACION FALLIDA] | `git lfs env` | Git reconoce el subcomando pero no puede ejecutar `git-lfs`; no hay punteros LFS detectados | Inmediata |

CI instala dependencias, construye frontend y ejecuta Unit/Feature con SQLite mock. El workflow usa Node major `20`, mientras `.nvmrc` fija `22.22.1`; ambas pueden ser compatibles con Vite si el runner resuelve un Node 20 suficientemente nuevo, pero conviene alinear la politica.

## 18. Despliegue productivo conocido

[VALIDADO POR EL USUARIO] El checkout productivo esta en `/var/www/centrodecobros2`; Compose expone `app` y posteriormente se agrego `queue`. El contenedor app trabaja en `/var/www/html`. Ejemplos confirmados:

```bash
cd /var/www/centrodecobros2
sudo docker compose exec app php artisan optimize:clear
sudo docker compose exec app php artisan config:cache
sudo docker compose restart app
sudo docker run --rm --user "$(id -u):$(id -g)" \
  -e npm_config_cache=/app/.npm-cache \
  -v "$PWD":/app -w /app node:22-bookworm \
  sh -lc 'npm ci --include=dev && npm run production'
```

No hay Compose en Git; inspeccionar `sudo docker compose config --services` antes de cualquier operacion. No ejecutar migrations, purgas ni cambios de scheduler sin respaldo, `--pretend` y aprobacion expresa.

## 19. Estado Git e inventario local

Baseline previa a documentacion:

- Rama/HEAD: `main` / `4299d1984e2e75cbea5a2275ef9e9d79224519f5`.
- `main` y `origin/main` apuntan al mismo commit.
- Tags: `sandbox-phase34-v1.0.0`, `sandbox-phase34-v1.0.1`, `sandbox-phase34-v1.0.2`.
- Stashes: ninguno.
- Submodulos: ninguno.
- Worktrees: solo el origen.
- `.git`: directorio real dentro de la raiz.
- Working tree inicial: limpio.
- Tamano observado: aproximadamente 371 MiB; 30,357 archivos y 4,788 directorios fisicos.

La copia integra debe preservar contenido ignorado: `.env`, dump SQL, `vendor`, `node_modules`, caches, sesiones, uploads, SQLite de testing, outputs y assets generados. Preservar no significa que deban versionarse ni reutilizarse en produccion.

Existe un directorio local vacio/auxiliar con nombre literal de ruta Windows (`C:\temp\...\storage\framework`) dentro de la raiz Linux. Se conserva por integridad, pero es una anomalia de portabilidad y no debe usarse como ruta canonica.

## 20. Hallazgos priorizados

| Prioridad | Hallazgo confirmado | Impacto | Accion recomendada |
| --- | --- | --- | --- |
| P0 | Cron hace JOIN a todas las respuestas aprobadas sin `distinct/exists` y sin lock global | Cargo recurrente duplicado | Carril financiero separado con query unica, claim/idempotencia y prueba de concurrencia |
| P0 | `CargoDomiciliacion` busca tipo 2 por `ClientReference` sin `idusuario/productivo` | Un usuario API podria operar una referencia homonima de otro tenant | Scope fail-closed por usuario y ambiente, conservando contrato |
| P0 | Deduplicacion de respuestas liga/lector esta comentada | Multiples intentos validos se conservan, pero reintentos identicos pueden duplicar filas/eventos | Definir clave por intento y regla de un solo aprobado sin descartar denegados |
| P0 | Pago SPEI actualiza transaccion y guarda pago en transacciones DB separadas | Estado Pagado sin fila `pagospei` si falla el segundo guardado | Una unica transaccion atomica + prueba de rollback |
| P1 | Pago SPEI puede considerar vencida la referencia durante su propio dia de expiracion | Rechazo anticipado respecto al sync diario | Alinear semantica de fecha con negocio y tests frontera |
| P1 | Cancelacion web puede persistir cancelado con JSON parseable sin validar semanticamente el codigo proveedor | Desalineacion local/proveedor | Unificar parser/criterio de exito con API y registrar fallo controlado |
| P1 | Credenciales de cancelacion web pueden provenir del usuario autenticado, no del owner de transaccion | Admin podria usar credenciales incorrectas al operar cliente | Resolver integracion desde owner/registro |
| P1 | Folios usan `max()+1` en varios flujos | Colisiones bajo concurrencia | Secuencia/lock/unique con retry, por scope de negocio |
| P1 | Inbound `Service/*` no verifica firma/origen | Inyeccion/replay de payloads | Implementar cuando Pagadetodo entregue contrato compatible |
| P1 | Esquema migrations incompleto y roles legacy contradictorios | Entornos nuevos no reproducibles | Baseline de schema controlada y migration de datos revisada |
| P1 | No hay Compose/Dockerfile en repo | Deploy/rollback depende de conocimiento externo | Versionar infraestructura saneada o mantener runbook verificado |
| P2 | Test de endpoint webhook acepta `hybrid`, job cancela tests hybrid | UI reporta encolado pero no entrega | Alinear modos en controlador/job y cubrir con test |
| P2 | Export de Domiciliacion Activa no esta en allowlist Cliente | Boton/ruta puede devolver 403 al cliente | Decidir acceso y agregar prueba de ownership si se habilita |
| P2 | Actividad de modulo acepta key desconocida y la registra | Bitacora administrativamente ruidosa/falsificable | Validar mapa y acceso por rol en backend |
| P2 | Reportes conservan joins legacy por referencias | Duplicados/omisiones ante relaciones historicas | Migrar gradualmente a `idtransaccion` y comparar totales |
| P2 | `ApiAuditLogger` repite el mismo fallback `idtransaccion` | Correlacion alternativa nunca se resuelve | Definir alias real esperado y probarlo |
| P2 | Logs legacy incluyen respuestas crudas en algunos cargos | Exposicion operativa en `laravel.log` | Redaccion central, sin cambiar payload transmitido |
| P3 | `Archivo::$fillable` usa `hasname`, controlador `hashname` | Futura asignacion masiva puede fallar | Corregir con test acotado |
| P3 | Sidebar Cliente conserva texto visual de Administrador | Confusion UX | Ajuste de etiqueta en iteracion frontend |

## 21. Brechas documentacion-codigo corregidas o preservadas

- [OBSOLETO] Conteos de 100/103/110/121 rutas: el corte actual tiene 122.
- [OBSOLETO] PHP 8.3.27 y Node Windows 20.20 como unico runtime local: WSL actual muestra PHP 8.3.31 y Node 22.23.1; `.nvmrc` manda 22.22.1.
- [OBSOLETO] Dos tareas scheduler: ahora son tres.
- [OBSOLETO] Idempotencia activa de respuestas: los bloques estan comentados por decision temporal del propietario.
- [DOCUMENTACION HISTORICA] Los archivos `MIGRATION_PHASE_*`, diagnosticos fechados y handoff conversacional preservan evidencia de su fecha; no describen por si solos el HEAD actual.
- [EVIDENCIA EN CODIGO] `app/Notifications/TransaccionValidada.php` citado en docs no existe; el correo real esta en `app/Mail/TransaccionValidada.php` y la notificacion en `app/Notifications/NotifyAdmin.php`.
- [EVIDENCIA EN CODIGO] La migration legacy nombra rol 2 `Vendedor`; runtime, sidebars y middleware lo usan como Cliente.

## 22. Consideraciones Windows a WSL

- Ejecutar desde filesystem Linux reduce latencia frente a `/mnt/c`.
- Scripts `.ps1` siguen requiriendo PowerShell/Windows; sus equivalentes Bash deben documentarse si se vuelven obligatorios.
- WAMP PHP 8.3 incluye historicamente SQLite, pero el PHP WSL auditado solo tiene `pdo_mysql`.
- Dependencias copiadas pueden contener binarios/paths del host anterior; validar antes de usarlas.
- No se detectaron symlinks ni colisiones de case en archivos rastreados.
- No normalizar permisos o line endings masivamente. `.gitattributes` y Git deben conservar el contrato actual.
- Configurar MySQL local de desarrollo, extensiones PHP, permisos de `storage`/`bootstrap/cache` y servicios opcionales sin reutilizar secretos productivos.
- Revisar caches Laravel copiados: pueden contener rutas/config del origen. No documentar sus valores; limpiarlos solo cuando una tarea de operacion lo autorice.

## 23. Decisiones vigentes y sustituidas

Vigentes:

- Trabajar en el nuevo checkout WSL despues de verificar la replica.
- No crear nuevas carpetas de fase.
- Pagadetodo real solo desde servidor/IP autorizado.
- `principal.blade.php` y nombres publicos de assets son fronteras estables.
- Webhooks configurables se activan por cliente y requieren worker persistente.
- Payload real de webhook se transmite segun contrato; solo auditoria se sanitiza.
- La solucion del JOIN duplicado del cron queda como tarea financiera separada.
- No reactivar deduplicacion de respuestas hasta instruccion del propietario.

Sustituidas:

- [OBSOLETO] El workspace activo Windows deja de ser canonico tras la copia validada.
- [OBSOLETO] Crear una carpeta por fase; desde 2026-06-03 se trabaja sobre un unico repo.
- [OBSOLETO] `revisarStatus()` reconciliaba todo cada cinco minutos; hoy SPEI queda cada cinco minutos y la reconciliacion general es diaria.

## 24. Pendientes y siguiente plan

1. Adoptar la ruta WSL verificada como checkout canonico para las siguientes tareas.
2. Preparar un runtime de testing WSL/contenedor con `pdo_sqlite`, sin tocar DB operativa.
3. Ejecutar Feature aislado y browser smoke con Pagadetodo mock; registrar evidencia separada.
4. Atender P0 de ownership en `CargoDomiciliacion` antes de ampliar integraciones API.
5. Diseñar idempotencia de intentos de respuesta que permita multiples denegados y un solo aprobado.
6. Diseñar claim/lock del cron recurrente y revisar indices con `EXPLAIN` productivo de solo lectura.
7. Resolver atomicidad/fecha de SPEI.
8. Alinear test webhook hybrid y export de Domiciliacion Activa.
9. Versionar o documentar formalmente Compose, worker, cron, backup y rollback productivos sin secretos.

Prompt recomendado para la siguiente iteracion:

```text
Trabaja exclusivamente en /home/ccortesg/workspace/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia. Lee AGENTS.md y docs/CODEX_PROJECT_HANDOFF.md completos. No ejecutes migraciones, no uses credenciales ni APIs reales, no cambies scheduler, contratos Pagadetodo, principal.blade.php ni assets compilados. Primero confirma git status y prepara un plan para corregir el P0 de ownership de POST CargoDomiciliacion: la busqueda de transacciones tipo 2 debe quedar acotada al usuario API autenticado y su ambiente productivo, sin cambiar ruta, payload ni respuesta publica. Incluye pruebas positivas y negativas SQLite con PAGADETODO_MOCK=true. No implementes hasta que yo apruebe el plan.
```

## 25. Continuidad para el siguiente agente

1. Abrir `AGENTS.md` y este handoff.
2. Confirmar `git status --short --branch`; los cambios documentales de esta auditoria pueden estar sin commit.
3. Leer la ficha `docs/MODULES` del dominio solicitado.
4. Trazar ruta -> middleware -> controlador -> modelo/tabla -> Vue/Blade -> export/reporte -> webhook/scheduler.
5. Separar hechos verificados de validaciones reportadas por el usuario.
6. Mantener pruebas externas, DB productiva, scheduler y despliegue fuera de local salvo autorizacion explicita.
7. Actualizar este handoff cuando cambie un contrato, riesgo P0/P1, entorno canonico o procedimiento operativo.

## 26. Estado de la migracion del workspace

- [VERIFICADO EN ESTA EJECUCION] Copia fisica completa desde `/mnt/c/temp/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` hacia `/home/ccortesg/workspace/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` mediante `rsync -aH --no-owner --no-group`, sin exclusiones y sin `--delete`. La segunda pasada no encontro archivos por transferir.
- [VERIFICADO EN ESTA EJECUCION] Origen y destino contienen 30,359 archivos y 4,797 directorios. La diferencia de `du -sb` entre NTFS/DrvFS y ext4 corresponde al tamano contabilizado de directorios; la comparacion autoritativa `rsync -rltHnci --delete --no-owner --no-group --no-perms --exclude='.git/'` termino con cero diferencias de contenido.
- [VERIFICADO EN ESTA EJECUCION] Coinciden exactamente `HEAD` (`4299d1984e2e75cbea5a2275ef9e9d79224519f5`), rama `main`, estado tracked/untracked, refs, ramas, tags, remotos, stashes, submodulos, reflogs, hooks, entradas del indice y configuracion Git. `.git` sigue siendo un directorio real.
- [VERIFICADO EN ESTA EJECUCION] `git fsck --full` en destino termino con codigo 0. Reporto 53 objetos `dangling tree` preservados por la copia; no reporto objetos faltantes ni corrupcion.
- [VERIFICADO EN ESTA EJECUCION] Se preservaron archivos locales ignorados y no rastreados, incluidos `.env`, `database/centrodecobros.sql`, `vendor`, `node_modules`, `storage`, outputs y assets generados. No se inspeccionaron ni documentaron secretos.
- [VERIFICADO EN ESTA EJECUCION] Desde la ruta WSL: Laravel 12.54.1 arranca, `route:list` enumera 121 rutas, `schedule:list` enumera las 3 tareas esperadas, `composer validate` pasa y `AuditSanitizerTest` pasa con 2 pruebas y 12 aserciones. `git diff --check` pasa.
- [VALIDACION FALLIDA] Git LFS no puede ejecutar `git-lfs` tanto en origen como en destino. No se detectaron punteros LFS conocidos, pero debe instalarse o repararse Git LFS antes de trabajar con un repositorio que llegue a incorporarlos.
- [PENDIENTE DE VALIDACION] No se ejecutaron Feature tests, build frontend, browser smoke, MySQL, migrations ni APIs externas durante la migracion. Estas validaciones requieren un entorno de testing autorizado y permanecen separadas de la integridad de la copia.

La copia WSL queda verificada como checkout canonico. El origen no fue eliminado ni renombrado y debe conservarse hasta que el propietario decida archivarlo.

## 27. Entorno local MySQL y HTTP preparado el 2026-08-06

- [VERIFICADO EN ESTA EJECUCION] El workspace usado para desarrollo local y trafico HTTP es `/home/ccortesg/workspace/centrodecobros2`; `HEAD`, rama y cambios locales preexistentes se preservaron sin limpieza.
- [CORREGIDO EL 2026-08-06] La referencia anterior a una base ajena fue invalidada por el propietario y eliminada de la configuracion activa. La base desechable correcta es `centrodecobros_testing`.
- [VERIFICADO EN ESTA EJECUCION] `.env` conecta con la base local `centrodecobros` y `.env.testing` apunta a la base desechable `centrodecobros_testing` con su usuario exclusivo. Laravel y `SELECT DATABASE()` confirmaron ambos destinos. Los archivos estan ignorados y las contrasenas no se imprimen ni se versionan.
- [VERIFICADO EN ESTA EJECUCION] El arnes Feature exige ambiente `testing`, driver MySQL, nombre configurado `centrodecobros_testing` y nombre real seleccionado `centrodecobros_testing` antes de `dropAllTables`. `migrate:fresh` completo las 21 migraciones versionadas y no es compatible con ejecucion paralela sobre una sola base.
- [VERIFICADO EN ESTA EJECUCION] Las variables MySQL locales no se fuerzan en `phpunit.xml`: `.env.testing` gobierna localmente y el workflow existente conserva su SQLite efimero aislado.
- [VERIFICADO EN ESTA EJECUCION] `storage/` y `bootstrap/cache/` pertenecen al grupo `www-data`, son escribibles por el grupo y usan setgid en directorios; `.env` es `0640` con grupo `www-data` y `.env.testing` es `0600`.
- [VERIFICADO EN ESTA EJECUCION Y POR EL USUARIO] `scripts/local/apache/centrodecobros2.conf` esta instalado como VirtualHost activo, coincide byte a byte con la plantilla versionada, usa el path real y elimina `Indexes`/`MultiViews`. `apache2ctl configtest` responde `Syntax OK`; Apache devuelve 302 desde `/` hacia `/transaccion` y 200 en `/login`. El propietario confirmo acceso desde su equipo mediante `http://centrodecobros.local/`.
- [VERIFICADO EN ESTA EJECUCION] El contrato vigente de `storePublic()`/`storeLectorPublic()` conserva cada intento recibido; las pruebas ya no exigen la deduplicacion comentada que las reglas del proyecto prohiben reactivar incidentalmente.
- [INVALIDADO EL 2026-08-06] La corrida Feature anterior sobre una base ajena no cuenta como evidencia. Fue sustituida por una corrida verificada sobre `centrodecobros_testing`: 160 pruebas y 674 aserciones correctas.
- [VERIFICADO EN ESTA EJECUCION] Unit termino con 28 pruebas y 171 aserciones. El smoke HTTP efimero confirmo `/login` 200, `/` y `/main` 302 hacia login, y CSS/JS publicos 200 sobre la base local `centrodecobros`.
- [VERIFICADO POR HTTP] El navegador integrado no pudo inicializar el cliente local en esta sesion, pero ya no bloquea la validacion operativa: el servidor efimero paso su smoke, Apache responde correctamente y el propietario confirmo el acceso mediante navegador desde su equipo.
- [VERIFICADO EN ESTA EJECUCION] La base local `centrodecobros` estaba vacia. `scripts/local/import_empty_local_database.php` valido ambiente/base real, ausencia de tablas y seguridad del dump ignorado antes de importar 21 tablas. Luego `migrate --pretend` y `migrate` aplicaron las siete migraciones aditivas pendientes; `migrate:status` termino sin pendientes y se verificaron las tablas de dominio, auditoria, webhooks y queue.

## 28. Rol Consulta de respuestas implementado el 2026-09-25

- [EVIDENCIA EN CODIGO] El rol `idrol=4` se denomina `Consulta de respuestas`. Cada cuenta requiere `users.idusuario_vinculado` hacia un Cliente activo `idrol=2`; varios revisores pueden compartir el mismo Cliente.
- [EVIDENCIA EN CODIGO] El middleware solo permite al rol 4 cargar `/main`, listar `/respuesta` para tipos 1-4 y listar `/pagos-recibidos`. Exportaciones, dashboard, administracion y rutas financieras de escritura responden `403`.
- [EVIDENCIA EN CODIGO] Los listados usan como propietario efectivo de lectura el `id` y `productivo` del Cliente vinculado. La autorizacion de escritura conserva la identidad real del revisor y nunca hereda permisos del Cliente.
- [EVIDENCIA EN CODIGO] El shell tiene sidebar propio, inicia en Respuestas de Liga de pago y oculta exportaciones. El detalle de Respuestas conserva los mismos campos visibles que para Cliente.
- [VERIFICADO EN ESTA EJECUCION] La prueba Feature dedicada pasa sobre `centrodecobros_testing` con 5 pruebas y 68 aserciones; la suite Feature completa pasa con 165 pruebas y 742 aserciones, Unit con 30 pruebas y 184 aserciones, y el build hibrido Vite/legacy termina correctamente.
- [VERIFICADO EN ESTA EJECUCION] La migration del rol paso un ciclo aislado `up -> down -> up` sobre `centrodecobros_testing`, confirmando la columna y el rol sin tocar `centrodecobros`.
- [VERIFICADO EN ESTA EJECUCION] Tras confirmar `APP_ENV=local` y `SELECT DATABASE()=centrodecobros`, se aplico exclusivamente `2026_09_25_120000_add_response_viewer_role_and_user_link`: quedo en batch 7, con `users.idusuario_vinculado` presente y el rol 4 activo.
- [VALIDACION FALLIDA] `migrate:fresh` completo sigue bloqueado antes de esta migration: el baseline legacy falla en `2018_02_27_143638_create_personas_table` porque `personas` ya existe. Esta deuda historica no se corrigio incidentalmente; el arnes Feature restauró la base desechable y la nueva migration se valido de forma aislada.

## 29. Correccion de alta de Consulta de respuestas del 2026-10-01

- El log productivo reporto MySQL 1366 al insertar `N/A` en `users.IntegrationID`. La inspeccion local de solo lectura confirma `INT UNSIGNED NOT NULL`; `BusinessID` es `VARCHAR(255) NOT NULL`.
- Alta y edicion del rol 4 ahora guardan `IntegrationID=0` y `BusinessID=N/A`. El cero representa ausencia de integracion para este rol; sus permisos siguen restringidos por middleware y ownership, no por estos identificadores.
- El arnes Feature replica ambos tipos y restricciones de `users`. Las pruebas cubren alta, edicion, cambio del Cliente vinculado, conversion entre roles, rollback de Persona ante fallo de User y restricciones de acceso existentes.
- [VERIFICADO EL 2026-10-01] Feature completo en MySQL `centrodecobros_testing` con proveedor mock: 167 pruebas y 774 aserciones; prueba dedicada: 7 pruebas y 100 aserciones; Unit: 30 pruebas y 184 aserciones. Sintaxis PHP, Composer, carga de rutas/scheduler y `git diff --check` correctos. No se ejecutaron cargos ni tareas programadas.
- No requiere migration ni build frontend. Para desplegar, publicar el controlador corregido y verificar un alta controlada; no desactivar el modo estricto MySQL. La verificacion productiva queda pendiente.
