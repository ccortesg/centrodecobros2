# Fase 32 - Pruebas funcionales, ownership y contratos API controlados

Fecha de cierre: 2026-05-27  
Workspace: `C:\temp\centrodecobros_phase32_pruebas_ownership_contratos_api`  
Baseline origen: `C:\temp\centrodecobros_phase31_estabilizacion_funcional_accesos_seguridad`

## Dictamen

`GO tecnico parcial para continuar estabilizacion; NO-GO para liberacion directa sin recuperar DB local/Feature completos, validar sandbox externo Pagadetodo y cerrar deuda npm/secretos en carriles separados`

La fase implemento ownership por recurso, whitelists de busqueda y mocks controlados de Pagadetodo sin reabrir migraciones, sin cambiar nombres de rutas publicas, sin tocar `principal.blade.php` y conservando el contrato publico de assets.

## Cambios implementados

1. Se creo la copia aislada de Fase 32 desde la baseline de Fase 31.
2. Se agregaron helpers compartidos en `Controller`:
   - deteccion de administrador por `idrol=1`;
   - scope propietario para queries;
   - validacion de operacion por `idusuario` y `productivo`;
   - respuesta `403` consistente;
   - whitelists de criterios;
   - paginacion acotada;
   - cliente Pagadetodo controlado por `services.pagadetodo.mock`.
3. `ClienteController` aplica scope por propietario en listado/selector y bloqueo `403` al actualizar clientes ajenos.
4. `ArchivoController` valida que el cliente solo lea, suba, descargue o borre archivos de sus propios clientes.
5. `TransaccionController` aplica ownership en listados, reportes, exportaciones y operaciones de update/delete/activar/desactivar/rechazar; tambien bloquea creacion web contra `idcliente` ajeno.
6. `RespuestaController`, `ConsultaSpeiController`, `PagoSpeiController`, `CancelaSpeiController` y `TransaccionDomController` agregan whitelists y ownership sobre la transaccion vinculada.
7. Exportaciones de clientes, respuestas, SPEI y cargos recurrentes quedan filtradas por propietario para rol cliente.
8. Contratos Pagadetodo se aislaron con mock controlado para:
   - `GenerarLigaPago`;
   - `GenerarLigaDomiciliacion`;
   - `GenerarSpei`;
   - `GenerarLigaLector`;
   - `CargoDomiciliacion`;
   - `CancelarDomiciliacion`.
9. Se agrego `PAGADETODO_MOCK=false` a `.env.example` y la llave `services.pagadetodo.mock` en `config/services.php`.
10. Se agregaron Feature tests de Fase 32 con schema minimo aislado. En este host se saltan de forma explicita porque `pdo_sqlite` no esta instalado.
11. Se agregaron Unit tests de regresion sin DB para ownership, whitelists, exports y contrato mock.

## Matriz de accesos por rol

| Superficie | Admin `idrol=1` | Cliente `idrol=2` | Otros roles | Guest |
| --- | --- | --- | --- | --- |
| Usuarios y roles | Permitido por middleware | `403` | `403` | Login requerido |
| Catalogos admin | Permitido | Bloqueado si no esta en superficie cliente | `403` | Login requerido |
| Clientes | Ve todos | Ve/opera solo clientes con `clientes.idusuario = Auth::id()` | `403` | Login requerido |
| Archivos | Ve/opera todos | Solo archivos de clientes propios | `403` | Login requerido |
| Transacciones | Ve/opera todas | Solo registros propios; no puede crear contra `idcliente` ajeno | `403` | Login requerido |
| Respuestas | Ve/opera todas | Solo respuestas de transacciones propias | `403` | Login requerido |
| SPEI consultas/pagos/cancelaciones | Ve/opera todos | Solo registros vinculados a transacciones propias | `403` por middleware actual en ruta normal; guard listo en controlador | Login requerido |
| Domiciliacion y cargos | Ve/opera todos | Solo registros propios o vinculados a transacciones propias | `403` | Login requerido |
| Exportaciones | Globales | Filtradas por propietario | `403` | Login requerido |
| APIs publicas por token | N/A por sesion | N/A por sesion | N/A | Autenticacion por `User`/`Password` en payload; mock disponible para pruebas |
| Webhooks `Service/*` | N/A | N/A | N/A | Siguen publicos; pendiente firma/idempotencia |

## Validacion ejecutada

