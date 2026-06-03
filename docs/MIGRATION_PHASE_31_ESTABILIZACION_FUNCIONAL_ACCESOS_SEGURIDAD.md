# Fase 31 - Estabilizacion funcional, accesos y seguridad

Fecha de cierre: 2026-05-27  
Workspace: `C:\temp\centrodecobros_phase31_estabilizacion_funcional_accesos_seguridad`  
Baseline origen: `C:\temp\centrodecobros_phase30_release_candidate`

## Dictamen

`GO tecnico parcial para continuar estabilizacion; NO-GO para liberacion directa sin corregir DB local, npm audit y validacion externa Pagadetodo`

La fase cerro los bloqueadores criticos solicitados sin reabrir migraciones, sin cambiar nombres de rutas publicas, sin tocar `principal.blade.php` y conservando el contrato publico de assets.

## Cambios implementados

1. `Administrador` dejo de ser permisivo.
   - `idrol=1`: acceso total dentro del grupo protegido.
   - `idrol=2`: acceso acotado a clientes, archivos, ligas visibles, domiciliacion visible, respuestas de lectura, reportes y carga/importacion operativa.
   - otros roles: bloqueo `403`.
2. `CancelarDomiciliacion` dejo de responder con `$e` indefinido.
   - valida credenciales por payload antes de DB;
   - permite localizar token por `ClientReference` o recibir `Token`;
   - usa `CancelacionDom` y `urlDomCancel`;
   - evita `Auth::user()` en ruta API sin sesion.
3. APIs sensibles ahora validan campos criticos antes de operar.
   - `GenerarSpei`: credenciales, monto, fecha, referencia y descripcion.
   - `GenerarLigaLector`: credenciales, monto, referencia y descripcion.
   - `GenerarLigaDomiciliacion`: fecha antes de parseo y usuario API real en lugar de `Auth::user()`.
   - `CargoDomiciliacion`: credenciales, referencia cliente y monto antes de consultas.
4. SPEI corrige filtros `ClientReference`.
   - `ConsultaSpeiController` y `PagoSpeiController` usan `where('transacciones.ClientReference', 'like', ...)`.
5. `UserController` reduce exposicion y rehash accidental.
   - el listado ya no selecciona `users.password`;
   - alta y actualizacion tienen validacion backend;
   - update solo cambia password si el campo viene presente;
   - usuario usa regla unique con ignore del registro actual.

## Validacion ejecutada

| Validacion | Resultado |
| --- | --- |
| Copia aislada desde Fase 30 | OK |
| `php artisan route:list` | OK, 97 rutas |
| `php artisan schedule:list` | OK, 2 tareas: `ejecutarCron` diario 07:00 y `revisarStatus` cada 5 min |
| `composer validate --no-check-publish --no-interaction` | OK, con deprecations del Composer del host |
| `composer audit --no-interaction` | Bloqueado: Composer 2.2.6 no tiene comando `audit` |
| `npm run build` | No aplica: no existe script `build` |
| `npm run production` | OK, genera `plantilla.css`, `plantilla.js`, `guest-public.js`, Vite manifest y bridge `public/js/app.js` |
| `npm audit --audit-level=low` | Falla por 29 vulnerabilidades: 5 low, 16 moderate, 8 high |
| `vendor/bin/phpunit --testsuite Unit` | OK, 9 tests, 15 assertions |
| `vendor/bin/phpunit tests/Feature/Smoke/ApiValidationRegressionTest.php` | OK, 5 tests, 10 assertions |
| `vendor/bin/phpunit` completo | Bloqueado por DB: `Access denied for user 'centro_user'@'localhost'` en 13 tests Feature |

## Pruebas agregadas

| Archivo | Cobertura |
| --- | --- |
| `tests/Unit/AdministradorMiddlewareTest.php` | Matriz minima de acceso admin, cliente y roles no autorizados |
| `tests/Feature/Smoke/ApiValidationRegressionTest.php` | Validacion temprana de SPEI, lector, domiciliacion, cargo recurrente y cancelacion |
| `tests/Unit/SpeiFilterSourceTest.php` | Regresion del filtro `ClientReference` mal formado |
| `tests/Unit/UserControllerSecuritySourceTest.php` | No seleccion de hash y update condicional de password |

## Porcentaje actualizado por modulo

