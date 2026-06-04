# Fase 35 - Preparacion GitHub y release sandbox paralelo

Fecha: 2026-06-02  
Workspace: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`  
Baseline: Fase 34 validada localmente con webhooks e idempotencia.

## Nota vigente 2026-06-03

Este documento queda como evidencia historica de la preparacion GitHub/sandbox. Despues de este corte:

- la rama vigente es `main`;
- el repositorio actual ya es la carpeta de trabajo permanente;
- no se deben crear nuevas carpetas de fase;
- la plataforma ya funciona en produccion por Docker, confirmado por el propietario;
- el runbook vigente es `docs/MIGRATION_DEPLOY_AND_ROLLBACK_RUNBOOK.md`;
- el modelo operativo vigente es `docs/PROJECT_OPERATING_MODEL.md`.

No ejecutar el plan de sandbox paralelo/vhost de este documento salvo que el propietario lo pida explicitamente como un ambiente nuevo.

## Dictamen

`GO tecnico para preparar GitHub y sandbox paralelo; NO-GO para reemplazo productivo`.

La carpeta fue inicializada como repositorio Git local y la rama de publicacion fue renombrada a `main`. La publicacion debe hacerse a un repositorio privado GitHub despues de revisar el primer commit local. La version productiva actual no se toca.

## Rama, tag y commit inicial

Rama local definida:

```bash
main
```

Tag recomendado despues del primer commit validado:

```bash
sandbox-phase34-v1.0.2
```

Comandos recomendados para el primer commit y push:

```bash
git add .
git status --short
git commit -m "Prepare phase 34 sandbox release"
git remote add origin <repo-github-privado>
git push -u origin main
git push origin sandbox-phase34-v1.0.2
```

Antes de ejecutar `git add .`, revisar que `git status --short` no muestre `.env`, `vendor/`, `node_modules/`, logs, SQLite, dumps SQL, `output/`, `test-results/`, assets compilados ni archivos accidentales de raiz.

## `.gitignore` preparado

Se amplio `.gitignore` para excluir:

- `.env` y variantes locales, preservando `.env.example`;
- `vendor/` y `node_modules/`;
- caches de Laravel y Composer generadas localmente;
- logs, sesiones, vistas compiladas y SQLite local;
- `output/`, `test-results/`, `.phpunit.cache`, `.phpunit.result.cache`;
- dumps SQL locales (`database/*.sql`, `database/*.dump`);
- archivos accidentales de raiz generados por snippets de navegador;
- assets compilados:
  - `public/build/`
  - `public/js/app.js`
  - `public/js/plantilla.js`
  - `public/js/guest-public.js`
  - `public/css/plantilla.css`
  - `public/mix-manifest.json`

## Decision sobre assets

Los assets compilados no se versionan en GitHub.

Razon:

- evita subir artefactos generados y hashes de build;
- fuerza reproducibilidad con `package-lock.json`;
- mantiene el contrato publico, pero generado por proceso controlado.

Comando obligatorio en CI/deploy:

```bash
npm ci
npm run production
```

Archivos que deben existir despues del build:

```bash
test -f public/js/app.js
test -f public/js/plantilla.js
test -f public/css/plantilla.css
test -f public/js/guest-public.js
test -f public/build/manifest.json
```

## Workflow GitHub

Se agrego:

```text
.github/workflows/sandbox-release-validation.yml
```

El workflow:

- usa PHP 8.3;
- instala extensiones necesarias, incluyendo SQLite;
- usa Node 20;
- instala dependencias PHP y Node;
- genera assets productivos;
- ejecuta `npm audit --omit=dev --audit-level=low`;
- ejecuta `php artisan route:list`;
- ejecuta `php artisan schedule:list`;
- ejecuta Unit tests;
- prepara `storage/github-actions.sqlite` con `scripts/local/prepare_phase33_browser_sqlite.php`;
- ejecuta Feature tests con SQLite persistente y `PAGADETODO_MOCK=true`.

No usa credenciales productivas, no despliega, no ejecuta migraciones y no activa scheduler.

## Configuracion sandbox requerida

Valores minimos del `.env` del servidor sandbox:

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://centro-v12.example.com
SESSION_COOKIE=centro_v12_session
CACHE_PREFIX=centro_v12_
PAGADETODO_MOCK=true
PAGADETODO_USER=
PAGADETODO_PASSWORD=
PAGADETODO_DOM_USER=
PAGADETODO_DOM_PASSWORD=
PAGADETODO_DOM_BA_USER=
PAGADETODO_DOM_BA_PASSWORD=
PAGADETODO_SANDBOX_USER=
PAGADETODO_SANDBOX_PASSWORD=
BROADCAST_DRIVER=log
VITE_PUSHER_APP_KEY=
VITE_PUSHER_APP_CLUSTER=mt1
QUEUE_CONNECTION=sync
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=centrodecobros
DB_USERNAME=...
DB_PASSWORD=...
```

Restricciones:

- no copiar `.env` local al repositorio;
- no usar credenciales productivas de Pagadetodo para pruebas;
- no ejecutar migraciones estructurales;
- no instalar cron/scheduler para este directorio mientras comparta DB;
- no publicar bajo subcarpeta; usar vhost/subdominio separado.

## Deploy sandbox paralelo

Ruta sugerida:

```bash
/var/www/centro-v12-sandbox
```

Comandos:

```bash
sudo mkdir -p /var/www/centro-v12-sandbox
sudo chown ubuntu:www-data /var/www/centro-v12-sandbox
git clone --branch main <repo-github-privado> /var/www/centro-v12-sandbox
cd /var/www/centro-v12-sandbox

php8.3 /usr/local/bin/composer install --no-dev --optimize-autoloader
npm ci
npm run production

cp .env.example .env
php8.3 artisan key:generate --force
```

Editar `.env` con valores sandbox. Despues:

```bash
php8.3 artisan config:clear
php8.3 artisan cache:clear
php8.3 artisan route:clear
php8.3 artisan view:clear
php8.3 artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

Validacion servidor:

```bash
php8.3 artisan --version
php8.3 artisan route:list
php8.3 artisan schedule:list
php8.3 vendor/bin/phpunit --testsuite Unit
npm audit --omit=dev --audit-level=low
```

## Vhost recomendado

```apache
<VirtualHost *:80>
    ServerName centro-v12.example.com
    DocumentRoot /var/www/centro-v12-sandbox/public

    <Directory /var/www/centro-v12-sandbox/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost/"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/centro-v12-error.log
    CustomLog ${APACHE_LOG_DIR}/centro-v12-access.log combined
</VirtualHost>
```

Activacion:

```bash
sudo a2ensite centro-v12.conf
sudo apachectl configtest
sudo systemctl reload apache2
```

No instalar cron del sandbox.

## Rollback

Rollback del sandbox paralelo:

```bash
sudo a2dissite centro-v12.conf
sudo systemctl reload apache2
```

Si el problema es codigo:

```bash
sudo mv /var/www/centro-v12-sandbox /var/www/centro-v12-sandbox.disabled.$(date +%F-%H%M)
```

La version productiva actual en `/var/www/centro` no debe tocarse.

## Validaciones locales ejecutadas antes de esta preparacion

| Validacion | Resultado |
| --- | --- |
| `php artisan route:list` | OK, 97 rutas. |
| `php artisan schedule:list` | OK, 2 tareas registradas; no activar en sandbox paralelo. |
| `composer validate --no-check-publish --no-interaction` | OK. |
| `php vendor/bin/phpunit --testsuite Unit` | OK, 13 tests, 72 assertions. |
| Feature completo WAMP/SQLite | OK, 54 tests, 234 assertions. |
| `npm run production` | OK. |
| `npm audit --omit=dev --audit-level=low` | 0 vulnerabilidades. |
| `npm audit --audit-level=low` | 29 vulnerabilidades dev/tooling. |

## Riesgos residuales

1. Sandbox oficial Pagadetodo sigue bloqueado por falta de URL/credenciales no productivas.
2. Webhooks no verifican firma/origen porque falta especificacion del proveedor.
3. Los secretos reales ya no estan en codigo fuente; sigue pendiente rotacion/provisionamiento seguro por ambiente.
4. `npm audit` completo conserva deuda dev/tooling; abrir carril separado.
5. UAT MySQL real sigue pendiente.
6. Scheduler no debe activarse en paralelo con la misma DB.

## Siguiente paso recomendado

Siguiente paso historico del 2026-06-02: primer commit/push, workflow verde y sandbox paralelo. Estado vigente 2026-06-03: produccion ya funciona por Docker y el trabajo futuro debe seguir `docs/PROJECT_OPERATING_MODEL.md`, `docs/MIGRATION_DEPLOY_AND_ROLLBACK_RUNBOOK.md` y `docs/MIGRATION_NEXT_PROMPTS.md`.
