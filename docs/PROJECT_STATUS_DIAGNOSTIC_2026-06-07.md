# Diagnostico tecnico y funcional - Centro de Cobros

Fecha de corte local del workspace: 2026-06-07
Workspace: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`
Alcance ejecutado: analisis de codigo/documentacion y actualizacion documental. No se implemento funcionalidad, no se ejecutaron migraciones, no se tocaron credenciales, scheduler, contratos Pagadetodo ni `principal.blade.php`.

## Dictamen ejecutivo

`GO tecnico parcial mas fuerte para integraciones Pagadetodo en servidor autorizado; NO-GO para pruebas reales Pagadetodo desde local, reemplazo productivo directo no controlado o activacion de scheduler paralelo`.

El proyecto esta en un estado tecnicamente avanzado para operar como sandbox controlado: Laravel 12 carga, hay 100 rutas registradas, Vue 3/Vite estan estabilizados, el contrato publico de assets se preserva, hay ownership para rol cliente en superficies criticas y los carriles Feature SQLite de ownership/UX/Pagadetodo pasan con WAMP PHP 8.3. Addendum 2026-06-08: el propietario confirma que los servicios Pagadetodo ya fueron probados exitosamente desde servidor en sandbox y en productivo; no se pueden probar desde ambiente local porque Pagadetodo restringe las llamadas por IP address de origen. Las brechas restantes son aceptacion operativa y financiera: formalizar evidencia sanitizada de esas pruebas servidor, confirmar firma/origen si el proveedor lo soporta, completar UAT con MySQL/datos reales controlados, evitar scheduler duplicado y resolver el bloqueo de smoke tests MySQL locales.

## Evidencia segura ejecutada

| Validacion | Resultado |
| --- | --- |
| `git status --short` | Sin cambios antes de editar documentacion. |
| `php -v` | PHP CLI WSL `8.3.27`; no trae `pdo_sqlite`, solo `PDO` y `pdo_mysql`. |
| `composer --version` | Composer `2.2.6`. |
| `cmd.exe /C "node -v && npm -v"` | Node Windows `v20.20.0`, npm `10.8.2`. |
| `php artisan --version` | Laravel Framework `12.54.1`. |
| `php artisan route:list` | OK; 100 rutas registradas. Esto corrige documentos vivos que aun mencionaban 97. |
| `php artisan schedule:list` | OK; 2 tareas: `TransaccionDomController@ejecutarCron` diario 07:00 y `TransaccionController@revisarStatus` cada 5 minutos. No se activaron. |
| `php -l` en rutas/controladores criticos | OK en `routes/web.php`, `routes/api.php`, `Administrador.php`, `Controller.php`, `TransaccionController.php`, `TransaccionDomController.php`, `RespuestaController.php`, `PagoRecibidoController.php` y `WebhookIdempotencyFeatureTest.php`. |
| `php vendor/bin/phpunit --testsuite Unit` | OK; 13 tests, 72 assertions, PHP WSL 8.3.27. |
| Feature completo con WAMP PHP 8.3 | Ejecutado; 79 tests, 211 assertions, 13 errores por `SQLSTATE[HY000] [1045] Access denied for user 'centro_user'@'localhost'` en smoke tests que usan MySQL local. |
| Feature aislado WAMP/SQLite | OK; `tests\Feature\Phase32`, `tests\Feature\Phase34` y `tests\Feature\UX`: 52 tests, 170 assertions. |
| `npm run production -- --no-progress` | No ejecutado. No hubo cambios frontend y se evito regenerar assets compilados no versionables. |

## Stack real

| Capa | Estado real observado |
| --- | --- |
| Backend | Laravel `12.54.1`, PHP requerido `^8.2`, PHP local WSL `8.3.27`, WAMP PHP `8.3.0` disponible para SQLite. |
| Dependencias PHP | `guzzlehttp/guzzle`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`, `pusher/pusher-php-server`, `laravel/ui`, `telesign/telesign` residual. |
| Frontend | Vue `3.5.30`, Vite `7.x`, `@vitejs/plugin-vue`, Axios, jQuery/Bootstrap legacy, Laravel Echo/Pusher opcional por `VITE_PUSHER_*`. |
| Build assets | Vite genera `public/build`; scripts locales generan lane legacy y bridge. `public/js/app.js`, `public/js/plantilla.js`, `public/js/guest-public.js` y `public/css/plantilla.css` son contrato publico generado y no se versionan. |
| DB operativa | MySQL real/dump autorizado es fuente funcional; migrations historicas no reconstruyen todo el modelo. Feature aislado usa SQLite con schema minimo en `tests/Support`. |
| Integraciones | Pagadetodo configurado por `config/services.php` y `.env`; mock local por `services.pagadetodo.mock`; llamadas reales Pagadetodo solo desde servidor/IP autorizado. Pusher/Echo se activa solo si existe `VITE_PUSHER_APP_KEY`. |
| Docker | Produccion confirmada por propietario como Docker, pero no hay `Dockerfile` ni `docker-compose.yml` versionados; los comandos exactos dependen del servidor. |