| Validacion | Resultado |
| --- | --- |
| Copia aislada desde Fase 31 | OK con `robocopy`; `cp -a` inicial fue descartado por lentitud |
| `php --version` | OK, PHP `8.3.27` |
| `/mnt/c/nvm4w/nodejs/node.exe --version` | OK, Node `v20.20.0`; `node` directo no esta en PATH de bash |
| `npm --version` | OK, `10.8.2` |
| `composer --version` | OK, Composer `2.2.6` |
| `php artisan --version` | OK, Laravel `12.54.1` |
| `php artisan route:list` | OK, 97 rutas |
| `php artisan schedule:list` | OK, 2 tareas: `ejecutarCron` diario 07:00 y `revisarStatus` cada 5 min |
| `composer validate --no-check-publish` | OK, con deprecations del Composer del host |
| `composer audit` | Bloqueado: Composer `2.2.6` no tiene comando `audit` |
| `npm run production` | OK, genera `plantilla.css`, `plantilla.js`, `guest-public.js`, Vite manifest y bridge `public/js/app.js` |
| `npm audit --omit=dev` | OK, 0 vulnerabilidades runtime omitidas dev |
| `npm audit --audit-level=low` | Falla por 29 vulnerabilidades dev/tooling: 5 low, 16 moderate, 8 high |
| `vendor/bin/phpunit --testsuite Unit` | OK, 13 tests, 72 assertions |
| `vendor/bin/phpunit tests/Unit/Phase32OwnershipAndContractSourceTest.php` | OK, 4 tests, 57 assertions |
| `vendor/bin/phpunit tests/Feature/Phase32` | OK con 17 skipped por falta de `pdo_sqlite` |
| `vendor/bin/phpunit --testsuite Feature` | Bloqueado: 13 errores por MySQL `Access denied for user 'centro_user'@'localhost'`, 17 skipped por `pdo_sqlite` ausente |
| Browser guest `/login` con Playwright CLI | OK, renderiza titulo, campos `Usuario`/`Password`, boton `Acceder` y assets |
| Browser guest `/` y `/main` | OK, redirigen a `/login` |
| Browser admin/cliente | Bloqueado por DB local; login queda atado a `centro_user` sin acceso |

## Pruebas agregadas

| Archivo | Cobertura |
| --- | --- |
| `tests/Support/UsesIsolatedCentroCobrosDatabase.php` | Schema minimo aislado, seed admin/cliente A/cliente B y skip explicito si no existe `pdo_sqlite` |
| `tests/Feature/Phase32/AccessOwnershipFeatureTest.php` | Rutas admin vs cliente, clientes, archivos, transacciones, respuestas, SPEI y domiciliacion |
| `tests/Feature/Phase32/PagadetodoContractMockFeatureTest.php` | Contratos mock para ligas, SPEI, lector, cargo y cancelacion de domiciliacion |
| `tests/Unit/Phase32OwnershipAndContractSourceTest.php` | Regresion sin DB para helpers, guards por controlador, whitelists SPEI y exports con owner scope |

## Porcentaje actualizado por modulo

| Modulo / funcionalidad | Avance despues de Fase 32 | Estado y pendientes |
| --- | ---: | --- |
| Entorno, rutas, scheduler y build | 88% | Rutas, scheduler y build OK. Pendiente instalar `pdo_sqlite` o corregir MySQL local para Feature completos; Composer audit requiere Composer moderno. |
| Accesos y autorizacion web | 82% | Middleware y guards por controlador activos. Pendiente browser admin/cliente con DB funcional y politicas formales por accion si aparecen roles nuevos. |
| Clientes, personas y archivos | 82% | Listados, selector, update y archivos ya aplican ownership. Pendiente Feature real con DB y revisar semantica historica `archivos.idpersona`. |
| Usuarios y roles | 76% | Se mantiene hardening de Fase 31. Pendiente pruebas DB reales y definicion fina de administracion de roles. |
| Ligas de pago | 78% | Creacion/listado/export con scopes; mock Pagadetodo cubre contrato de generacion. Pendiente sandbox externo real e idempotencia. |
| Domiciliacion y cancelacion | 77% | Cancelacion y cargo tienen mock; ownership aplicado en transacciones/cargos. Pendiente contrato externo real y reintentos controlados. |
| Cargos recurrentes | 76% | Listados/reportes/export y store quedan acotados por propietario. Pendiente validar scheduler con DB/pasarela real. |
| SPEI | 78% | Consulta, pago y cancelacion agregan whitelists y ownership; `ClientReference` preservado en whitelist donde aplica. Pendiente sandbox para eventos reales y firma/origen de callbacks. |
| Lector / terminal | 70% | Mock para liga lector y validaciones previas heredadas. Pendiente integracion real y cobertura de cancelacion si aplica. |
| Respuestas y webhooks | 70% | Listado/update/delete ahora restringidos por transaccion propietaria. Pendiente firma, idempotencia y validacion de esquema de webhooks. |
| Reportes y exportaciones | 82% | Exportaciones criticas filtran por propietario para cliente. Pendiente Feature real con DB y revisar memoria en volumen alto. |
| Contratos Pagadetodo controlados | 72% | Mock local preparado sin tocar produccion ni rutas publicas. Pendiente sandbox oficial y comparacion contra payloads reales. |
| Pruebas automatizadas | 67% | Unit en verde y Feature Fase 32 listas; bloqueo local queda aislado y documentado. Pendiente habilitar DB local para ejecutar Feature autenticadas. |
| Browser funcional | 68% | Guest validado. Admin/cliente bloqueados por DB local. |
| Seguridad de dependencias y secretos | 60% | No se mezclo remediacion total. `npm audit --omit=dev` limpio, pero full audit sigue abierto; secretos hardcoded siguen como carril separado. |

