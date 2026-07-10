# Entorno y operacion

Ultima actualizacion: 2026-07-10

## Entorno validado de este repositorio

| Capa | Valor actual |
| --- | --- |
| Workspace | `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` |
| Rama | `main` |
| Remoto | `https://github.com/ccortesg/centrodecobros2.git` |
| Produccion | Docker en servidor, confirmado por propietario |
| Laravel | `12.54.1` |
| PHP requerido | `^8.2` |
| PHP local bash | `8.3.27` |
| PHP Feature recomendado | `C:\wamp64\bin\php\php8.3.0\php.exe` |
| Composer local | `2.2.6` |
| Node local compatible | `v24.16.0` en `/home/ccortesg/.nvm/versions/node/v24.16.0/bin/node` |
| npm Windows | `10.8.2` |
| Frontend | Vue `3.5.30` + Vite `7.x` |
| DB productiva | MySQL en servidor |
| DB pruebas Feature | SQLite aislado |

## Lanes soportadas

### Local backend

```bash
php artisan --version
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit --testsuite Unit
```

### Local Feature con SQLite

El PHP CLI de Linux puede no traer `pdo_sqlite`. Para Feature completos usar WAMP PHP 8.3:

```powershell
cd /D C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia
set APP_ENV=testing&& set DB_CONNECTION=sqlite&& set DB_DATABASE=C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& set PAGADETODO_MOCK=true&& C:\wamp64\bin\php\php8.3.0\php.exe scripts\local\prepare_phase33_browser_sqlite.php C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit --testsuite Feature
```

### Frontend

```powershell
cmd /c "node -v && npm -v"
cmd /c "npm ci"
cmd /c "npm run production"
cmd /c "npm audit --omit=dev --audit-level=low"
```

## Build frontend

Los scripts actuales de `package.json` usan `scripts/local/run_phase15_build.js`:

- `npm run development`
- `npm run production`

Artefactos esperados despues del build:

- `public/js/app.js`
- `public/js/plantilla.js`
- `public/js/guest-public.js`
- `public/css/plantilla.css`
- `public/build/manifest.json`

Estos artefactos no se versionan; se generan en CI/deploy.

## Produccion Docker

La aplicacion ya opera en Docker en el servidor productivo. Este repo no contiene el compose productivo, por lo que todo agente debe evitar asumir nombres de servicios. Antes de documentar comandos exactos de servidor se debe inspeccionar:

```bash
docker ps
docker compose ps
docker compose config
```

Reglas de operacion:

1. No cambiar PHP 7.4.3 global del host si Docker ya aisla la version nueva.
2. No ejecutar migraciones sobre DB productiva.
3. Mantener `.env` y secretos solo en servidor.
4. Generar assets en deploy o CI.
5. Limpiar caches Laravel despues de cambiar codigo/configuracion.
6. Validar logs, login y modulos criticos despues de cada despliegue.

### Auditoria de integraciones

Los modulos `Outgoing API Requests`, `Incoming API Requests` y `User Activity Log` requieren migraciones aditivas para crear:

- `outgoing_api_requests`
- `incoming_api_requests`
- `user_activity_logs`

Antes de produccion:

```bash
php artisan migrate --pretend
```

Durante ventana aprobada:

```bash
php artisan migrate --force
```

La retencion se maneja con comando manual, sin scheduler:

```bash
php artisan audit:purge --days=365 --dry-run
php artisan audit:purge --days=365
```

## Scheduler

El scheduler contiene procesos financieros sensibles:

- `TransaccionDomController@ejecutarCron`
- `TransaccionController@revisarStatus`

No activar, duplicar ni modificar scheduler sin instruccion explicita. Si se trabaja en sandbox o ambiente paralelo contra la misma DB, el scheduler debe permanecer deshabilitado.

## Produccion Docker: webhooks configurables

Esta implementacion requiere tres migraciones aditivas y un worker persistente. Los comandos siguientes son una guia para el operador; no deben ejecutarse desde un agente local contra produccion.

### 1. Precheck y respaldo

```bash
cd /var/www/centrodecobros2
git status --short
sudo docker compose ps
sudo docker compose config --services
sudo docker compose exec app php artisan about
```

Crear respaldo MySQL antes de cualquier migracion con el procedimiento productivo vigente. No pegar credenciales en consola compartida, Git ni documentacion.

Confirmar que las variables no secretas queden inicialmente asi:

```dotenv
WEBHOOK_NOTIFICATIONS_ENABLED=false
WEBHOOK_QUEUE_CONNECTION=database
WEBHOOK_QUEUE_NAME=webhooks
WEBHOOK_CONNECT_TIMEOUT=5
WEBHOOK_TIMEOUT=15
WEBHOOK_PROCESSING_TIMEOUT=60
WEBHOOK_MAX_ATTEMPTS=8
WEBHOOK_RATE_LIMIT_PER_MINUTE=25
```