## Arquitectura actual

- Laravel MVC clasico con controladores grandes. `TransaccionController` concentra ligas, SPEI, terminal, caja, APIs legacy, importacion, callbacks y scheduler de revision.
- Vue 3 monta componentes legacy por tags en `resources/assets/js/app.js`; el switchboard visual esta en `resources/views/contenido/contenido.blade.php` y usa enteros `menu`.
- Las rutas API externas no usan prefijo `/api`; `routes/api.php` conserva paths publicos como `Service/*`, `GenerarLigaPago`, `GenerarSpei` y `CargoDomiciliacion`.
- Autorizacion se divide en middleware `Administrador` para acceso grueso y helpers de controlador para ownership por registro.
- Las exportaciones combinan `maatwebsite/excel` y streaming CSV; algunos reportes usan rutas filtradas especificas.
- Scheduler usa metodos de controlador como callbacks; esto funciona, pero aumenta riesgo de duplicidad y acoplamiento.

## Estado general por area

| Area | Avance | Estado real | Pendientes principales |
| --- | ---: | --- | --- |
| Backend Laravel/rutas | 88% | 100 rutas cargan; controladores criticos sin errores de sintaxis. | Reducir controladores monoliticos, formalizar contratos por accion. |
| Frontend Vue/Vite | 84% | Componentes principales registrados; UX base compartida existe; assets compilados excluidos de Git. | Completar migracion responsive de listados financieros, browser smoke real. |
| Base de datos | 66% | Dump historico contiene tablas reales; migrations no son canonicas. | UAT con MySQL real, FKs/relaciones formales, migraciones controladas pendientes. |
| Seguridad/ownership | 80% | Middleware rol 1/2 y ownership en superficies criticas; tests SQLite verdes. | Politicas por accion, firma/origen webhook, rate limit/API externa. |
| Pagadetodo/API externa | 87% | Mock controlado e idempotencia local `Service/*` cubiertos por tests; propietario confirma pruebas exitosas en servidor sandbox y productivo. | Evidencia sanitizada de servidor, firma/origen si aplica, versionado/rate limit. |
| Scheduler | 70% | Dos tareas registradas; no se tocaron ni activaron. | Jobs/comandos dedicados, locks, validacion en sandbox aislado. |
| Documentacion viva | 86% | Buen volumen de docs; se actualiza este snapshot. | Mantener historicos como historicos y corregir refs vivas obsoletas. |
| Readiness sandbox paralelo | 82% | Viable con Docker/vhost aislado, scheduler apagado y `PAGADETODO_MOCK=true` en local; pruebas reales Pagadetodo deben correr solo desde servidor/IP autorizado. | Compose real documentado, UAT, evidencia Pagadetodo sanitizada y secretos por ambiente. |
| Readiness reemplazo productivo | 58% | No recomendado. | Aprobacion negocio, pruebas reales, rollback probado, proveedor validado. |

## Brechas documentacion vs codigo

