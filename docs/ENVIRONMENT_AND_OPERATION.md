# Entorno y operacion

Ultima actualizacion: 2026-06-03

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
| Node Windows | `v20.20.0` |
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

## Operacion segura local

1. Confirmar `git status --short`.
2. Usar `.env` local no versionado.
3. Mantener `PAGADETODO_MOCK=true` salvo validacion oficial con credenciales sandbox.
4. No usar DB productiva para pruebas destructivas.
5. Ejecutar pruebas proporcionales al cambio.
6. Actualizar documentacion del modulo afectado.
