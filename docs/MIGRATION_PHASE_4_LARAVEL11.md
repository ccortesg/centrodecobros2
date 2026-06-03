# Fase 4 - Laravel 10.50.2 -> 11

Ultima actualizacion documental: 2026-03-12

## Estado final

Resultado: `Laravel 11 estable localmente con salvedades`

Fase 4 se ejecuto sin mezclar upgrade de Vue, sin migrar Mix, sin cambiar payloads de negocio, sin tocar contratos externos y sin adoptar la estructura simplificada nueva de Laravel 11.

## Entorno usado

| Item | Valor |
| --- | --- |
| Workspace aislado | `C:\temp\centrodecobros_phase4_l11` |
| PHP | `8.2.24` |
| Composer | `2.7.3` |
| Laravel final | `11.48.0` |
| DB usada | `centrodecobros_phase4_l11` |
| Base origen clonada | `centrodecobros` |
| Node / npm observados | `16.17.1` / `8.15.0` en host; `8.17.0` / `6.13.4` validados en lane legacy |
| Frontend legacy | Intacto; assets preservados |

## Reglas respetadas durante la fase

1. No se hizo upgrade de Vue ni salida de Mix.
2. No se tocaron contratos de payload hacia pasarelas, SPEI, callbacks, OTP, Postmark o Pusher.
3. No se ejecuto `php artisan migrate`.
4. El trabajo se hizo sobre copia aislada y DB clonada.
5. La estructura clasica del monolito se mantuvo intacta.
6. Las integraciones externas quedaron fuera de la automatizacion.

## Cambios aplicados

### Dependencias principales

| Tema | Antes | Despues |
| --- | --- | --- |
| `php` requerido | `^8.1` | `^8.2` |
| `laravel/framework` | `10.50.2` | `11.48.0` |
| Stack Symfony de framework | `6.4.x` | `7.4.x` |
| `nesbot/carbon` | `2.73.0` | `3.11.3` |
| `laravel/prompts` | `0.1.25` | `0.3.14` |
| `laravel/serializable-closure` | `1.3.7` | `2.0.10` |
| `nunomaduro/termwind` | `1.17.0` | `2.4.0` |

### Archivos y areas tocadas

1. `composer.json`
2. `composer.lock`
3. `.env` local de la copia aislada para apuntar a `centrodecobros_phase4_l11`
4. `bootstrap/cache/packages.php`
5. Documentacion `docs/MIGRATION_*` y `docs/README.md`

### Decisiones tecnicas aplicadas

1. Se mantuvo `laravel/ui 4.6.2`; no fue necesario retirarlo ni reestructurar auth.
2. Se mantuvieron `maatwebsite/excel 3.1.67`, `barryvdh/laravel-dompdf 2.2.0`, `pusher/pusher-php-server 7.2.7`, `symfony/http-client 6.4.34` y `symfony/postmark-mailer 6.4.24`.
3. No fue necesario tocar `app/`, `config/`, `routes/`, `tests/Feature/Smoke/` ni `scripts/local/` para lograr arranque estable.
4. Se mantuvo la estructura clasica de `bootstrap/app.php`, `RouteServiceProvider`, `Kernel` y `Handler`.

## Validacion ejecutada

| Validacion | Resultado |
| --- | --- |
| `php artisan package:discover` | OK |
| `php artisan --version` | `Laravel Framework 11.48.0` |
| `php artisan route:list` | OK; 97 rutas |
| `php artisan schedule:list` | OK; 2 tareas registradas |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1` | OK |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` | Sigue reportando solo gaps legacy conocidos |
| `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php` | `OK (14 tests, 81 assertions)` |
| `composer audit` | Sin advisories |
| `composer dump-autoload -o --no-scripts` | OK con warning PSR-4 de `telesign/telesign` |
| Validacion manual controlada en `http://localhost:8000` | OK en login, `/main`, `/dashboard` y modulos criticos |
| `nvm use 8.17.0` + `npm ci` + `npm run dev` | OK; build legacy reproducible con hashes identicos en assets |

## Hallazgos y riesgos residuales

1. Los gaps legacy previos no cambiaron en Fase 4:
   - `/url -> showURL / openPublic`
   - `/transaccionDom/exportarTransacciones`
   - `/pagospei/exportarReporteSpei`
   - `/role`
2. La smoke suite sigue sin validar el login real via POST; el riesgo de side effects por rehash de password en Laravel 11 baja a brecha de automatizacion porque la validacion manual controlada ya se completo en local.
3. `telesign/telesign` sigue emitiendo warning PSR-4 al regenerar autoload; no rompio runtime ni la suite.
4. El dataset funcional sigue siendo local y no versionado en el repo.
5. El frontend legacy ya se recompilo en lane Node `8.17.0`, pero sigue siendo obligatorio no declararlo valido si se ejecuta desde Node `16.17.1`.

## Revision de logs y arranque

1. `php artisan --version`, `route:list`, `schedule:list` y `package:discover` no generaron fallas fatales.
2. `storage/logs/laravel.log` no mostro nuevas escrituras asociadas al upgrade; el archivo disponible corresponde a errores historicos del lane anterior.

## Rollback documental claro

La estrategia de rollback de Fase 4 es por descarte de la copia aislada:

1. dejar de usar `C:\temp\centrodecobros_phase4_l11`;
2. volver a operar desde `C:\temp\centrodecobros_phase3_l10`;
3. volver a apuntar al lane previo aprobado y su DB;
4. si se quiere limpiar el experimento:
   - borrar la carpeta `C:\temp\centrodecobros_phase4_l11`
   - eliminar la base `centrodecobros_phase4_l11`

## Conclusion operativa

Fase 4 cumple su contrato tecnico: Laravel 11 queda estable localmente sobre PHP `8.2.24`, con frontend legacy intacto, smoke suite y scripts base operando, validacion manual controlada cerrada, precheck formal del frente legacy ejecutado y rollback claro. El siguiente frente recomendado ya no es otro salto backend, sino FE-2 como discovery controlado sobre Mix 2 / webpack 3 preservando por ahora runtime Vue y nombres de assets.