| Brecha | Evidencia | Severidad | Accion documental |
| --- | --- | --- | --- |
| Conteo de rutas desactualizado | `php artisan route:list` muestra 100 rutas; docs vivos mencionaban 97. | Media | Actualizar documentos rectores; dejar docs historicos como snapshot de fase. |
| Feature completo descrito como verde | En este entorno falla por MySQL local `centro_user@localhost`; carril SQLite aislado si pasa. | Alta | Documentar diferencia entre smoke MySQL y Feature SQLite. |
| Secretos descritos como hardcoded en docs historicos | Codigo vigente usa `config/services.php`, `.env.example` y `VITE_PUSHER_*`; quedan defaults productivos y provision/rotacion. | Media | Usar lenguaje vigente: externalizados; Pagadetodo real validado en servidor, no reproducible local por IP de origen. |
| `ReporteTransacciones.vue` mencionado | No existe en `resources/assets/js/components`; reportes reales son `ReporteLigas*`, `ReporteSpei`, `ReporteCargosRecurrentes`. | Baja | Corregir modulo Transacciones. |
| API sin prefijo `/api` | `routes/api.php` registra rutas legacy directas. | Alta si se cambia | Mantener compatibilidad y documentar como contrato. |
| Docker productivo no versionado | No hay `Dockerfile`/`docker-compose.yml`; docs dicen produccion Docker confirmada. | Media | No inventar comandos; pedir/inspeccionar compose real. |
| `Archivo` fillable typo | `app/Archivo.php` tiene `hasname`; controlador usa `hashname`. Asignacion directa funciona, mass assignment podria fallar. | Baja | Documentar pendiente tecnico, no corregir en esta fase. |

## Diagnostico por modulo/pantalla