No es obligatorio cambiar `QUEUE_CONNECTION`; los jobs webhook seleccionan explicitamente `WEBHOOK_QUEUE_CONNECTION`.

### 2. Desplegar codigo y dependencias

```bash
cd /var/www/centrodecobros2
git fetch
git pull --ff-only
sudo docker compose exec app composer install --no-dev --optimize-autoloader --no-interaction
sudo docker compose exec app php artisan optimize:clear
```

### 3. Revisar las migraciones sin ejecutarlas

```bash
sudo docker compose exec app php artisan migrate:status
sudo docker compose exec app php artisan migrate --pretend --path=database/migrations/2026_07_10_120000_create_webhook_notification_tables.php
sudo docker compose exec app php artisan migrate --pretend --path=database/migrations/2026_07_10_120100_create_queue_tables.php
sudo docker compose exec app php artisan migrate --pretend --path=database/migrations/2026_07_10_120200_add_transaction_correlation_to_cancellations.php
```

Revisar que solo cree tablas webhook/queue y agregue `idtransaccion` nullable a `cancelacionesDom` y `cancelacionesLector`.

Si `migrate:status` muestra pendientes las migraciones de auditoria `2026_07_03_120000`, `120100` o `120200`, revisar y ejecutar tambien esas rutas de forma individual. No usar un `php artisan migrate --force` general: la base historica puede contener tablas importadas cuyo registro de migraciones no coincida con el schema real.

### 4. Ejecutar migraciones en ventana aprobada

Solo despues de respaldo y aprobacion:

```bash
sudo docker compose exec app php artisan migrate --path=database/migrations/2026_07_10_120000_create_webhook_notification_tables.php --force
sudo docker compose exec app php artisan migrate --path=database/migrations/2026_07_10_120100_create_queue_tables.php --force
sudo docker compose exec app php artisan migrate --path=database/migrations/2026_07_10_120200_add_transaction_correlation_to_cancellations.php --force
sudo docker compose exec app php artisan migrate:status
```

No ejecutar seeds ni migraciones no revisadas dentro de esta ventana.

### 5. Compilar frontend con el comando productivo vigente

```bash
sudo docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e npm_config_cache=/app/.npm-cache \
  -v "$PWD":/app \
  -w /app \
  node:22-bookworm \
  sh -lc 'npm ci --include=dev && npm run production'
```

Los assets se generan en servidor; no se versionan.

### 6. Agregar un worker persistente

Se debe elegir exactamente una de las siguientes alternativas. No ejecutar ambas al mismo tiempo.

#### Alternativa A (recomendada): servicio dedicado en Docker Compose

El compose no esta versionado en este repo. Agregar un servicio equivalente que reutilice la misma imagen, volumen, red y variables del servicio `app`:

```yaml
queue:
  build: .
  restart: unless-stopped
  command: php artisan queue:work database --queue=webhooks --sleep=3 --tries=3 --timeout=30 --max-time=3600
  volumes:
    - ./:/var/www/html
    - /var/run/mysqld:/var/run/mysqld
  stop_grace_period: 30s
```

Copiar tambien `environment`, `env_file`, `networks`, `user` y `depends_on` que utilice realmente `app`. No publicar un puerto adicional para `queue`.

Validar y levantar:

```bash
sudo docker compose config
sudo docker compose up -d app queue
sudo docker compose ps
sudo docker compose logs --tail=100 queue
```

No usar `docker compose exec -d app queue:work` como solucion permanente: no queda supervisado despues de reinicios.

Con esta alternativa Docker Compose reemplaza a Supervisor mediante `restart: unless-stopped`. No instalar Supervisor dentro del contenedor.

#### Alternativa B: Supervisor en el host

Usar esta alternativa solo si el compose productivo no se puede modificar. Supervisor se instala en el host y mantiene un unico `queue:work` dentro de `app`.

Confirmar primero la ruta absoluta del proyecto y del binario Docker:

```bash
pwd
command -v docker
sudo docker compose ps
```

Instalar y habilitar Supervisor en un host Debian/Ubuntu:

```bash
sudo apt update
sudo apt install -y supervisor
sudo systemctl enable --now supervisor
sudo systemctl status supervisor --no-pager
```

Crear `/etc/supervisor/conf.d/centrodecobros-webhooks.conf`, sustituyendo `/RUTA/ABSOLUTA/DEL/PROYECTO` por el resultado de `pwd` y `/usr/bin/docker` si `command -v docker` devuelve otra ruta:

```ini
[program:centrodecobros-webhooks]
process_name=%(program_name)s
directory=/RUTA/ABSOLUTA/DEL/PROYECTO
command=/usr/bin/docker compose exec -T app php artisan queue:work database --queue=webhooks --sleep=3 --tries=3 --timeout=30 --max-time=3600
user=root
numprocs=1
autostart=true
autorestart=true
startsecs=5
startretries=20
stopsignal=TERM
stopasgroup=true
killasgroup=true
stopwaitsecs=40
redirect_stderr=true
stdout_logfile=/var/log/supervisor/centrodecobros-webhooks.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=10
environment=HOME="/root"
```

