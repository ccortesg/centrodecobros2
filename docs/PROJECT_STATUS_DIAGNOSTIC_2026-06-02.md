# Diagnostico integral de estado del proyecto

Fecha de revalidacion: 2026-06-02  
Workspace analizado: `/mnt/c/temp/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`  
Baseline funcional: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

## Nota vigente 2026-06-03

Este diagnostico queda como snapshot historico del 2026-06-02. Fue superado parcialmente por la preparacion GitHub/Fase 35 y por la confirmacion del propietario de que produccion ya funciona por Docker.

Estado vigente:

- trabajar siempre sobre `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`;
- no crear copias nuevas de fase;
- rama vigente `main`;
- `.gitignore` y workflow GitHub ya existen;
- credenciales Pagadetodo/Pusher fueron externalizadas;
- produccion Docker funciona, pero el compose no esta versionado en este repo;
- documentos vigentes: `docs/PROJECT_OPERATING_MODEL.md`, `docs/README.md`, `docs/MIGRATION_DEPLOY_AND_ROLLBACK_RUNBOOK.md` y `docs/MIGRATION_RELEASE_CHECKLIST.md`.

Las secciones siguientes se conservan como evidencia del corte anterior y no deben usarse como prompt operativo actual.

## Dictamen ejecutivo

`GO tecnico parcial fuerte para sandbox controlado; NO-GO para liberacion directa, reemplazo productivo o cobro real`.

La Fase 34 si mejora el punto mas sensible que quedaba despues de Fase 33: webhooks `Service/*` y reintentos. El proyecto carga en Laravel 12.54.1, mantiene 97 rutas, conserva el contrato publico de assets, genera build productivo, pasa Unit local y pasa Feature completo con PHP WAMP 8.3 + SQLite + `PAGADETODO_MOCK=true`.

La brecha principal ya no es la migracion framework ni el shell autenticado. La brecha es operativa y financiera: no hay validacion contra sandbox oficial Pagadetodo no productivo, no hay firma/origen para webhooks, las credenciales y endpoints siguen hardcoded, el scheduler no debe correr en paralelo contra la misma DB, la carpeta no es un repositorio Git activo y `npm audit` completo conserva deuda de tooling.

## Evidencia ejecutada el 2026-06-02