| Modulo / pantalla | Rutas backend | UI Vue/Blade | Controladores/modelos/tablas | Funcionalidad existente | Avance | Pendiente principal | Sev. | Complejidad |
| --- | --- | --- | --- | --- | ---: | --- | --- | --- |
| Shell autenticado | `GET /main`, `POST /logout` | `principal.blade.php`, `contenido.blade.php`, sidebars | Auth/Login, middleware | Monta dashboard y menus por rol 1/2; `principal.blade.php` intacto. | 86% | Browser smoke real con proxy HTTPS y sesion autorizada. | Alta | Media |
| Dashboard | `GET /dashboard` | `Dashboard.vue` | `DashboardController`, `transacciones`, `transaccionesDom` | Sumatorias por mes/usuario/productivo; compatible MySQL/SQLite. | 82% | Definir metricas exactas y validar con datos reales por rol. | Media | Media |
| Notificaciones | `POST /notification/get`, `broadcasting/auth` | `Notification.vue`, Echo/Pusher opcional | `NotificationController`, `Notification`, `notifications` | Polling y limpieza de notificaciones antiguas; realtime opcional. | 64% | Validar Pusher/Echo E2E con credenciales aisladas. | Media | Media |
| Login / guest `/url` | `/`, `/url`, `/login` | `auth/login.blade.php`, `transaccion/*.blade.php`, `guest-public.js` | `TransaccionController@showForm/openPublic/storePublic` | Login y URL guest legacy; tests publicos basicos existen. | 76% | Browser smoke publico y decision de UX para entrada `/`. | Media | Baja |
| Estados | `estado/*` | `Estado.vue` | `EstadoController`, `Estado`, `estados` | CRUD, activar/desactivar, filtro status corregido. | 72% | Validaciones backend mas estrictas y UAT con catalogo real. | Media | Baja |
| Ciudades | `ciudad/*` | `Ciudad.vue` | `CiudadController`, `Ciudad`, `ciudades`, `estados` | CRUD, selector, offset/status corregidos. | 72% | Homologar paginacion/validaciones y probar volumen real. | Media | Baja |
| Clientes | `cliente/*`, `archivo/*` | `Cliente.vue` | `ClienteController`, `ArchivoController`, `Persona`, `Cliente`, `Archivo` | Alta/edicion/listado/export, archivos y ownership. | 84% | Formalizar validaciones, revisar `Archivo::$fillable` `hasname/hashname`. | Media | Media |
| Consolidar clientes | `cliente/consolidar*` | `ClienteConsolidar.vue` | `ClienteController`, `clientes`, `personas`, relaciones | Merge admin con transaccion, locks y reasignacion. | 78% | Auditoria UI y pruebas con dataset real. | Media | Media |
| Depurar clientes | `cliente/depurar*` | `ClienteDepurar.vue` | `ClienteController`, `clientes`, `personas`, relaciones | Delete fisico solo admin con elegibilidad. | 76% | Definir si negocio requiere soft delete/auditoria. | Media | Media |
| Usuarios | `user/*` | `User.vue` | `UserController`, `User`, `Persona`, `Rol` | CRUD, password hash no se expone en listado. | 82% | Politicas formales por accion y validaciones de password. | Alta | Media |
| Roles | `rol`, `role`, `rol/selectRol` | `Rol.vue`, `Role.vue` alias | `RolController`, `Rol`; `RoleController` queda legacy no ruteado | Listado/selector de roles; menu solo admin. | 72% | Retirar o documentar `RoleController`/`Role.vue` legacy cuando haya fase de limpieza. | Baja | Baja |
| Ligas de pago | `transaccion`, `registrar`, `exportar`, `GenerarLigaPago` | `Transaccion.vue`, `ReporteLigas.vue` | `TransaccionController`, `Transaccion`, `Cliente`, `Respuesta` | Generacion web/API, importacion masiva, listado y export; Pagadetodo validado en servidor sandbox/productivo segun propietario. | 87% | Concurrencia de folios, adapter externo y evidencia sanitizada. | Alta | Alta |
| Domiciliacion | `transaccion/registrarDom`, `GenerarLigaDomiciliacion`, `CancelarDomiciliacion` | `Transaccion.vue`, `ReporteLigasDom.vue` | `TransaccionController`, `CancelacionDom`, `Transaccion` | Alta liga domiciliada, cancelacion, estados `condicion`; Pagadetodo validado en servidor sandbox/productivo segun propietario. | 86% | Ejecutar migraciones controladas de campos nuevos antes de uso servidor y documentar evidencia. | Alta | Alta |
| Domiciliacion Activa | `GET domiciliacion-activa` | `DomiciliacionActiva.vue` | `TransaccionController@domiciliacionActiva`, `respuestas` | Lista domiciliaciones aprobadas activas/canceladas, cargo manual/cancelar. | 82% | Browser QA admin/cliente y reglas de status con negocio. | Alta | Media |
| Cargos recurrentes | `transaccionDom/*`, `CargoDomiciliacion` | `TransaccionDom.vue`, `ReporteCargosRecurrentes.vue` | `TransaccionDomController`, `TransaccionDom`, `transaccionesDom` | Cargo web/API, reportes, control de intentos/proximo cargo. | 80% | Locks/jobs para scheduler y pruebas de concurrencia/idempotencia. | Critica | Alta |
| Respuestas | `respuesta/*` | `Respuesta.vue` | `RespuestaController`, `Respuesta`, `respuestas` | Listado/export/CRUD y filtros por status; ownership. | 81% | Firma/origen, callbacks con reintentos formalizados. | Alta | Alta |
| Webhooks `Service/*` | `EntregarPagoLiga*`, `EntregarPagoLector`, SPEI services | Sin UI directa | `RespuestaController`, `TransaccionController` | Validacion minima, idempotencia local, errores controlados; validacion real servidor confirmada por propietario. | 84% | Esquema formal, monitoreo y firma/origen si aplica. | Alta | Alta |
| SPEI generar | `transaccion/registrarSpei`, `GenerarSpei` | `Transaccion.vue`, menu 14 | `TransaccionController`, `Transaccion` | Genera referencia/CLABE por mock/servicio. | 82% | Validar contrato real y cliente/email obligatorio. | Alta | Alta |
| SPEI consulta | `consultaspei`, `Service/ConsultaClabe` | `ConsultaSpei.vue`, `ReporteSpei.vue` | `ConsultaSpeiController`, `TransaccionController`, `ConsultaSpei` | Consulta y guarda respuesta controlada si referencia vacia/no encontrada. | 83% | Evidencia servidor sanitizada y volumen/export real. | Alta | Media |
| SPEI pago | `pagospei`, `Service/PagoClabe` | `PagoSpei.vue`, `ReporteSpei.vue` | `PagoSpeiController`, `TransaccionController`, `PagoSpei` | Pago idempotente por `transaccion`, filtros `condicion/enviada`. | 84% | Firma/origen y reglas de reintento/proveedor. | Alta | Alta |
| SPEI cancelacion | `cancelaspei`, `Service/CancelaClabe` | `CancelaSpei.vue` | `CancelaSpeiController`, `TransaccionController`, `CancelaSpei` | Cancelacion idempotente por `transaccion + autorizacion`. | 83% | Fixtures reales y comportamiento ante pago inexistente en UAT. | Alta | Media |
| Pago en Caja | `transaccion` tipo 3, `respuesta` tipo 3 | `Transaccion.vue`, `Respuesta.vue`, menu 14/15 | `TransaccionController`, `RespuestaController` | Comparte tipo tecnico `3` con referencia; reportes distinguen parcialmente. | 75% | Confirmar con negocio separacion Caja vs SPEI historica. | Media | Media |
| Pago con Terminal | `transaccion` tipo 4, `GenerarLigaLector`, `EntregarPagoLector` | `Transaccion.vue`, `Respuesta.vue`, `ReporteLigas.vue` tipo 4 | `TransaccionController`, `RespuestaController` | Liga/lector con webhook validado/idempotente; pruebas servidor Pagadetodo exitosas segun propietario. | 84% | Evidencia sanitizada y cancelacion lector si negocio la requiere. | Alta | Alta |
| Pagos Recibidos | `pagos-recibidos`, `pagos-recibidos/status` | `PagoRecibido.vue` | `PagoRecibidoController`, `PagoRecibido`, `respuestas`, `pagospei`, `transaccionesDom` | Vista unificada de pagos aprobados, montos normalizados, rango fechas. | 82% | Export/filtro canal si negocio lo requiere; aclarar Caja/SPEI. | Media | Media |
| Reportes | `exportar*`, `reporte*` | `ReporteLigas`, `ReporteLigasDom`, `ReporteSpei`, `ReporteCargosRecurrentes` | `Transaccion*Controller`, `PagoSpeiController`, exports | Reportes por modulo y exportaciones filtradas. | 78% | Smoke MySQL/export volumen; docs corregidas por componente inexistente. | Alta | Media |
| Importacion masiva | `transaccion/importar/*` | `Transaccion.vue` | `TransaccionController`, storage local | Importa ligas/domiciliacion con progreso, cancelacion y log. | 77% | Pruebas con plantilla real, limpieza de storage y validacion de concurrencia. | Media | Media |
| UX/UI responsive | N/A | Componentes Vue + `ux-ui.css` | Frontend | Helpers y clases compartidas existen; pilotos aplicados. | 72% | Migrar listados financieros restantes y browser smoke mobile/desktop. | Media | Media-Alta |
| Build/deploy Docker | N/A | Build Vite/legacy | Scripts `scripts/local/*`, docs deploy | Node Windows disponible; assets no versionados; Docker servidor externo. | 74% | Documentar compose real y CI/deploy final; no inventar servicios. | Alta | Media |

