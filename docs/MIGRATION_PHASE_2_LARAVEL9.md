# Fase 2 - Laravel 8.83.23 -> 9

Ultima actualizacion documental: 2026-03-12

## Estado final

Resultado: `Laravel 9 estable localmente con salvedades`

Fase 2 se ejecuto sin mezclar upgrade de Vue, sin migrar Mix, sin cambiar payloads de negocio y sin ejecutar `php artisan migrate`.

## Entorno usado

| Item | Valor |
| --- | --- |
| Workspace aislado | `C:\\temp\\centrodecobros_phase2_l9` |
| PHP | `8.0.30` |
| Composer | `2.7.3` |
| Laravel final | `9.52.21` |
| DB usada | `centrodecobros_phase2_l9` |
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
| `laravel/framework` | `8.83.23` | `9.52.21` |
| PHP requerido | `>=7.3.0` | `^8.0.2` |
| `barryvdh/laravel-dompdf` | `0.8.7` | `2.2.0` |
| `pusher/pusher-php-server` | `3.3.1` | `5.0.3` |
| `wildbit/swiftmailer-postmark` | presente | retirado |
| `symfony/postmark-mailer` | ausente | `6.0.19` |
| `symfony/http-client` | ausente | `6.0.20` |
| `fideloper/proxy` | presente | retirado |
| `fzaninotto/faker` | presente | retirado |
| `fakerphp/faker` | ausente | `1.24.1` |

### Ajustes de soporte y compatibilidad

1. `app/Http/Middleware/TrustProxies.php` migrado al middleware nativo de Laravel 9.
2. `app/Http/Kernel.php` actualizado a `PreventRequestsDuringMaintenance`.
3. `composer.json` configurado con `allow-plugins.symfony/thanks=false`.
4. `tests/Feature/Smoke/CommandAndDatabaseSmokeTest.php` normalizado para el formato real de `schedule:list` en Laravel 9.
5. `phpunit.xml` migrado al schema actual de PHPUnit 9.

## Validacion ejecutada

| Validacion | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 9.52.21` |
| `php artisan route:list` | OK |
| `php artisan schedule:list` | OK; 2 tareas registradas |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1` | OK |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` | Reporta solo gaps legacy conocidos |
| `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php` | `OK (14 tests, 79 assertions)` |
| `composer audit` | 5 advisories residuales en 4 paquetes |

## Hallazgos y riesgos residuales

1. El sistema queda estable localmente, pero no queda endurecido a nivel de advisories: `laravel/framework` 9 y Symfony `6.0.x` arrastran vulnerabilidades que ya exigen Fase 3 sobre PHP `8.1`.
2. Los gaps legacy previos no se tocaron en esta fase:
   - `/url -> showURL / openPublic`
   - `/transaccionDom/exportarTransacciones`
   - `/pagospei/exportarReporteSpei`
   - `/role`
3. El dataset funcional de prueba se resolvio con clon local de la BD, no con un snapshot versionado en el repo.
4. El frontend legacy sigue fuera del alcance: no se recompilo `node_modules` ni se toco Mix 2.
5. Persisten riesgos operativos ya conocidos:
   - middleware `Administrador` permisivo
   - credenciales y endpoints hardcoded en controladores financieros

## Go / No-Go posterior

Resultado para cerrar Fase 2: `GO`

Resultado para arrancar Fase 3 sin pretrabajo adicional: `NO-GO`

Bloqueadores previos a Fase 3:

1. preparar PHP `8.1.x` en nueva copia aislada;
2. decidir tratamiento de advisories residuales de Laravel 9 / Symfony `6.0.x`;
3. definir estrategia versionable para dataset funcional local;
4. decidir si los endpoints legacy huerfanos son flujos vigentes o codigo muerto.

## Rollback documental claro

La estrategia de rollback de Fase 2 es por descarte de la copia aislada, no por revertir el baseline original:

1. Dejar de usar `C:\\temp\\centrodecobros_phase2_l9`.
2. Volver a operar desde `C:\\temp\\centrodecobros`, que conserva el baseline de Fase 1 en Laravel `8.83.23`.
3. Volver a ejecutar la validacion de Fase 1 con PHP `7.4.33`:
   - `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1`
   - `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1`
   - `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php`
4. Si se quiere limpiar el experimento de Fase 2:
   - borrar la carpeta `C:\\temp\\centrodecobros_phase2_l9`
   - eliminar la BD `centrodecobros_phase2_l9`

## Conclusion operativa

Fase 2 cumple su contrato tecnico: Laravel 9 queda estable localmente, con smoke suite ejecutada, riesgos y decisiones actualizados y rollback claro. El proyecto todavia no esta listo para considerarse endurecido ni para tocar frontend; la siguiente fase natural es Laravel `9 -> 10` sobre PHP `8.1`.
