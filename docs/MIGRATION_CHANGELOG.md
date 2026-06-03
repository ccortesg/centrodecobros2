# Changelog de Migracion

Ultima actualizacion: 2026-05-27

## 2026-05-27 - Fase 33

- Creada la copia aislada `C:\temp\centrodecobros_phase33_entorno_sandbox_e2e` desde `C:\temp\centrodecobros_phase32_pruebas_ownership_contratos_api`.
- Identificado runner funcional de pruebas: `C:\wamp64\bin\php\php8.3.0\php.exe` con drivers PDO `mysql,sqlite`.
- Agregado script `scripts/local/prepare_phase33_browser_sqlite.php` para preparar `storage/phase33_browser.sqlite`.
- Ajustado `tests/Support/UsesIsolatedCentroCobrosDatabase.php` para aceptar SQLite `:memory:` o archivo persistente.
- Agregada tabla `notifications` al schema minimo de pruebas para soportar shell autenticado.
- Corregidos `orderBy('id')` ambiguos en controladores con joins.
- Ajustado `DashboardController` para soportar MySQL y SQLite en extraccion de mes/anio.
- Ajustado `CommandAndDatabaseSmokeTest` para validar schema en MySQL y SQLite.
- Corregida expectativa de folio en contrato mock de lector.
- Validaciones:
  - `vendor/bin/phpunit tests/Feature/Phase32` con PHP WAMP OK, 17 tests, 49 assertions;
  - `vendor/bin/phpunit --testsuite Unit` con PHP WAMP OK, 13 tests, 72 assertions;
  - `vendor/bin/phpunit tests/Feature/Smoke/AuthenticatedReadOnlySmokeTest.php` con SQLite OK, 7 tests, 74 assertions;
  - `vendor/bin/phpunit --testsuite Feature` con SQLite OK, 44 tests, 199 assertions;
  - `npm run production` OK;
  - `npm audit --omit=dev` OK, 0 vulnerabilidades;
  - `npm audit --audit-level=low` falla por 29 vulnerabilidades heredadas;
  - `php artisan route:list` OK, 97 rutas;
  - `php artisan schedule:list` OK, 2 tareas.
- Browser:
  - guest `/main` redirige a `/login`;
  - admin `admin/secret` entra a `/main`, shell admin OK, `notification/get` 200, `dashboard` 200, 0 errores consola;
  - cliente `client-a/secret` entra a `/main`, shell cliente OK, `notification/get` 200, `dashboard` 200, 0 errores consola.
- Sandbox Pagadetodo:
  - se mantuvo simulacion controlada con `PAGADETODO_MOCK=true`;
  - no se hicieron llamadas a produccion;
  - sandbox oficial queda pendiente por falta de URL/credenciales no productivas.
- Emitido dictamen: `GO tecnico parcial fuerte para continuar validacion controlada; NO-GO para liberacion directa hasta validar sandbox Pagadetodo oficial, cerrar webhooks/idempotencia y tratar npm audit/secretos en carriles separados`.

## 2026-05-27 - Fase 32

- Creada la copia aislada `C:\temp\centrodecobros_phase32_pruebas_ownership_contratos_api` desde `C:\temp\centrodecobros_phase31_estabilizacion_funcional_accesos_seguridad`.
- Revalidado entorno, rutas, scheduler, build y auditorias disponibles.
- Agregados helpers compartidos para:
  - deteccion de administrador;
  - scope propietario;
  - autorizacion por registro;
  - respuesta `403`;
  - whitelists de criterios;
  - paginacion acotada;
  - mock controlado de Pagadetodo.
- Implementado ownership para rol cliente en clientes, archivos, transacciones, respuestas, consultas SPEI, pagos SPEI, cancelaciones SPEI, cargos recurrentes y exportaciones criticas.
- Agregadas whitelists de busqueda dinamica en superficies criticas para evitar columnas arbitrarias.
- Agregado mock Pagadetodo controlado para `GenerarLigaPago`, `GenerarLigaDomiciliacion`, `GenerarSpei`, `GenerarLigaLector`, `CargoDomiciliacion` y `CancelarDomiciliacion`.
- Declarado `PAGADETODO_MOCK=false` en `.env.example` y `services.pagadetodo.mock` en `config/services.php`.
- Agregadas pruebas:
  - `tests/Support/UsesIsolatedCentroCobrosDatabase.php`
  - `tests/Feature/Phase32/AccessOwnershipFeatureTest.php`
  - `tests/Feature/Phase32/PagadetodoContractMockFeatureTest.php`
  - `tests/Unit/Phase32OwnershipAndContractSourceTest.php`
- Validaciones:
  - `vendor/bin/phpunit --testsuite Unit` OK, 13 tests, 72 assertions;
  - `vendor/bin/phpunit tests/Unit/Phase32OwnershipAndContractSourceTest.php` OK, 4 tests, 57 assertions;
  - `vendor/bin/phpunit tests/Feature/Phase32` OK con 17 skipped por falta de `pdo_sqlite`;
  - `npm run production` OK;
  - `npm audit --omit=dev` OK, 0 vulnerabilidades;
  - `npm audit --audit-level=low` falla por 29 vulnerabilidades heredadas;
  - `php artisan route:list` OK, 97 rutas;
  - `php artisan schedule:list` OK, 2 tareas.
- Browser:
  - `/login` renderiza correctamente con Playwright CLI;
  - `/` y `/main` redirigen a `/login`;
  - admin/cliente quedan bloqueados por DB local.