## Diagnostico por rol/acceso

| Rol/acceso | Middleware/rutas permitidas | Menus visibles | Funcionalidades operables | Gaps reales | Avance |
| --- | --- | --- | --- | --- | ---: |
| Administrador `idrol=1` | Acceso total al grupo `Administrador`; auth para dashboard/main/logout. | Todo el sidebar admin: catalogos, ligas, domiciliacion, SPEI, caja, terminal, pagos recibidos, reportes, acceso. | CRUD/consulta/export casi completo, APIs internas, consolidar/depurar, usuarios/roles. | UAT real, politicas por accion, control de scheduler/contratos. | 88% |
| Cliente `idrol=2` | Allowlist: clientes/archivos propios, transacciones tipos visibles, respuestas lectura/export, domiciliacion activa, cargos recurrentes, pagos recibidos, importacion y reportes permitidos. | Sidebar cliente: clientes, ligas, domiciliacion, pagos recibidos, reportes, ayuda/acerca. No muestra SPEI ni usuarios/roles. | Operacion propia con ownership; tests cubren no ver registros ajenos. | Definir si cliente debe operar SPEI/caja/terminal; browser UAT y permisos finos por accion. | 86% |
| Otros roles | Middleware responde `403` en grupo protegido. | No hay sidebar especifico; `contenido.blade.php` no renderiza experiencia util. | Bloqueo funciona por tests. | Si negocio requiere nuevos roles, falta matriz de permisos, menus y policies. | 58% |
| Guest publico | Grupo `guest`: `/`, `/url`, `/login`. | Sin sidebar autenticado. | Login y flujo `/url`; entrada `/` es legacy. | UX de entrada publica y smoke browser real. | 76% |
| Cliente API externo | Rutas legacy `Generar*`, `CargoDomiciliacion`, `CancelarDomiciliacion`; auth por payload `User`/`Password`. | N/A | Mock y validaciones tempranas; contratos existentes conservados; pruebas servidor sandbox/productivo exitosas segun propietario. | Rate limit, versionado, errores consistentes y evidencia sanitizada. | 82% |
| Proveedor/webhook externo | `Service/*` sin auth Laravel; depende de contrato proveedor. | N/A | Validacion minima, idempotencia local y pruebas servidor exitosas segun propietario. | Firma/origen, IP allowlist si aplica, monitoreo/reintentos. | 76% |
| Scheduler/sistema | `schedule:list` registra dos tareas. | N/A | Programacion existe; no se activo en esta fase. | Locks, jobs dedicados, garantia de una sola instancia. | 70% |