Cargar y validar:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status centrodecobros-webhooks
sudo tail -n 100 /var/log/supervisor/centrodecobros-webhooks.log
```

El estado esperado es `RUNNING`. No anteponer `sudo` dentro de `command`: Supervisor ya inicia el proceso como `root`. `-T` es obligatorio para evitar que `docker compose exec` intente reservar una terminal interactiva.

### 7. Cache y reinicio

```bash
sudo docker compose exec app php artisan optimize:clear
sudo docker compose exec app php artisan config:cache
sudo docker compose exec app php artisan route:cache
sudo docker compose exec app php artisan view:cache
sudo docker compose exec app php artisan queue:restart
sudo docker compose restart app
```

Si se eligio la alternativa A:

```bash
sudo docker compose restart queue
sudo docker compose logs --tail=100 queue
```

Si se eligio la alternativa B, esperar a que Supervisor recupere el proceso despues del reinicio de `app` y verificarlo:

```bash
sudo supervisorctl status centrodecobros-webhooks
sudo supervisorctl restart centrodecobros-webhooks
sudo tail -n 100 /var/log/supervisor/centrodecobros-webhooks.log
```

Confirmar que no cambio el scheduler:

```bash
sudo docker compose exec app php artisan route:list --path=integraciones/webhooks
sudo docker compose exec app php artisan schedule:list
```

### 8. Preparar modo shadow

Con `WEBHOOK_NOTIFICATIONS_ENABLED=false`:

```bash
sudo docker compose exec app php artisan webhooks:import-legacy --dry-run --mode=shadow
sudo docker compose exec app php artisan webhooks:import-legacy --mode=shadow
```

Revisar URLs invalidas reportadas. El comando no configura HMAC ni muestra secretos.

Despues cambiar solo el interruptor global a `true`, reconstruir config y reiniciar app/worker:

```bash
sudo docker compose exec app php artisan optimize:clear
sudo docker compose exec app php artisan config:cache
sudo docker compose exec app php artisan queue:restart
sudo docker compose restart app
```

Reiniciar despues el worker con `sudo docker compose restart queue` para la alternativa A o `sudo supervisorctl restart centrodecobros-webhooks` para la alternativa B.

En `shadow` el callback legacy continua enviandose; el motor nuevo solo crea entregas `shadow`.

### 9. Activar `app.donarconcausa.org.mx`

1. Entrar como Administrador a `Integraciones > Webhook Configuration`.
2. Seleccionar el usuario correspondiente a `app.donarconcausa.org.mx`.
3. Confirmar modo `shadow` y endpoint HTTPS.
4. Seleccionar eventos y formato acordados.
5. Habilitar HMAC-SHA256 y generar/rotar secreto.
6. Transferir el secreto por un canal seguro; no guardarlo en tickets, logs ni Git.
7. Ejecutar `Enviar prueba`.
8. Verificar en `Webhook Deliveries` HTTP/ACK y firma del receptor.
9. Confirmar tolerancia 300 s, anti-replay 10 min y limite 30/min/IP en el receptor.
10. Cambiar a `active` solo cuando prueba, worker y monitoreo esten correctos.

### 10. Verificacion y rollback

```bash
sudo docker compose exec app php artisan queue:failed
sudo docker compose exec app php artisan tinker --execute="echo DB::table('jobs')->where('queue', 'webhooks')->count();"
sudo docker compose logs --tail=200 app
```

Consultar tambien `sudo docker compose logs --tail=200 queue` con la alternativa A o `sudo tail -n 200 /var/log/supervisor/centrodecobros-webhooks.log` con la alternativa B.

Smoke funcional:

- admin ve configuracion/entregas y puede exportar;
- cliente recibe 403 y no ve los menus;
- una prueba HMAC queda `delivered`;
- un duplicado conserva el mismo `event_id` y no duplica la entrega;
- Outgoing API Requests muestra copia sanitizada;
- el payload recibido por la plataforma destino conserva todos los campos esperados.

Rollback funcional inmediato: cambiar el cliente de `active` a `shadow` o `legacy`. Esto reactiva el callback anterior sin borrar tablas. Si falla UI/codigo, volver al commit aprobado, recompilar, limpiar caches y reiniciar `app`/`queue`. No ejecutar `migrate:rollback` automaticamente ni borrar tablas con evidencia operativa.

## Operacion segura local

1. Confirmar `git status --short`.
2. Usar `.env` local no versionado.
3. Mantener `PAGADETODO_MOCK=true` salvo validacion oficial con credenciales sandbox.
4. No usar DB productiva para pruebas destructivas.
5. Ejecutar pruebas proporcionales al cambio.
6. Actualizar documentacion del modulo afectado.