| Area | Resultado |
| --- | --- |
| PHP Linux local | `PHP 8.3.27`; presentes `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `intl`; no aparece `pdo_sqlite`. |
| PHP WAMP | `C:\wamp64\bin\php\php8.3.0\php.exe` disponible; usado para Feature tests con SQLite. |
| Laravel | `php artisan --version` -> `Laravel Framework 12.54.1`. |
| Rutas | `php artisan route:list` OK, 97 rutas. |
| Scheduler | `php artisan schedule:list` OK; 2 tareas: cargo recurrente diario 07:00 y revision de status cada 5 minutos. |
| Composer | `composer validate --no-check-publish --no-interaction` OK; Composer local emite deprecations y no expone `composer audit`. |
| Node/npm | `node v20.20.0`, `npm 10.8.2` via `cmd.exe`. |
| Build frontend | `npm run production` OK; genera/verifica `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css`, `public/js/guest-public.js` y `public/build/manifest.json`. |
| npm runtime audit | `npm audit --omit=dev --audit-level=low` -> 0 vulnerabilidades. |
| npm audit completo | `npm audit --audit-level=low` -> 29 vulnerabilidades: 5 low, 16 moderate, 8 high. |
| Unit tests | `php vendor/bin/phpunit --testsuite Unit` -> OK, 13 tests, 72 assertions. |
| Feature completo WAMP/SQLite | OK, 54 tests, 234 assertions. |
| Git | En el corte 2026-06-02 todavia no era repo; esto fue superado despues con rama `main` y remoto GitHub. |

## Estado global por capa

| Capa | Avance | Diagnostico |
| --- | ---: | --- |
| Backend Laravel 12 | 88% | Rutas, controladores, middleware, scheduler y tests cargan. Persisten controladores monoliticos; la configuracion financiera fue externalizada despues en Fase 35. |
| Frontend Vue 3 + Vite | 86% | Build productivo verde y contrato publico preservado. `plantilla.*` sigue como lane legacy intencional. |
| Base de datos | 67% | Feature aislado valida comportamiento, pero schema real sigue siendo MySQL historico/dump y UAT MySQL local no esta desbloqueado. |
| Seguridad/accesos | 78% | Middleware por rol, ownership critico y whitelists existen. Faltan politicas formales por accion, secretos externos y firma/origen de webhooks. |
| Integraciones Pagadetodo | 78% | Mock controlado y webhooks idempotentes pasan pruebas. Falta sandbox oficial no productivo. |
| Webhooks/idempotencia | 80% | Fase 34 agrego validacion minima, deduplicacion y errores controlados en `Service/*`. Falta especificacion de firma/origen y observabilidad. |
| Pruebas automatizadas | 88% aislado / 55% MySQL real | Unit y Feature aislados pasan; MySQL real sigue pendiente por entorno/credenciales. |
| Documentacion viva | 85% | Documentos rectores y modulos principales actualizados con Fase 34; quedan docs historicas por naturaleza de migracion. |
| Readiness sandbox paralelo | 78% | Viable si se usa vhost/subdominio separado, sesiones/cache/logs aislados, scheduler apagado y `PAGADETODO_MOCK=true`. |
| Readiness reemplazo productivo | 56% | Snapshot 2026-06-02: faltaban sandbox Pagadetodo, UAT MySQL real, secretos, firma de webhooks, Git limpio y operacion productiva. Git/secretos fueron mitigados parcialmente despues; produccion Docker ya funciona. |

## Diagnostico por modulo, pantalla y funcionalidad

Los porcentajes estiman avance para operar en sandbox controlado. No equivalen a aceptacion productiva hasta UAT con datos reales, sandbox oficial Pagadetodo y aprobacion operativa.

| Modulo / pantalla | Rutas / menu | Avance | Estado actual | Pendientes |
| --- | --- | ---: | --- | --- |
| Login | `/login` | 88% | Login custom por `usuario`/password preservado. | Rate limit, bloqueo por intentos y UAT con usuarios reales. |
| Logout / sesion | `/logout` | 86% | Logout POST activo. | Separar `SESSION_COOKIE`, cache y logs en sandbox paralelo. |
| Public `/url` | `GET/POST /url` | 86% | Vista guest y validacion URL preservadas; usa `guest-public.js`. | Definir CSP/frame policy y allowlist de destinos. |
| Public `/` | `GET/POST /` | 48% | Ruta publica historica viva; flujo legacy no debe asumirse como pantalla robusta moderna. | Decidir retiro, redireccion formal o restauracion funcional. |
| Shell autenticado | `/main`, sidebars | 86% | Switchboard Blade carga componentes por `menu`; admin y cliente tienen sidebars diferenciados. | UAT browser completo despues de preparar release sandbox. |
| Dashboard | `/dashboard`, menu 0 | 82% | Endpoint carga y ya fue adaptado para MySQL/SQLite. | Validar metricas con dataset real y separacion por rol/productivo. |
| Notificaciones | `/notification/get`, Echo/Pusher | 63% | Polling HTTP vivo; realtime queda en lane legacy con `BROADCAST_DRIVER=log` local. | Externalizar Pusher, validar websocket aislado y no usar credenciales reales para pruebas. |
| Roles | `/rol`, `/role`, menu 4 | 63% | Listado/selector apuntan a `RolController`; `RoleController` queda legacy sin ruta directa. | CRUD completo, politicas por accion y retiro formal de legacy. |
| Usuarios | `/user/*`, menu 3 | 78% | CRUD basico; password no se expone y update es condicional. | Whitelists/policies mas formales, auditoria y UAT MySQL. |
| Estados | `/estado/*`, menu 7 | 70% | CRUD activar/desactivar y selector. | Validaciones backend estrictas y UAT con catalogo real. |
| Ciudades | `/ciudad/*`, menu 8 | 70% | CRUD, selector y `listarCiudad`. | Validaciones y paginacion consistente. |
| Clientes | `/cliente/*`, menu 9 | 86% | Listado, alta, edicion, selector y export con ownership para rol cliente. | Validacion de campos, auditoria y UAT con datos reales. |
| Archivos cliente | `/archivo/*` | 72% | Alta/listado/descarga/borrado con propietario. | Headers/nombre de descarga, mimes `xlx/xlxs`, errores de storage. |
| Consolidar clientes | `/cliente/consolidar`, menu 10 | 87% | Flujo admin con transaccion DB y locks. | UAT con volumen real y bitacora operativa visible. |
| Depurar clientes | `/cliente/depurar`, menu 28 | 87% | Flujo admin todo-o-nada con elegibilidad. | Reporte de no elegibles y UAT real. |
| Liga de pago unica | `/transaccion`, tipo 1, menu 1 | 84% | Creacion web/API, listado, export e importacion masiva. | Sandbox Pagadetodo oficial y concurrencia de folios. |
| Importacion masiva | `/transaccion/importar/*` | 78% | Inicio/proceso/cancelacion/estatus/log para ligas y domiciliacion. | Prueba con archivos reales grandes, limpieza de temporales y monitoreo. |
| Respuestas ligas | `/respuesta`, `Service/EntregarPagoLiga*`, menus 2/12 | 80% | Fase 34 agrega validacion minima, campos opcionales seguros e idempotencia. | Firma/origen, sandbox oficial, trazabilidad de reintentos y alertas. |
| Domiciliacion liga | `/transaccion`, tipo 2, menu 11 | 83% | Generacion, frecuencia y proximo cargo cubiertos por mock. | Sandbox oficial, reglas de reintento/cancelacion y UAT. |
| Cargos recurrentes | `/transaccionDom`, menu 13 | 79% | CRUD/listado/API cargo/reportes/scheduler. | No activar scheduler en paralelo; agregar locks y jobs/comandos dedicados. |
| Cancelar domiciliacion API | `POST CancelarDomiciliacion` | 76% | Permite `ClientReference` o `Token`; mock disponible. | Contrato real `CancelarDomiciliacionIndi`, idempotencia y sandbox. |
| SPEI generacion | `GenerarSpei`, tipo 3, menu 14 | 82% | Validacion temprana, mock y persistencia CLABE/referencia. | Sandbox generacion y validacion real de CLABE. |
| Consulta SPEI | `Service/ConsultaClabe`, `/consultaspei`, menu 22 | 83% | Fase 34 corrige errores por referencia vacia/nula; listado/export con ownership. | Eventos reales, export con volumen y firma/origen si aplica. |
| Pago SPEI | `Service/PagoClabe`, `/pagospei`, menu 23 | 84% | Valida payload minimo, monto numerico y deduplica por `transaccion`. | Conciliacion real, callbacks y observabilidad. |
| Cancelaciones SPEI | `Service/CancelaClabe`, `/cancelaspei`, menu 24 | 83% | Valida payload minimo, deduplica por `transaccion + autorizacion` y maneja pago no encontrado. | Definir visibilidad cliente y validar periodo/reglas con sandbox. |
| Pago en caja | menus 14/15 | 52% | UI legacy lo muestra, pero comparte tipo/ruta con SPEI. | Aclarar contrato funcional o separar nomenclatura. |
| Pago terminal / lector | tipo 4, `GenerarLigaLector`, menus 26/27 | 79% | Mock y webhook lector con validacion/idempotencia. | QR/contrato externo/cancelacion lector reales. |
| Reportes ligas/domiciliacion/terminal | menus 18/19/21 | 83% | Reportes y exports vivos. | UAT con rangos, fechas nulas y datos reales. |
| Reporte SPEI | menu 20 | 84% | Reporte/export SPEI vivo. | Conciliacion real. |
| Reporte cargos recurrentes | menu 25 | 84% | Reporte/export cargos vivo. | Volumen y rangos reales. |
| API clientes externos | `Generar*`, `CargoDomiciliacion`, `CancelarDomiciliacion` | 74% | Auth por payload `User`/`Password`, validaciones tempranas y mock. | Rate limit, versionado, idempotencia general, errores consistentes y sandbox oficial. |
| Webhooks proveedor | `Service/*` | 80% | Fase 34 cubre validacion, deduplicacion local y errores controlados. | Firma/origen, especificacion formal de esquema, reintentos documentados y monitoreo. |
| Scheduler | `ejecutarCron`, `revisarStatus` | 70% | Tareas registradas y cargan. | Deshabilitar en sandbox paralelo; extraer a comandos/jobs con locks. |
| Exportaciones | varios `/exportar` | 81% | Exports criticos filtran por propietario. | Revalidar memoria/tiempo con dataset real. |

## Estado por rol y tipo de acceso

| Rol / acceso | Avance | Lo que funciona | Pendientes |
| --- | ---: | --- | --- |
| Administrador `idrol=1` | 88% | Acceso total al grupo protegido; opera catalogos, clientes, transacciones, reportes, usuarios, roles, SPEI y domiciliacion. | UAT MySQL real, politicas por accion y auditoria de operaciones sensibles. |
| Cliente operativo `idrol=2` | 87% | Middleware limita rutas; sidebar acotado; ownership por recurso en modulos criticos y exports. | Validar usuarios reales; decidir exposicion SPEI/cancelaciones y documentar matriz final de permisos. |
| Otros roles | 58% | Bloqueo por defecto `403`. | Definir si existen roles funcionales adicionales y construir menu/politicas/tests. |
| Guest publico | 76% | `/login`, `/url` y ruta publica legacy existen. | Definir destino real de `/`, CSP/frame policy y hardening publico. |
| Cliente API externo | 74% | Contratos principales cubiertos por mock y validaciones tempranas. | Auth robusta, rate limit, idempotencia global, versionado y sandbox. |
| Proveedor/webhook externo | 80% | `Service/*` recibe payloads, valida minimo y deduplica casos clave. | Firma/origen, contrato oficial, reintentos documentados y evidencia sandbox. |
| Scheduler / sistema | 70% | Tareas registradas. | No ejecutar doble contra la misma DB; locks, comandos dedicados y monitoreo. |

## Errores y mejoras necesarias

### Criticos para avanzar a pruebas reales

1. Obtener URL y credenciales sandbox Pagadetodo no productivas; no usar produccion para validar.
2. Verificar si Pagadetodo soporta firma/origen de webhooks y agregar validacion antes de cobro real.
3. Mantener `PAGADETODO_MOCK=true` hasta comparar contrato real contra mocks controlados.
4. No activar scheduler de esta version si comparte DB con produccion.
5. Punto superado despues del corte: el repositorio fue preparado en `main`; mantener `git status --short` limpio antes de publicar.

### Altos

1. Punto mitigado despues del corte: credenciales/endpoints Pagadetodo y Pusher fueron externalizados; falta provision/rotacion segura por ambiente.
2. Resolver `npm audit` completo en carril separado.
3. Actualizar Composer o entorno para poder ejecutar `composer audit`.
4. Agregar locks transaccionales para folios generados con `max()+1`.
5. Definir matriz formal de permisos por accion para roles futuros.

### Medios

1. Corregir descarga de archivos con headers/nombre/mime.
2. Retirar o corregir `RoleController` legacy.
3. Separar servicios Pagadetodo de controladores monoliticos.
4. Definir formalmente si `/` sera pantalla publica soportada.
5. Documentar mapa unico de menu `data-menu-target` y mantenerlo sincronizado con Vue.

## Siguientes pasos optimos

1. Preparar GitHub/release sandbox paralelo en una copia o repo limpio, sin cambiar funcionalidad de negocio.
2. Construir `.gitignore` y excluir `.env`, `vendor/`, `node_modules/`, logs, SQLite local, outputs, test-results y archivos accidentales de raiz.
3. Documentar deploy por vhost/subdominio separado con PHP 8.3, sesiones/cache/logs aislados, scheduler deshabilitado y `PAGADETODO_MOCK=true`.
4. Repetir validaciones de release: `route:list`, Unit, Feature WAMP/SQLite, build production, npm runtime audit.
5. Abrir despues carriles separados para sandbox oficial Pagadetodo, secretos e integraciones, y remediacion npm audit.

## Prompt exacto recomendado

```text
Quiero preparar el proyecto Centro de Cobros Fase 34 para subirlo a GitHub y desplegar una version sandbox paralela sin tocar la version productiva actual.

Usa como baseline:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

Objetivo:
- crear o validar un repositorio Git limpio;
- preparar `.gitignore` para excluir `.env`, `vendor/`, `node_modules/`, logs, SQLite local, outputs, test-results y archivos accidentales de raiz;
- definir branch/tag de release sandbox;
- confirmar si los assets compilados se versionaran o si se generaran en CI;
- preparar comandos exactos de deploy y rollback;
- documentar vhost/subdominio separado, PHP 8.3, sesiones/cache/logs aislados, scheduler deshabilitado y `PAGADETODO_MOCK=true` hasta sandbox oficial;
- ejecutar validaciones de rutas, Unit, Feature WAMP/SQLite, build production y npm runtime audit;
- actualizar documentacion con checklist final de publicacion.

Restricciones:
1. No cambiar funcionalidad de negocio.
2. No ejecutar migraciones sobre DB productiva.
3. No publicar secretos ni archivos locales.
4. No activar scheduler en la version paralela.
5. No usar credenciales productivas de Pagadetodo.
6. No tocar `principal.blade.php` ni el contrato publico de assets salvo necesidad tecnica justificada y verificada.
```