## Hallazgos ordenados por severidad

| Severidad | Hallazgo | Causa raiz | Riesgo | Siguiente accion |
| --- | --- | --- | --- | --- |
| Critica | Scheduler puede duplicar efectos si dos instancias comparten DB. | Callbacks programados sin locks ni orquestacion documentada. | Duplicar cargos recurrentes o cambios de status. | Mantener apagado en sandbox paralelo; extraer jobs con locks. |
| Alta | Las llamadas reales Pagadetodo no son reproducibles desde local. | Pagadetodo restringe el IP address de origen de las llamadas API. | Un agente puede perder tiempo intentando validar local o confundir bloqueo de red con bug de codigo. | Validar Pagadetodo solo desde servidor/IP autorizado; usar `PAGADETODO_MOCK=true` local. |
| Alta | Webhooks no validan firma/origen. | No hay especificacion Pagadetodo disponible. | Payload externo falso o reintentos no autenticos. | Pedir especificacion y agregar verificacion compatible. |
| Alta | Feature completo no es reproducible en este entorno por MySQL local. | Tests smoke usan DB MySQL `centrodecobros` con usuario local no autorizado. | Falsa confianza si se reporta suite completa verde. | Usar carril SQLite aislado o provisionar MySQL local seguro. |
| Alta | Docker productivo no esta versionado ni documentado en repo. | Compose/orquestacion vive en servidor. | Deploy/rollback ambiguos para agentes. | Inspeccionar/recibir compose real y actualizar runbook. |
| Alta | Controladores financieros son monoliticos. | Logica historica acumulada en `TransaccionController` y `TransaccionDomController`. | Cambios locales pueden romper API, reportes, webhooks o scheduler. | Extraer adapters/servicios por carriles pequenos. |
| Media | Rutas/docs desalineadas. | Conteos historicos quedaron en docs vivos. | Agentes planean con inventario incorrecto. | Este snapshot fija 100 rutas; actualizar docs vivas. |
| Media | Caja vs SPEI comparten tipo tecnico `3`. | Modelo historico no separa canal con columna independiente. | Reporte/Pagos Recibidos puede etiquetar historicos ambiguos. | Confirmar regla con negocio antes de cambios. |
| Media | UI responsive incompleta. | Migracion UX por etapas; muchas tablas anchas siguen legacy. | Uso dificil en mobile/laptop y regresiones visuales. | Migrar listados financieros prioritarios con browser smoke. |
| Baja | `Archivo::$fillable` usa `hasname` y controlador `hashname`. | Typo historico en modelo. | Mass assignment futura podria fallar; hoy se asigna directo. | Corregir en fase tecnica con test acotado. |

## Riesgos activos

1. Pagadetodo real solo debe validarse desde servidor/IP autorizado; local queda bloqueado por restriccion de origen.
2. Firma/origen de webhooks pendiente.
3. Scheduler financiero sin locks y con callbacks a controladores.
4. Smoke MySQL local bloqueado por credenciales; UAT MySQL real pendiente.
5. Docker compose productivo no versionado en repo.
6. Migrations no canonicas frente al dump operativo.
7. Folios por `max()+1` expuestos a carreras.
8. Realtime Pusher/Echo no validado E2E.
9. UI responsive y accesibilidad aun parciales en listados financieros.
10. Historicos de documentacion contienen referencias superadas; distinguir snapshot historico de guia viva.

## Pendientes priorizados

