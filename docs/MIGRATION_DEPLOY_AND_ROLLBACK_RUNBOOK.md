# Runbook de deploy paralelo y rollback

Ultima actualizacion: 2026-06-02  
Baseline: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

## Objetivo

Montar la version Laravel 12/PHP 8.3 en paralelo a la version PHP 7.4 actual, usando la misma base de datos solo para validacion controlada, sin interrumpir `/var/www/centro`.

## Principios

1. La version actual no se modifica.
2. La nueva version vive en otro directorio y otro vhost/subdominio.
3. PHP 8.3 se aisla por PHP-FPM o contenedor.
4. La DB no se migra.
5. El scheduler nuevo no se activa mientras comparta DB.
6. Pagadetodo queda en `PAGADETODO_MOCK=true` hasta tener sandbox oficial.

## Preparacion en GitHub

1. Usar branch de publicacion sandbox: `phase34-sandbox-release`.
2. Crear tag despues del primer commit validado: `sandbox-phase34-v1.0.0`.
3. Verificar que no se suben `.env`, `vendor`, `node_modules`, logs, SQLite local, `output/`, `test-results/` ni archivos accidentales de raiz.
4. Mantener fuera de Git los assets compilados (`public/build/`, `public/js/app.js`, `public/js/plantilla.js`, `public/js/guest-public.js`, `public/css/plantilla.css`, `public/mix-manifest.json`).
5. Generar assets en CI/deploy con `npm ci && npm run production`.
6. Ejecutar el workflow `.github/workflows/sandbox-release-validation.yml` antes de deploy.
7. Registrar hash/tag que se desplegara.

## Preparacion del servidor

Ejecutar inventario antes de tocar nada:

```bash
lsb_release -a
php -v
apache2 -v
apachectl -M
mysql --version
find /etc/apache2/sites-enabled -maxdepth 1 -type l -ls
systemctl status apache2 --no-pager
```

Respaldar:

```bash
sudo tar -czf /var/backups/centro-app-$(date +%F-%H%M).tgz /var/www/centro
mysqldump --single-transaction --routines --triggers centrodecobros > /var/backups/centrodecobros-$(date +%F-%H%M).sql
```

## Estrategia PHP 8.3

### Camino A: contenedor en Ubuntu 20.04

Usar si no se quiere alterar PHP 7.4 ni repos del host. Apache publica un vhost que proxy/reverse-proxy hacia el contenedor. Este camino evita depender de paquetes PHP 8.3 para Focal.

Pendientes de esta ruta:
- instalar Docker/Compose;
- definir volumen de codigo y logs;
- conectar a MySQL del host;
- documentar arranque automatico.

### Camino B: PHP-FPM por vhost en host soportado

Usar si el host se actualiza/migra a Ubuntu 22.04/24.04 o se confirma repositorio compatible.

Paquetes esperados:

```bash
sudo apt install php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-sqlite3 unzip git curl
```

No instalar `libapache2-mod-php8.3` para esta coexistencia. Apache debe enrutar por `SetHandler` a `/run/php/php8.3-fpm.sock`.

Habilitar modulos Apache:

```bash
sudo a2enmod proxy proxy_fcgi setenvif rewrite headers
sudo systemctl reload apache2
```

## Deploy de codigo

```bash
sudo mkdir -p /var/www/centro-v12-sandbox
sudo chown ubuntu:www-data /var/www/centro-v12-sandbox
git clone --branch phase34-sandbox-release <repo-github> /var/www/centro-v12-sandbox
cd /var/www/centro-v12-sandbox
```

Instalar dependencias:

```bash
php8.3 /usr/local/bin/composer install --no-dev --optimize-autoloader
```

Los assets compilados no se versionan. Generarlos en servidor o CI antes de servir la aplicacion:

```bash
npm ci
npm run production
test -f public/js/app.js
test -f public/js/plantilla.js
test -f public/css/plantilla.css
test -f public/js/guest-public.js
test -f public/build/manifest.json
```

Configurar `.env` sandbox:

```bash
cp .env.example .env
php8.3 artisan key:generate --force
```

Valores minimos:

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
QUEUE_DRIVER=sync
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=centrodecobros
DB_USERNAME=...
DB_PASSWORD=...
```

No ejecutar migraciones.

Caches y permisos:

```bash
php8.3 artisan config:clear
php8.3 artisan cache:clear
php8.3 artisan route:clear
php8.3 artisan view:clear
php8.3 artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

## Vhost Apache PHP 8.3-FPM

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

Activar:

```bash
sudo a2ensite centro-v12.conf
sudo apachectl configtest
sudo systemctl reload apache2
```

## Validacion

```bash
php8.3 artisan --version
php8.3 artisan route:list
php8.3 artisan schedule:list
php8.3 vendor/bin/phpunit --testsuite Unit
npm audit --omit=dev --audit-level=low
```

Smoke browser:

1. `GET /login`
2. `GET /url`
3. login admin
4. login cliente
5. `/main`
6. `/dashboard`
7. `Clientes`
8. `Generar Liga de pago`
9. `Cargos Recurrentes`
10. `Reportes`

Validar que:

- cookies de la version nueva no sustituyen la sesion de la version actual;
- `storage/logs/laravel.log` nuevo no tiene errores;
- Apache error log nuevo no tiene errores;
- no se instalo cron del nuevo directorio;
- `PAGADETODO_MOCK=true` o sandbox oficial esta confirmado.

## Rollback

Rollback de la version paralela:

```bash
sudo a2dissite centro-v12.conf
sudo systemctl reload apache2
```

Si el problema fue codigo del sandbox, borrar o renombrar `/var/www/centro-v12-sandbox`. La version actual en `/var/www/centro` no debe tocarse.

Rollback si se altero infraestructura PHP/Apache:

1. Revertir el cambio aplicado en vhost/modulos.
2. Confirmar que el vhost actual sigue usando PHP 7.4.
3. Reiniciar/reload Apache.
4. Validar `/login`, `/main`, scheduler y logs de la version actual.

Rollback de DB solo aplica si alguna prueba escribio datos reales no deseados. Por eso el respaldo previo es obligatorio.

## Condiciones para promocionar despues

No promover a reemplazo de produccion hasta cerrar:

- sandbox oficial Pagadetodo;
- webhooks con idempotencia/firma/origen;
- secretos fuera del codigo;
- UAT por rol sobre MySQL real;
- decision formal sobre `npm audit`;
- plan de scheduler sin duplicidad.
