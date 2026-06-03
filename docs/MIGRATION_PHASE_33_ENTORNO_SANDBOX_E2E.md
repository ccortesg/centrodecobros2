# Fase 33 - Entorno de pruebas, sandbox Pagadetodo y validacion E2E controlada

Fecha de cierre: 2026-05-27  
Workspace: `C:\temp\centrodecobros_phase33_entorno_sandbox_e2e`  
Baseline origen: `C:\temp\centrodecobros_phase32_pruebas_ownership_contratos_api`

## Dictamen

`GO tecnico parcial fuerte para continuar validacion controlada; NO-GO para liberacion directa hasta validar sandbox Pagadetodo oficial, cerrar webhooks/idempotencia y tratar npm audit/secretos en carriles separados`

La fase recupero ejecucion real de Feature tests autenticados usando PHP CLI de WAMP con `pdo_sqlite`, preparo una DB SQLite persistente para navegador, valido browser real de admin/cliente/guest y mantuvo sin cambios `principal.blade.php`, nombres de rutas publicas y contrato publico de assets.

## Cambios implementados

1. Se creo la copia aislada `C:\temp\centrodecobros_phase33_entorno_sandbox_e2e`.
2. Se identifico que el PHP Linux del host no tiene `pdo_sqlite`, pero `C:\wamp64\bin\php\php8.3.0\php.exe` si tiene drivers `mysql,sqlite`.
3. Se habilito un entorno local de tests sin tocar produccion:
   - Feature tests aislados con SQLite `:memory:`;
   - DB persistente `storage/phase33_browser.sqlite` para browser real;
   - script `scripts/local/prepare_phase33_browser_sqlite.php`.
4. Se corrigieron errores detectados al ejecutar las Feature reales:
   - `orderBy('id')` ambiguos en controladores con joins;
   - expectativa de folio en contrato mock de lector;
   - `DashboardController` ahora soporta MySQL y SQLite para extraccion mes/anio;
   - schema de pruebas agrega tabla `notifications` para el shell autenticado;
   - smoke DB soporta `SHOW TABLES` en MySQL y `sqlite_master` en SQLite.
5. Se mantuvo el mock Pagadetodo controlado por `services.pagadetodo.mock`.
6. No se hicieron llamadas a credenciales productivas ni se cambio el contrato externo.

## Entorno de pruebas validado

| Item | Resultado |
| --- | --- |
| PHP Linux CLI | `8.3.27`, sin `pdo_sqlite`, mantiene `pdo_mysql` |
| PHP WAMP CLI | `8.3.0`, drivers PDO `mysql,sqlite` |
| SQLite browser | `storage/phase33_browser.sqlite`, generado por script local |
| Laravel | `12.54.1` |
| Rutas | 97 rutas |
| Scheduler | 2 tareas |
| Node | `v20.20.0` via `/mnt/c/nvm4w/nodejs/node.exe`; `node` directo no esta en PATH bash |
| npm | `10.8.2` |
| Composer | `2.2.6`, sin comando `audit` |

## Validacion ejecutada

| Validacion | Resultado |
| --- | --- |
| Copia aislada desde Fase 32 | OK |
| `C:\wamp64\bin\php\php8.3.0\php.exe artisan --version` | OK, Laravel `12.54.1` |
| Drivers PDO WAMP | OK, `mysql,sqlite` |
| `vendor/bin/phpunit tests/Feature/Phase32` con PHP WAMP | OK, 17 tests, 49 assertions |
| `vendor/bin/phpunit --testsuite Unit` con PHP WAMP | OK, 13 tests, 72 assertions |
| `vendor/bin/phpunit tests/Feature/Smoke/AuthenticatedReadOnlySmokeTest.php` con DB SQLite | OK, 7 tests, 74 assertions |
| `vendor/bin/phpunit --testsuite Feature` con DB SQLite | OK, 44 tests, 199 assertions |
| `php artisan route:list` | OK, 97 rutas |
| `php artisan schedule:list` | OK, 2 tareas |
| `composer validate --no-check-publish` | OK, con deprecations del Composer del host |
| `composer audit` | Bloqueado: Composer `2.2.6` no tiene comando `audit` |
| `npm run production` | OK, contrato de assets regenerado/verificado |
| `npm audit --omit=dev` | OK, 0 vulnerabilidades |
| `npm audit --audit-level=low` | Falla por 29 vulnerabilidades dev/tooling: 5 low, 16 moderate, 8 high |