| Prioridad | Pendiente | Modulo | Severidad | Complejidad |
| --- | --- | --- | --- | --- |
| P0 | Mantener scheduler apagado en sandbox paralelo hasta tener locks/una instancia garantizada. | Scheduler | Critica | Media |
| P1 | Documentar evidencia sanitizada de las pruebas Pagadetodo servidor sandbox/productivo. | Integraciones | Alta | Baja-Media |
| P1 | Provisionar runner MySQL local/controlado o ajustar smoke a SQLite preparado. | Pruebas | Alta | Media |
| P1 | Documentar compose Docker real y comandos de deploy/rollback validados. | Deploy | Alta | Media |
| P1 | Agregar firma/origen de webhooks cuando Pagadetodo entregue especificacion. | Webhooks | Alta | Media-Alta |
| P1 | Browser smoke autenticado admin/cliente/guest en servidor sandbox. | UX/UAT | Alta | Media |
| P2 | Extraer adapter Pagadetodo y servicios SPEI/domiciliacion. | Backend | Alta | Alta |
| P2 | Formalizar matriz de permisos si aparecen roles nuevos. | Acceso | Media | Media |
| P2 | Completar responsive table shell/cards en listados financieros. | Frontend | Media | Media-Alta |
| P3 | Corregir typo `Archivo::$fillable` y documentar contrato de archivos. | Clientes | Baja | Baja |

## Siguientes pasos optimos

1. Cerrar carril de entorno de validacion: decidir si smoke MySQL se provisiona con usuario/dataset local seguro o si se adapta a SQLite preparado sin tocar produccion.
2. Preparar UAT sandbox: Docker/vhost aislado, `.env` sandbox, scheduler apagado, assets generados en deploy y logs separados; llamadas reales Pagadetodo solo desde servidor/IP autorizado.
3. Solicitar o conservar evidencia sanitizada de las pruebas Pagadetodo sandbox/productivo ya exitosas, sin exponer secretos ni payloads sensibles.
4. Pedir a Pagadetodo especificacion de firma/origen si existe; no usar credenciales productivas para pruebas automatizadas fuera del servidor autorizado.
5. Ejecutar browser smoke real admin/cliente/guest sobre sandbox con datos controlados.
6. Abrir carril pequeno para scheduler: jobs/comandos con locks y prueba de una sola instancia antes de cualquier activacion.

## Prompt optimo para la siguiente iteracion

```text
Trabaja sobre:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

No crees carpetas nuevas. No ejecutes migraciones productivas. No modifiques credenciales, scheduler ni contratos Pagadetodo. No toques principal.blade.php salvo necesidad tecnica justificada y verificada. No versionar assets compilados.

Objetivo:
Preparar un carril de validacion reproducible para cerrar la brecha de pruebas Feature/Smoke del proyecto Centro de Cobros sin tocar produccion ni intentar llamadas reales Pagadetodo desde local.

Contexto obligatorio:
- docs/PROJECT_STATUS_DIAGNOSTIC_2026-06-07.md
- docs/CODEX_CONVERSATION_HANDOFF.md
- docs/PROJECT_OPERATING_MODEL.md
- docs/ROUTES_AND_FLOW.md
- tests/Support/UsesIsolatedCentroCobrosDatabase.php
- tests/Feature/Smoke/
- tests/Feature/Phase32/
- tests/Feature/Phase34/
- tests/Feature/UX/

Tareas:
1. Diagnosticar por que los smoke tests Feature usan MySQL local y fallan con Access denied para centro_user@localhost.
2. Proponer e implementar la opcion mas segura para hacerlos reproducibles sin migrar DB productiva:
   - adaptar smoke a SQLite aislado preparado; o
   - documentar/provisionar MySQL local de testing con dataset minimo no productivo.
3. Mantener Pagadetodo local en mock: no intentar llamadas reales desde local porque el proveedor restringe por IP de origen.
4. Ejecutar:
   - php artisan route:list
   - php artisan schedule:list
   - php vendor/bin/phpunit --testsuite Unit
   - Feature aislado WAMP/SQLite
   - Feature completo solo si el runner queda seguro y no toca produccion
5. Actualizar documentacion con resultados, causa raiz y comandos exactos.

Restricciones:
1. No usar credenciales productivas.
2. No ejecutar migraciones productivas.
3. No activar scheduler.
4. No cambiar logica de negocio salvo ajustes estrictamente necesarios en tests/fixtures.
5. Redactar cualquier secreto como [secreto omitido].
```