## Porcentaje actualizado por rol

| Rol / acceso | Avance despues de Fase 32 | Estado y pendientes |
| --- | ---: | --- |
| Administrador (`idrol=1`) | 84% | Mantiene acceso total y ahora convive con guards por recurso. Pendiente browser/Feature con DB real y UAT operativo. |
| Cliente (`idrol=2`) | 82% | Ya no depende solo del menu: controladores y exports restringen recursos propios. Pendiente Feature real con DB y pruebas browser autenticadas. |
| Otros roles | 55% | Bloqueo por defecto sigue vigente. Pendiente decidir si existen roles operativos adicionales y su matriz. |
| Guest publico | 78% | `/login`, `/` y `/main` validan comportamiento basico en browser. Pendiente revisar `/url` con DB/entorno completo y hardening publico. |
| Cliente API externo | 70% | Validaciones de Fase 31 mas mock de contratos principales. Pendiente auth robusta, rate limit, versionado, idempotencia y sandbox real. |
| Proveedor/webhook externo | 46% | Rutas siguen vivas sin cambio de contrato. Pendiente firma/origen, idempotencia y pruebas de reintentos. |
| Scheduler / sistema | 67% | Tareas visibles y ownership no rompe controladores. Pendiente ejecutar jobs contra DB/pasarela controlada. |

## Riesgos residuales

1. DB local sigue bloqueada: MySQL rechaza `centro_user@localhost` y PHP CLI no tiene `pdo_sqlite`.
2. Feature tests de ownership y contratos estan escritos, pero en este host se saltan o fallan por entorno DB.
3. Browser admin/cliente no pudo validarse por login dependiente de DB local.
4. `npm audit --audit-level=low` mantiene 29 vulnerabilidades de dev/tooling; no se remediaron por restriccion de fase.
5. Composer `2.2.6` impide ejecutar `composer audit`.
6. Mocks Pagadetodo no sustituyen validacion con sandbox oficial ni garantizan drift de contrato externo.
7. Credenciales/endpoints hardcoded siguen abiertos por restriccion de no externalizar secretos en esta fase.
8. Webhooks financieros siguen sin firma, idempotencia fuerte o verificacion de origen.
9. Controladores grandes conservan deuda estructural; los guards se agregaron de forma conservadora sin refactor mayor.

## Siguientes pasos optimos

1. Abrir una fase corta de entorno de pruebas para instalar `pdo_sqlite` en PHP CLI o corregir credenciales/dataset MySQL local; despues ejecutar Feature completos y browser admin/cliente.
2. Ejecutar validacion contra sandbox oficial Pagadetodo comparando payloads reales contra los mocks de Fase 32.
3. Abrir carril separado de remediacion npm audit, empezando por dependencias de runtime y luego tooling legacy.
4. Abrir carril separado de externalizacion de secretos/endpoints sin cambiar payloads externos.
5. Endurecer webhooks con firma/origen/idempotencia y pruebas de reintentos.

## Prompt recomendado siguiente

```text
Quiero abrir la Fase 33 de habilitacion de entorno de pruebas, sandbox Pagadetodo y validacion E2E controlada del proyecto Centro de Cobros, partiendo de la baseline:
C:\temp\centrodecobros_phase32_pruebas_ownership_contratos_api

Objetivo:
- crear una copia aislada nueva para Fase 33;
- corregir el entorno local de pruebas sin tocar produccion: habilitar pdo_sqlite en PHP CLI o configurar una DB MySQL local de testing con dataset minimo;
- ejecutar los Feature tests autenticados de Fase 32 para admin, cliente y guest;
- validar browser real de administrador, cliente y guest con datos controlados;
- conectar o simular un sandbox Pagadetodo oficial para comparar payloads reales contra los mocks controlados de Fase 32;
- documentar diferencias de contrato, riesgos y decision de release.

Restricciones:
1. No reabrir migracion estructural de base de datos.
2. No cambiar nombres de rutas publicas ni contratos externos sin evidencia y prueba.
3. No tocar principal.blade.php salvo necesidad tecnica minima y justificada.
4. Mantener el contrato publico de assets.
5. No mezclar remediacion completa de npm audit ni externalizacion completa de secretos.
6. No usar credenciales productivas para pruebas automatizadas.

Entrega esperada:
- Feature tests autenticados ejecutados con resultado;
- evidencia browser admin/cliente/guest;
- comparativa mock vs sandbox Pagadetodo;
- lista de riesgos residuales;
- porcentaje actualizado por modulo y rol despues de Fase 33.
```