## Evidencia browser

Servidor usado:

```text
http://127.0.0.1:8133
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=C:\temp\centrodecobros_phase33_entorno_sandbox_e2e\storage\phase33_browser.sqlite
PAGADETODO_MOCK=true
```

| Rol | Usuario | Resultado |
| --- | --- | --- |
| Guest | sin sesion | `/main` redirige a `/login`; login renderiza titulo, campos `Usuario`/`Password` y boton `Acceder` |
| Administrador | `admin` / `secret` | Login OK, `/main` renderiza shell admin, sidebar completo, `notification/get` 200, `dashboard` 200, 0 errores de consola |
| Cliente | `client-a` / `secret` | Login OK, `/main` renderiza shell cliente acotado, `notification/get` 200, `dashboard` 200, 0 errores de consola |

## Comparativa Pagadetodo

| Contrato | Mock controlado Fase 32/33 | Sandbox oficial |
| --- | --- | --- |
| `GenerarLigaPago` | Devuelve `code=success`, `url`, `reference`, `referenceEmisor`; persiste transaccion tipo 1 | No conectado en esta fase por falta de URL/credenciales oficiales separadas de produccion |
| `GenerarLigaDomiciliacion` | Devuelve `code=success`, `url`, `reference`, `referenceEmisor`; persiste transaccion tipo 2 | No conectado en esta fase |
| `GenerarSpei` | Devuelve `Error=null`, `Message`, `Folio`, `Account`, `Date`, `Clabe`; persiste transaccion tipo 3 | No conectado en esta fase |
| `GenerarLigaLector` | Devuelve `code=success`, `message`, `codeQR`, `reference`, `referenceEmisor`; persiste transaccion tipo 4 | No conectado en esta fase |
| `CargoDomiciliacion` | Devuelve `code=00`, `message`, `token`, `txResponse`; persiste cargo recurrente | No conectado en esta fase |
| `CancelarDomiciliacion` | Devuelve `code=success`, `message`, `reference`; persiste cancelacion | No conectado en esta fase |

Decision: la simulacion controlada es suficiente para regresiones internas y no usa produccion. No sustituye la validacion contractual con sandbox oficial; esa queda como siguiente fase antes de release.

## Porcentaje actualizado por modulo

| Modulo / funcionalidad | Avance despues de Fase 33 | Estado y pendientes |
| --- | ---: | --- |
| Entorno, rutas, scheduler y build | 92% | Rutas, scheduler, build y Feature completos OK con PHP WAMP + SQLite. Pendiente normalizar PHP Linux o documentar WAMP como runner oficial local. |
| Accesos y autorizacion web | 86% | Browser admin/cliente/guest OK y ownership de Fase 32 validado en Feature. Pendiente UAT con dataset real. |
| Clientes, personas y archivos | 86% | Feature ownership y browser cliente/admin validan superficie principal. Pendiente revisar semantica historica `archivos.idpersona`. |
| Usuarios y roles | 80% | Admin browser y tests mantienen hardening. Pendiente politica fina si aparecen roles adicionales. |
| Ligas de pago | 82% | Mock contrato y Feature pasan. Pendiente sandbox oficial Pagadetodo. |
| Domiciliacion y cancelacion | 81% | Cargo/cancelacion mock y ownership pasan. Pendiente reintentos e idempotencia real. |
| Cargos recurrentes | 80% | Listados/reportes y scheduler visibles; Feature no rompe. Pendiente ejecutar job con pasarela controlada. |
| SPEI | 82% | Consultas/pagos/cancelaciones con ownership y whitelists en verde. Pendiente eventos reales de sandbox y webhooks firmados. |
| Lector / terminal | 74% | Contrato mock corregido con folio esperado. Pendiente contrato externo real. |
| Respuestas y webhooks | 72% | Listados y ownership pasan. Pendiente firma/origen/idempotencia de `Service/*`. |
| Reportes y exportaciones | 84% | Smoke autenticado y exports scoped sin ruptura. Pendiente volumen y memoria con dataset real. |
| Contratos Pagadetodo controlados | 76% | Simulacion local completa. Pendiente comparativa oficial con sandbox real. |
| Pruebas automatizadas | 86% | Unit y Feature completos en verde con runner WAMP/SQLite. Pendiente CI o comando documentado cross-host. |
| Browser funcional | 85% | Guest/admin/cliente OK con 0 errores de consola. Pendiente UAT contra dataset real. |
| Seguridad de dependencias y secretos | 61% | Sin cambios de alcance. Runtime npm omit-dev limpio; full audit y secretos siguen en carriles separados. |

