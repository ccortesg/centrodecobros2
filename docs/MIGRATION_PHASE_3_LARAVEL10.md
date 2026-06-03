# Fase 3 - Laravel 9.52.21 -> 10

Ultima actualizacion documental: 2026-03-12

## Estado final

Resultado: `Laravel 10 estable localmente con salvedades`

Fase 3 se ejecuto sin mezclar upgrade de Vue, sin migrar Mix, sin cambiar payloads de negocio y sin ejecutar `php artisan migrate`.

## Entorno usado

| Item | Valor |
| --- | --- |
| Workspace aislado | `C:\\temp\\centrodecobros_phase3_l10` |
| PHP | `8.1.29` |
| Composer | `2.7.3` |
| Laravel final | `10.50.2` |
| DB usada | `centrodecobros_phase3_l10` |
| Node / npm observados | `16.17.1` / `8.15.0` |
| Frontend legacy | Intacto; assets compilados preservados |

## Reglas respetadas durante la fase

1. No se hizo upgrade de Vue ni salida de Mix.
2. No se tocaron contratos de payload hacia Pagadetodo, SPEI, callbacks, OTP o exportaciones.
3. No se ejecuto `php artisan migrate`.
4. El trabajo se hizo sobre copia aislada y BD clonada.
5. Las integraciones externas quedaron fuera de la automatizacion.

## Cambios aplicados

### Dependencias principales

| Tema | Antes | Despues |
| --- | --- | --- |
| `php` requerido | `^8.0.2` | `^8.1` |
| `laravel/framework` | `9.52.21` | `10.50.2` |
| `laravel/ui` | `3.4.6` | `4.6.2` |
| `pusher/pusher-php-server` | `5.0.3` | `7.2.7` |
| `monolog/monolog` | `2.11.0` | `3.10.0` |
| `psr/log` | `1.1.4` | `3.0.2` |
| `symfony/http-client` | `6.0.20` | `6.4.34` |
| `symfony/postmark-mailer` | `6.0.19` | `6.4.24` |
| Stack Symfony de soporte | `6.0.x` | `6.4.x` |

### Ajustes de soporte y compatibilidad

1. `tests/Feature/Smoke/CommandAndDatabaseSmokeTest.php` se normalizo para validar las tareas del scheduler sin depender del texto literal de salida.
2. El login custom por `usuario` / `password` se preservo sin cambios funcionales.
3. No se tocaron controladores, rutas ni componentes del frente para resolver el upgrade.
4. Para esta fase, `php.exe` `8.1.29` debe invocarse de forma explicita o via `PATH` temporal; `composer.bat` del host sigue usando PHP `7.4.33` si no se corrige.

## Validacion ejecutada

| Validacion | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 10.50.2` |
| `php artisan route:list` | OK |
| `php artisan schedule:list` | OK; 2 tareas registradas |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1` | OK |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` | Reporta solo gaps legacy conocidos |
| `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php` | `OK (14 tests, 81 assertions)` |
| `composer audit` | Sin advisories |

## Hallazgos y riesgos residuales

1. Los gaps legacy previos no se tocaron en esta fase:
   - `/url -> showURL / openPublic`
   - `/transaccionDom/exportarTransacciones`
   - `/pagospei/exportarReporteSpei`
   - `/role`
2. El dataset funcional de prueba se resolvio con clon local de la BD, no con un snapshot versionado en el repo.
3. El frontend legacy sigue fuera del alcance: no se recompilo `node_modules` ni se toco Mix 2.
4. Persisten riesgos operativos ya conocidos:
   - middleware `Administrador` permisivo
   - credenciales y endpoints hardcoded en controladores financieros
5. `composer dump-autoload` sigue reportando un warning PSR-4 de `telesign/telesign`; no genero falla de runtime ni de smoke suite, pero OTP/SMS queda como validacion manual controlada.

## Go / No-Go posterior

Resultado para cerrar Fase 3: `GO`

Resultado para arrancar Fase 4 sin pretrabajo adicional: `NO-GO condicionado`

Bloqueadores previos a Fase 4:

1. preparar PHP `8.2.x` en nueva copia aislada;
2. ejecutar precheck de compatibilidad Laravel 10 -> 11 para `laravel/ui`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`, `symfony/postmark-mailer`, `pusher/pusher-php-server` y `telesign/telesign`;
3. definir estrategia versionable para dataset funcional local;
4. decidir si los endpoints legacy huerfanos son flujos vigentes o codigo muerto.

## Rollback documental claro

La estrategia de rollback de Fase 3 es por descarte de la copia aislada, no por revertir la copia de Fase 2:

1. Dejar de usar `C:\\temp\\centrodecobros_phase3_l10`.
2. Volver a operar desde `C:\\temp\\centrodecobros_phase2_l9`, que conserva el baseline de Fase 2 en Laravel `9.52.21`.
3. Volver a apuntar al dataset `centrodecobros_phase2_l9`.
4. Repetir la validacion de Fase 2 con PHP `8.0.30`:
   - `php artisan --version`
   - `php artisan route:list`
   - `php artisan schedule:list`
   - `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1`
   - `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1`
   - `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php`
5. Si se quiere limpiar el experimento de Fase 3:
   - borrar la carpeta `C:\\temp\\centrodecobros_phase3_l10`
   - eliminar la BD `centrodecobros_phase3_l10`

## Conclusion operativa

Fase 3 cumple su contrato tecnico: Laravel 10 queda estable localmente sobre PHP `8.1.29`, con smoke suite ejecutada, advisories cerrados, riesgos y decisiones actualizados y rollback claro. El proyecto todavia no esta listo para tocar frontend ni para intentar Laravel 11 sin nuevo precheck; la siguiente fase natural es preparar el carril PHP `8.2.x` y abrir Fase 4 en otra copia aislada.