| Modulo / funcionalidad | Avance despues de Fase 31 | Estado y pendientes |
| --- | ---: | --- |
| Entorno, rutas, scheduler y build | 86% | Rutas/scheduler/build OK. Pendiente DB local para Feature completos, `composer audit` con Composer moderno y cierre de npm audit. |
| Accesos y autorizacion web | 72% | Middleware real por rol. Pendiente politicas por recurso/ownership y pruebas browser con usuarios reales. |
| Clientes, personas y archivos | 73% | Cliente rol 2 puede operar su flujo visible. Pendiente validacion de ownership por registro y endurecer criterios dinamicos. |
| Usuarios y roles | 74% | Password hash deja de exponerse y update no rehashea vacio. Pendiente pruebas DB y politica fina de administracion. |
| Ligas de pago | 72% | UI/ruta conservadas. Pendiente validar extremo a extremo con Pagadetodo o mock controlado. |
| Domiciliacion y cancelacion | 69% | Cancelacion API ya no esta rota; storeDom API valida fecha y usuario API. Pendiente contrato externo real y manejo idempotente. |
| Cargos recurrentes | 68% | Validacion temprana agregada y scheduler visible. Pendiente ejecucion controlada de cargo real/sandbox y callbacks. |
| SPEI | 69% | Generacion valida payload y filtros `ClientReference` corregidos. Pendiente sandbox para consulta/pago/cancelacion y hardening de webhooks. |
| Lector / terminal | 64% | API valida campos criticos. Pendiente validar integracion externa y decidir exposicion/cancelacion lector. |
| Respuestas y webhooks | 63% | Lectura/export protegidos para rol cliente. Pendiente idempotencia, firma/origen y pruebas de callbacks. |
| Reportes y exportaciones | 76% | Rutas siguen presentes y build OK. Pendiente Feature completos con DB y filtros dinamicos endurecidos. |
| Seguridad de dependencias y secretos | 58% | Se corrigieron regresiones criticas puntuales. Pendiente externalizacion de secretos y remediacion de npm audit. |

## Porcentaje actualizado por rol

| Rol / acceso | Avance despues de Fase 31 | Estado y pendientes |
| --- | ---: | --- |
| Administrador (`idrol=1`) | 78% | Puede operar todo el grupo protegido. Pendiente pruebas autenticadas con DB y politicas formales por modulo. |
| Cliente (`idrol=2`) | 71% | Ya no puede llamar rutas admin directas; conserva clientes, archivos, ligas, domiciliacion, respuestas y reportes visibles. Pendiente ownership por recurso y validacion browser. |
| Otros roles | 45% | Quedan bloqueados por defecto. Pendiente definir si existen roles operativos adicionales y su matriz. |
| Guest publico | 75% | Login, raiz y `/url` no se tocaron. Pendiente smoke browser final por no depender de DB. |
| Cliente API externo | 61% | Validaciones criticas y cancelacion mejoradas. Pendiente autenticacion robusta, rate limit, idempotencia, firma y sandbox Pagadetodo. |

## Riesgos residuales

1. DB local sigue bloqueando Feature tests autenticados (`centro_user` sin acceso).
2. `npm audit` subio a 29 vulnerabilidades; no se corrigio por restriccion de no mezclar remediacion de dependencias.
3. Credenciales y endpoints Pagadetodo/Pusher siguen hardcoded por restriccion de fase.
4. No hay evidencia de contrato real de `CancelarDomiciliacionIndi` contra sandbox externo.
5. El middleware ya protege por rol, pero no reemplaza ownership por registro dentro de cada controlador.
6. Criterios dinamicos de busqueda siguen requiriendo whitelists por controlador.
7. Webhooks financieros siguen sin firma, idempotencia fuerte o origen verificado.

## Siguientes pasos optimos

1. Corregir primero el entorno DB local o preparar un dataset de pruebas aislado para recuperar Feature tests completos.
2. Agregar ownership/whitelists por controlador para cliente, transacciones, respuestas, SPEI y domiciliacion.
3. Crear mocks o sandbox Pagadetodo para validar `Generar*`, `CargoDomiciliacion`, `CancelarDomiciliacion` y webhooks sin tocar produccion.
4. Abrir fase separada de dependencias frontend para reducir `npm audit` sin romper contrato publico de assets.
5. Abrir fase separada de secretos para externalizacion controlada de credenciales y Pusher.

## Prompt recomendado siguiente

```text
Quiero abrir la Fase 32 de pruebas funcionales completas, ownership por recurso y contratos API controlados del proyecto Centro de Cobros, partiendo de la baseline:
C:\temp\centrodecobros_phase31_estabilizacion_funcional_accesos_seguridad

Objetivo:
- crear una copia aislada nueva para Fase 32;
- resolver o aislar el bloqueo de DB local para poder correr Feature tests autenticados;
- agregar ownership/whitelists por controlador para que el rol cliente solo vea/opere sus propios recursos;
- crear pruebas Feature para rutas admin vs cliente, clientes, archivos, transacciones, respuestas, SPEI y domiciliacion;
- preparar mocks o sandbox controlado para contratos Pagadetodo sin tocar produccion;
- validar browser de administrador, cliente y guest;
- actualizar documentacion rectora con porcentajes y riesgos al cierre.

Restricciones:
1. No reabrir migracion estructural de base de datos.
2. No cambiar nombres de rutas publicas ni contratos externos sin evidencia y prueba.
3. No tocar principal.blade.php salvo necesidad tecnica minima y justificada.
4. Mantener el contrato publico de assets.
5. No mezclar remediacion completa de npm audit ni externalizacion completa de secretos en esta fase.
6. Si la DB local sigue bloqueada, documentar el bloqueo y mantener pruebas aisladas que cubran las regresiones corregidas.

Entrega esperada:
- cambios implementados;
- pruebas ejecutadas con resultado;
- evidencia de matriz de accesos por rol;
- lista de riesgos residuales;
- porcentaje actualizado por modulo y rol despues de Fase 32.
```