## Porcentaje actualizado por rol

| Rol / acceso | Avance despues de Fase 33 | Estado y pendientes |
| --- | ---: | --- |
| Administrador (`idrol=1`) | 88% | Browser y Feature completos OK con dataset controlado. Pendiente UAT real y sandbox externo. |
| Cliente (`idrol=2`) | 87% | Browser cliente y ownership por recurso OK. Pendiente pruebas con clientes/datos reales. |
| Otros roles | 58% | Bloqueo por defecto sigue vigente. Pendiente definicion funcional si existen roles adicionales. |
| Guest publico | 84% | `/main` redirige a `/login`; login OK. Pendiente hardening publico adicional. |
| Cliente API externo | 72% | Contratos mock principales pasan. Pendiente sandbox oficial, rate limit, versionado e idempotencia. |
| Proveedor/webhook externo | 48% | Rutas sin cambio de contrato. Pendiente firma, origen, idempotencia y reintentos. |
| Scheduler / sistema | 70% | Tareas visibles y tests no rompen. Pendiente ejecutar jobs contra DB/pasarela controlada. |

## Riesgos residuales

1. El PHP Linux local sigue sin `pdo_sqlite`; el runner funcional es WAMP PHP `8.3.0`.
2. MySQL local con `centro_user` sigue rechazando conexion; la validacion automatizada queda aislada en SQLite.
3. No se conecto sandbox oficial Pagadetodo; solo se simulo el contrato local.
4. `npm audit --audit-level=low` mantiene 29 vulnerabilidades dev/tooling.
5. Composer `2.2.6` impide ejecutar `composer audit`.
6. Credenciales/endpoints hardcoded siguen pendientes por restriccion de fase.
7. Webhooks financieros siguen sin firma, idempotencia fuerte o verificacion de origen.
8. La DB SQLite de pruebas es dataset minimo, no reemplaza UAT con datos reales.

## Siguientes pasos optimos

1. Obtener URL/credenciales de sandbox oficial Pagadetodo no productivas y ejecutar comparativa real contra los mocks.
2. Endurecer webhooks `Service/*` con firma/origen/idempotencia y pruebas de reintento.
3. Convertir el runner WAMP/SQLite en comando documentado o CI local reproducible.
4. Abrir carril separado de `npm audit`.
5. Abrir carril separado de externalizacion de secretos.

## Prompt recomendado siguiente

```text
Quiero abrir la Fase 34 de validacion oficial Pagadetodo, webhooks e idempotencia del proyecto Centro de Cobros, partiendo de la baseline:
C:\temp\centrodecobros_phase33_entorno_sandbox_e2e

Objetivo:
- crear una copia aislada nueva para Fase 34;
- usar exclusivamente credenciales y URL de sandbox Pagadetodo no productivas;
- comparar respuestas reales de sandbox contra los mocks controlados de Fase 33 para ligas, SPEI, lector, cargo y cancelacion de domiciliacion;
- documentar diferencias de contrato y ajustar solo adaptadores internos sin cambiar nombres de rutas publicas;
- endurecer webhooks Service/* con validacion de esquema, idempotencia y verificacion de origen/firma si el proveedor lo soporta;
- agregar pruebas Feature/Unit de contratos reales simulados, webhooks duplicados y reintentos;
- actualizar documentacion rectora con riesgos, porcentajes y decision de release.

Restricciones:
1. No usar credenciales productivas.
2. No reabrir migracion estructural de base de datos.
3. No cambiar nombres de rutas publicas ni payloads externos sin evidencia de sandbox y prueba.
4. No tocar principal.blade.php salvo necesidad tecnica minima y justificada.
5. Mantener el contrato publico de assets.
6. No mezclar remediacion completa de npm audit ni externalizacion completa de secretos.

Entrega esperada:
- evidencia sandbox Pagadetodo real o bloqueo documentado;
- matriz mock vs sandbox por endpoint;
- pruebas de webhooks/idempotencia;
- pruebas ejecutadas con resultado;
- riesgos residuales;
- porcentaje actualizado por modulo y rol despues de Fase 34.
```
