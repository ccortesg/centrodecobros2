# Fase 4 - Precheck Formal Laravel 10 -> 11

Ultima actualizacion documental: 2026-03-12

## Estado del precheck

Resultado: `GO`

El upgrade backend `Laravel 10.50.2 -> 11.x` es viable en una copia aislada nueva, con cambios controlados y sin abrir una modernizacion de frontend. El dictamen `GO` aplica solo para esta copia:

- workspace: `C:\temp\centrodecobros_phase4_l11`
- PHP objetivo: `8.2.24`
- base local aislada: `centrodecobros_phase4_l11`
- frontend: congelado en stack legacy actual

## Contexto de entrada validado

La evaluacion se hizo tomando como verdad principal:

1. `docs/` completo.
2. `composer.json` y `composer.lock`.
3. `package.json` y `package-lock.json`.
4. `config/`, `routes/`, `app/`, `tests/`, `scripts/local/`.
5. `database/centrodecobros.sql`.
6. La configuracion real de la copia aislada y su dataset local.

Baseline confirmado al abrir Fase 4:

| Tema | Estado observado |
| --- | --- |
| Framework actual | `Laravel 10.50.2` |
| PHP por defecto del host | `7.4.33` |
| PHP viable para la fase | `8.2.24` |
| Composer | `2.7.3` |
| Frontend | Vue `2.5.x`, Mix `2`, Bootstrap Sass `3`, jQuery, axios legacy |
| Base operativa del lane previo | `centrodecobros` en `.env` real |
| Base aislada creada para Fase 4 | `centrodecobros_phase4_l11` |

## Validacion previa obligatoria ejecutada

Sobre la copia de Fase 4 y PHP `8.2.24`:

| Validacion | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 10.50.2` |
| `php artisan route:list` | OK; 97 rutas |
| `php artisan schedule:list` | OK; 2 tareas |
| `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php` | `OK (14 tests, 81 assertions)` |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1` | OK sobre DB aislada |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` | Solo gaps legacy conocidos |
| `composer audit` | Sin advisories |
| `composer dump-autoload -o --no-scripts` | OK con warning PSR-4 de `telesign/telesign` |

Hallazgo importante: la aplicacion Laravel 10 ya arranca y pasa la smoke suite bajo PHP `8.2.24` antes del upgrade. Eso reduce el riesgo de bloqueadores por plataforma y deja el foco en framework/dependencias.

## Compatibilidad de paquetes revisados

Revision basada en `composer.lock`, `composer show --locked`, y simulacion de Composer bajo PHP `8.2.24`.

| Paquete / tema | Version actual | Observacion para Laravel 11 | Dictamen |
| --- | --- | --- | --- |
| `laravel/framework` | `10.50.2` | Dry-run resolvio `11.48.0` con stack Symfony `7.4` | Compatible con upgrade |
| `laravel/ui` | `4.6.2` | Declara soporte `^11.0`; no obliga a cambiar estructura actual | Compatible; mantener |
| `maatwebsite/excel` | `3.1.67` | Declara soporte `^11.0`; export/import sigue siendo punto sensible funcional | Compatible; revalidar |
| `barryvdh/laravel-dompdf` | `2.2.0` | Declara soporte `^11`; no se detecto bloqueo de Composer | Compatible; revalidar |
| `pusher/pusher-php-server` | `7.2.7` | Sin acoplamiento Laravel duro; soporta PHP `^8.0` | Compatible; no tocar contratos |
| `telesign/telesign` | `3.0.7` | Soporta PHP `^8.0`, pero mantiene warning PSR-4 del proveedor | Compatible con salvedad |
| `symfony/http-client` | `6.4.34` | No bloquea el salto; puede convivir fuera del core del framework | Compatible |
| `symfony/postmark-mailer` | `6.4.24` | Compatible con `symfony/mailer ^7.0` | Compatible |
| `laravel/helpers` | `1.8.2` | Declara soporte `^11.0`; deuda legacy, no bloqueante | Compatible con salvedad |
| `phpunit/phpunit` | `9.6.34` | La suite actual corre sin cambios en PHP `8.2` | Compatible para esta fase |

## Simulacion formal del upgrade

Con PHP `8.2.24` se ejecuto:

```powershell
php C:\composer\composer.phar require 'php:^8.2' 'laravel/framework:^11.0' --with-all-dependencies --dry-run --no-scripts
```

Resultado:

- resolucion satisfactoria;
- upgrade objetivo a `laravel/framework v11.48.0`;
- 24 updates y 4 installs transitorios esperados;
- sin advisories de seguridad nuevos;
- sin bloqueador de Composer en dependencias root criticas.

Paquetes transitorios esperados en el salto:

- Symfony `6.4.x -> 7.4.x`
- `nunomaduro/termwind 1.x -> 2.4.0`
- `nesbot/carbon 2.x -> 3.x`
- `laravel/prompts 0.1.x -> 0.3.x`
- `laravel/serializable-closure 1.x -> 2.x`

## Revision de estructura, bootstrap y codigo legacy

### Estructura Laravel

No se detecta obligacion tecnica de adoptar la estructura simplificada nueva de Laravel 11:

- `bootstrap/app.php` sigue en formato clasico.
- Providers siguen registrados via `config/app.php`.
- `app/Exceptions/Handler.php`, `app/Console/Kernel.php` y `RouteServiceProvider` siguen el patron clasico.

Esto es consistente con la guia oficial: las aplicaciones existentes no necesitan migrar a la nueva estructura de directorios.

### Providers, routing, exceptions y scheduler

Hallazgos:

1. `RouteServiceProvider` mantiene API legacy sin prefijo `/api`; debe conservarse intacto.
2. `app/Console/Kernel.php` programa dos tareas llamando metodos de controlador; es deuda legacy, pero `schedule:list` ya funciona en PHP 8.2.
3. `AppServiceProvider` usa `Paginator::useBootstrapThree()` y `Schema::defaultStringLength(191)`; no se detecta incompatibilidad previa al salto.
4. `Handler.php` no contiene personalizaciones delicadas.

### Auth y hashing

Hallazgos:

1. El login real es custom por `usuario/password`.
2. `App\User` sigue extendiendo `Authenticatable`; no se detecto provider custom.
3. La guia de Laravel 11 introduce rehash de password en login; este proyecto no publica `rehash_on_login` en `config/hashing.php`.

Impacto esperado:

- no es bloqueador para el arranque;
- debe vigilarse en validacion post-upgrade para no introducir side effects sobre el login legacy.

### PHP 8.2 y Carbon 3

Riesgo observado:

1. La app usa `Carbon\Carbon` ampliamente en controladores financieros.
2. No se detectaron usos de `diffIn*` ni patrones del upgrade guide que apunten a una ruptura inmediata por `Carbon 3`.
3. La aplicacion ya corre en PHP `8.2.24` con Laravel 10 sin warnings fatales visibles en baseline, rutas, scheduler o smoke tests.

Dictamen: riesgo `medio-bajo`, sujeto a revalidacion funcional y de logs tras el upgrade.

## Gaps legacy que NO deben tratarse en esta fase

El precheck confirma que siguen abiertos, pero no bloquean el upgrade backend:

1. `/url` sigue apuntando a `showURL` y `openPublic`, metodos ausentes en `TransaccionController`.
2. `ReporteCargosRecurrentes.vue` espera `/transaccionDom/exportarTransacciones`.
3. `ReporteSpei.vue` espera `/pagospei/exportarReporteSpei`.
4. `Role.vue` sigue cableado a `/role`.
5. `telesign/telesign` sigue mostrando warning PSR-4 al regenerar autoload.

## Riesgos especificos de Fase 4

| Riesgo | Impacto | Tratamiento en la fase |
| --- | --- | --- |
| Login legacy con hashing/rehash nuevo | Puede alterar autenticacion o hashes al iniciar sesion | Validar login y no cambiar scaffold |
| Carbon `3.x` en controladores financieros | Posible cambio sutil en fechas | Revalidar scheduler, SPEI, ligas y humo tecnico |
| Scheduler acoplado a controladores | Riesgo de falla silenciosa en tareas criticas | `schedule:list` y smoke suite obligatorios |
| Integraciones financieras y callbacks | No deben cambiar payloads ni endpoints | No refactorizar ni tocar contratos |
| Warning PSR-4 de TeleSign | Ruido en autoload y deuda de proveedor | Documentar como riesgo residual, no corregir por cuenta propia |

## Dictamen formal GO / NO-GO

### Dictamen

`GO`

### Justificacion

1. Existe lane PHP `8.2.24` funcional en el host.
2. La copia nueva y su DB ya quedaron aisladas del lane de Fase 3.
3. Laravel 10 actual ya corre sobre PHP 8.2 sin fallas en arranque, rutas, scheduler ni smoke suite.
4. Los paquetes backend criticos ya declaran compatibilidad suficiente para Laravel 11 o no presentan bloqueo de Composer.
5. La simulacion formal de Composer resolvio Laravel `11.48.0` sin bloqueadores severos.
6. No hay evidencia de que la fase obligue a tocar frontend, contratos externos o estructura general del monolito.

## Plan de ejecucion autorizado por el GO

1. Actualizar `composer.json` y `composer.lock` minimamente para `php:^8.2` y `laravel/framework:^11.0`.
2. Mantener `laravel/ui`, `maatwebsite/excel`, `barryvdh/laravel-dompdf`, `pusher`, `telesign`, frontend legacy y estructura actual.
3. Ejecutar `composer update` solo con las dependencias necesarias y sin abrir refactor de negocio.
4. Corregir unicamente incompatibilidades estrictas de arranque si aparecen.
5. Revalidar:
   - `php artisan --version`
   - `php artisan route:list`
   - `php artisan schedule:list`
   - smoke suite
   - `scripts/local/check_baseline.ps1`
   - `scripts/local/check_route_alignment.ps1`
   - `composer audit`
   - logs / errores fatales

## Rollback de la copia aislada

Rollback de Fase 4, si el upgrade falla:

1. dejar de usar `C:\temp\centrodecobros_phase4_l11`;
2. volver a operar desde `C:\temp\centrodecobros_phase3_l10`;
3. volver a apuntar al lane previo aprobado y su DB;
4. borrar la carpeta `C:\temp\centrodecobros_phase4_l11` y la base `centrodecobros_phase4_l11` si se decide descartar el experimento.

El rollback sigue siendo por descarte de la copia aislada y no por revertir sobre el lane de Fase 3.
