# Precheck Formal - Laravel 8.83.23 -> 9

Ultima actualizacion: 2026-03-12

## Estado actual del precheck

Resultado: `EJECUTADO CON SALVEDADES`

El precheck quedo cerrado al ejecutar Fase 2 en una copia aislada:

- workspace: `C:\\temp\\centrodecobros_phase2_l9`
- PHP: `8.0.30`
- Composer: `2.7.3`
- BD clonada: `centrodecobros_phase2_l9`
- resultado final: Laravel `9.52.21` estable localmente

## Prerrequisitos y cierre

| Prerrequisito | Estado final | Nota |
| --- | --- | --- |
| PHP `8.0.x` en copia aislada | Cumplido | `php8.0.30` en workspace separado |
| Composer `2.7.x` | Cumplido | `2.7.3` |
| `route:list` funcional | Cumplido | OK en Laravel 9 |
| `schedule:list` funcional | Cumplido | OK con 2 tareas |
| Smoke suite local segura | Cumplido | `OK (14 tests, 79 assertions)` |
| Dataset funcional controlado | Cumplido con salvedad | Se clono la BD local; sigue sin versionarse |
| Integraciones externas clasificadas | Cumplido | Se mantuvieron fuera de la automatizacion |

## Paquetes de riesgo y resolucion aplicada

| Paquete / tema | Estado en Laravel 8 | Resolucion aplicada en Fase 2 |
| --- | --- | --- |
| `laravel/framework` | `8.83.23` | Actualizado a `9.52.21` |
| `pusher/pusher-php-server` | `3.3.1` | Actualizado a `5.0.3` |
| `barryvdh/laravel-dompdf` | `0.8.7` | Actualizado a `2.2.0` |
| `wildbit/swiftmailer-postmark` | `3.3.0` | Retirado; sustituido por `symfony/postmark-mailer` + `symfony/http-client` |
| `fideloper/proxy` | `4.4.2` | Retirado; `TrustProxies` migrado al middleware nativo |
| `fzaninotto/faker` | `1.9.2` | Sustituido por `fakerphp/faker` |
| `laravel/ui` | `3.4.6` | Mantenido sin cambios funcionales |
| `maatwebsite/excel` | `3.1.40` | Actualizado transitivamente a `3.1.67` y revalidado |
| `telesign/telesign` | `3.0.0` | Actualizado transitivamente a `3.0.7`; sin pruebas contra real |

## Validaciones obligatorias ejecutadas

1. `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1`
2. `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1`
3. `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php`
4. `php artisan route:list`
5. `php artisan schedule:list`

## Salvedades residuales

1. `/url` sigue referenciando metodos inexistentes.
2. El frontend mantiene tres endpoints legacy sin route exacto (`/transaccionDom/exportarTransacciones`, `/pagospei/exportarReporteSpei`, `/role`).
3. El dataset funcional sigue sin vivir en el repo.
4. `composer audit` aun reporta advisories residuales en `laravel/framework` 9 y componentes Symfony `6.0.x`.

## Cierre del precheck

El precheck ya no es `NO-GO`: quedo `ejecutado con salvedades` y su salida formal vive en `docs/MIGRATION_PHASE_2_LARAVEL9.md`.