- Bloqueos documentados:
  - suite Feature completa falla por MySQL `Access denied for user 'centro_user'@'localhost'`;
  - Feature aisladas de Fase 32 se saltan porque PHP CLI no tiene `pdo_sqlite`;
  - `composer audit` no existe en Composer 2.2.6 del host.
- Emitido dictamen: `GO tecnico parcial para continuar estabilizacion; NO-GO para liberacion directa sin recuperar DB local/Feature completos, validar sandbox externo Pagadetodo y cerrar deuda npm/secretos en carriles separados`.

## 2026-05-27 - Fase 31

- Creada la copia aislada `C:\temp\centrodecobros_phase31_estabilizacion_funcional_accesos_seguridad` desde `C:\temp\centrodecobros_phase30_release_candidate`.
- Revalidado entorno, rutas, scheduler, build y auditorias disponibles.
- Implementada autorizacion real en middleware `Administrador`:
  - admin `idrol=1` con acceso total al grupo protegido;
  - cliente `idrol=2` limitado a su superficie operativa visible;
  - otros roles con `403`.
- Corregido `TransaccionController@cancelarDomAPI`, que antes retornaba con `$e` indefinido y dejaba el flujo inalcanzable.
- Agregadas validaciones tempranas en APIs SPEI, lector, domiciliacion y cargo recurrente.
- Corregidos filtros `ClientReference` en `ConsultaSpeiController` y `PagoSpeiController`.
- Endurecido `UserController`:
  - ya no selecciona `users.password`;
  - valida altas/updates;
  - no rehashea password si el campo no viene presente.
- Agregadas pruebas:
  - `tests/Unit/AdministradorMiddlewareTest.php`
  - `tests/Feature/Smoke/ApiValidationRegressionTest.php`
  - `tests/Unit/SpeiFilterSourceTest.php`
  - `tests/Unit/UserControllerSecuritySourceTest.php`
- Validaciones:
  - `vendor/bin/phpunit --testsuite Unit` OK, 9 tests, 15 assertions;
  - `vendor/bin/phpunit tests/Feature/Smoke/ApiValidationRegressionTest.php` OK, 5 tests, 10 assertions;
  - `npm run production` OK;
  - `php artisan route:list` OK, 97 rutas;
  - `php artisan schedule:list` OK, 2 tareas.
- Bloqueos documentados:
  - suite completa `vendor/bin/phpunit` falla por DB local `Access denied for user 'centro_user'@'localhost'`;
  - `composer audit` no existe en Composer 2.2.6 del host;
  - `npm audit` reporta 29 vulnerabilidades.
- Emitido dictamen: `GO tecnico parcial para continuar estabilizacion; NO-GO para liberacion directa sin corregir DB local, npm audit y validacion externa Pagadetodo`.

## 2026-03-25 - Fase 30

- Creada la copia aislada `C:\temp\centrodecobros_phase30_release_candidate` desde `C:\temp\centrodecobros_phase29_release_readiness`.
- Confirmado que la copia se creo correctamente antes de modificar archivos.
- Releidos y respetados los documentos rectores de release readiness, checklist, runbook, master plan, decisiones, riesgos, environment matrix y next prompts.
- Revalidados en la copia nueva:
  - `php artisan --version`
  - `composer show laravel/framework`
  - `composer show laravel/framework --locked`
  - `composer validate --no-check-publish`
  - `composer audit`
  - `node -v`
  - `npm -v`
  - `npm audit`
- Corregido el advisory abierto de `league/commonmark` actualizando `2.8.1 -> 2.8.2` dentro del rango permitido por `laravel/framework 12.54.1`.
- Reconfirmado que `composer audit` queda limpio despues del update.
- Revalidada la reproducibilidad de build con:
  - `npm ci`
  - `npm run development`
  - `npm run production`
- Reconfirmada la generacion de artefactos esperados:
  - `public/js/app.js`
  - `public/js/plantilla.js`
  - `public/css/plantilla.css`
  - `public/js/guest-public.js`
- Revalidadas rutas, scheduler y suite backend minima con:
  - `php artisan route:list`
  - `php artisan schedule:list`
  - `php vendor/bin/phpunit`
- Ejecutada validacion browser integral sobre:
  - `/login`
  - `/url`
  - `/main`
  - sidebar
  - cabecera/topbar
  - `Roles`
  - `Clientes`
  - `Usuarios`
  - `Reporte Ingresos SPEI`
  - `Reporte Ingresos por Cargos Recurrentes`
- Confirmado `0` errores y `0` warnings de consola en los modulos auditados.
- Confirmado que `/login` y `/url` siguen usando `public/js/guest-public.js` y no dependen de `public/js/plantilla.js`.
- Confirmado por hash que `resources/views/principal.blade.php` sigue intacto respecto de Fase 29.
- Documentadas como deuda residual aceptada y condicion operativa de release:
  - integraciones hardcoded
  - bootstrap realtime hardcoded
  - dependencias del entorno local y de despliegue
  - hallazgos residuales de `npm audit`
- Emitido dictamen unico final: `GO con condiciones adicionales`.

## 2026-03-25 - Fase 29

- Fase 29 cerro la migracion dentro del alcance actual y dejo el proyecto en `GO con condiciones previas`.
- La Fase 30 toma esa baseline consolidada y la convierte en release candidate final con validacion y runbook operativo.
