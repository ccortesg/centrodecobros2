# Runbook de deploy y rollback

Ultima actualizacion: 2026-07-03
Repositorio: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`
Rama vigente: `main`

## Estado actual

La plataforma ya funciona en el servidor productivo por Docker, segun confirmacion del propietario el 2026-06-03.

Este repositorio no contiene `Dockerfile` ni `docker-compose.yml`. Por lo tanto, los comandos exactos de despliegue productivo dependen del compose real del servidor y no deben inventarse desde el repo local.

## Principios

1. Trabajar sobre la carpeta/repositorio actual; no crear nuevas carpetas de fase.
2. No publicar secretos ni `.env`.
3. No ejecutar migraciones sobre DB productiva.
4. No cambiar PHP 7.4.3 global del host si Docker ya aisla el runtime nuevo.
5. No activar ni duplicar scheduler sin solicitud explicita.
6. No usar credenciales productivas de Pagadetodo en pruebas.
7. Generar assets compilados en CI/deploy; no versionarlos.

## Preparacion antes de deploy

En local:

```bash
git status --short
git branch --show-current
php artisan --version
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit --testsuite Unit
git diff --check
```

Si el cambio toca frontend:

```powershell
cmd /c "npm ci"
cmd /c "npm run production"
cmd /c "npm audit --omit=dev --audit-level=low"
```

Si el cambio toca webhooks, Pagadetodo, ownership o rutas criticas:

```powershell
cd /D C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia
set APP_ENV=testing&& set DB_CONNECTION=sqlite&& set DB_DATABASE=C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& set PAGADETODO_MOCK=true&& C:\wamp64\bin\php\php8.3.0\php.exe scripts\local\prepare_phase33_browser_sqlite.php C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit --testsuite Feature
```

## Inventario obligatorio en servidor

Antes de ejecutar deploy, identificar la topologia real:

```bash
pwd
git status --short
git branch --show-current
docker ps
docker compose ps
docker compose config
docker compose logs --tail=100
```

Si `docker compose` no aplica en ese servidor, registrar la herramienta real usada para levantar contenedores.

## Respaldo previo

Respaldar codigo, `.env` y DB antes de cambiar produccion:

```bash
date
tar -czf /var/backups/centro-app-$(date +%F-%H%M).tgz /ruta/del/checkout/productivo
cp /ruta/del/checkout/productivo/.env /var/backups/centro-env-$(date +%F-%H%M).env
mysqldump --single-transaction --routines --triggers centrodecobros > /var/backups/centrodecobros-$(date +%F-%H%M).sql
```

Ajustar rutas y credenciales de respaldo al servidor real.

## Deploy Docker actual

Los nombres de servicios son placeholders hasta confirmar el compose real. Sustituir `app` por el servicio PHP/Laravel correcto.

```bash
cd /ruta/del/checkout/productivo
git fetch origin
git checkout main
git pull --ff-only origin main
docker compose exec app php -v
docker compose exec app composer install --no-dev --optimize-autoloader --no-interaction
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan route:list
docker compose exec app php artisan schedule:list
```

Si el cambio incluye auditoria de integraciones, revisar migraciones aditivas antes de ejecutarlas:

```bash
docker compose exec app php artisan migrate --pretend
docker compose exec app php artisan migrate --force
docker compose exec app php artisan audit:purge --days=365 --dry-run
```

No programar `audit:purge` en scheduler sin una tarea aprobada separada.

Assets:

```bash
docker compose exec app npm ci
docker compose exec app npm run production
docker compose exec app test -f public/js/app.js
docker compose exec app test -f public/js/plantilla.js
docker compose exec app test -f public/js/guest-public.js
docker compose exec app test -f public/css/plantilla.css
docker compose exec app test -f public/build/manifest.json
```

Si Node vive en otro contenedor o en CI, ejecutar los comandos de assets en ese runtime y copiar/publicar los artefactos segun el pipeline real.

## Validacion postdeploy

```bash
docker compose ps
docker compose exec app php artisan --version
docker compose exec app php artisan route:list
docker compose logs --tail=200
```

Validar en navegador:

1. `/login`
2. `/url`
3. login admin
4. login cliente
5. `/main`
6. `/dashboard`
7. Clientes
8. Generacion de ligas
9. Domiciliacion/cargos recurrentes en modo seguro
10. Reportes/exportaciones

Validar adicionalmente:

- `.env` productivo no fue reemplazado por `.env.example`.
- `APP_URL`, cookies, cache prefix y storage son los esperados.
- No se publicaron secretos en logs.
- Scheduler activo es exactamente el esperado por produccion.
- `PAGADETODO_MOCK` y credenciales Pagadetodo corresponden al ambiente autorizado.

## Rollback de codigo

Si el deploy fue por Git y existe commit anterior conocido:

```bash
cd /ruta/del/checkout/productivo
git log --oneline -5
git checkout <commit_anterior>
docker compose exec app composer install --no-dev --optimize-autoloader --no-interaction
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app npm ci
docker compose exec app npm run production
docker compose restart app
docker compose logs --tail=200
```

Si el pipeline exige permanecer en `main`, hacer rollback por revert commit y redeploy:

```bash
git revert <commit_problematico>
git push origin main
```

## Rollback de infraestructura Docker

Solo si el cambio toco compose, imagen, volumenes o variables de entorno:

1. Restaurar compose/imagen/env anterior desde respaldo.
2. Reiniciar servicios.
3. Validar `/login`, `/main`, logs y scheduler.
4. Confirmar que DB no fue alterada.

Comandos orientativos, sujetos al compose real:

```bash
docker compose down
docker compose up -d
docker compose ps
docker compose logs --tail=200
```

## Rollback de DB

Evitar llegar a este punto: no ejecutar migraciones productivas sin solicitud explicita.

Si por excepcion hubo escritura no deseada, el rollback de DB debe decidirse manualmente con respaldo, ventana de mantenimiento y analisis de datos escritos desde el deploy.

## Ruta historica paralela

La documentacion antigua de vhost/subdominio PHP 8.3-FPM fue util cuando el objetivo era montar sandbox paralelo sin tocar produccion. Ese camino queda como referencia historica, no como modelo actual, porque el propietario confirmo que produccion ya opera por Docker.

Si se abre un sandbox paralelo futuro, debe conservar:

- subdominio/vhost separado;
- sesiones/cache/logs aislados;
- scheduler deshabilitado si comparte DB;
- `PAGADETODO_MOCK=true` hasta sandbox oficial;
- assets generados en deploy;
- sin migraciones sobre DB productiva.
