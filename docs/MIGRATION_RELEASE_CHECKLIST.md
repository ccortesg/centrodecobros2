# Checklist de publicacion y produccion Docker

Ultima actualizacion: 2026-06-03
Repositorio: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`
Rama: `main`

## Predeploy local

- Confirmar `git status --short`.
- Confirmar que no hay `.env`, secretos, dumps SQL, SQLite, logs, `vendor/`, `node_modules/`, outputs, test-results ni assets compilados staged.
- Confirmar que `public/build/`, `public/js/app.js`, `public/js/plantilla.js`, `public/js/guest-public.js`, `public/css/plantilla.css` y `public/mix-manifest.json` no estan versionados.
- Confirmar que cambios de negocio tienen documentacion de modulo actualizada.
- Confirmar que no se agregaron migraciones para correr en produccion sin aprobacion.
- Confirmar que no se modifico scheduler sin aprobacion.
- Confirmar que no se usaron credenciales productivas Pagadetodo en pruebas.

## Validaciones locales recomendadas

```bash
php artisan --version
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit --testsuite Unit
git diff --check
```

Si cambia frontend:

```powershell
cmd /c "npm ci"
cmd /c "npm run production"
cmd /c "npm audit --omit=dev --audit-level=low"
```

Si cambia Pagadetodo, webhooks, ownership o flujo financiero:

```powershell
cd /D C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia
set APP_ENV=testing&& set DB_CONNECTION=sqlite&& set DB_DATABASE=C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& set PAGADETODO_MOCK=true&& C:\wamp64\bin\php\php8.3.0\php.exe scripts\local\prepare_phase33_browser_sqlite.php C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit --testsuite Feature
```

## Predeploy en servidor Docker

- Confirmar ruta real del checkout productivo.
- Confirmar compose real:
  - `docker ps`
  - `docker compose ps`
  - `docker compose config`
- Confirmar nombre del servicio PHP/Laravel antes de ejecutar comandos.
- Respaldar codigo, `.env` y DB.
- Confirmar que PHP `7.4.3` global del host no sera alterado si Docker ya aisla runtime.
- Confirmar que `.env` productivo conserva:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL` correcto
  - `DB_*` correcto
  - secretos Pagadetodo/Pusher/SMTP solo en servidor
  - configuracion real o mock de Pagadetodo segun ambiente autorizado
- Confirmar permisos de `storage/` y `bootstrap/cache/`.
- Confirmar estrategia de assets:
  - generados en CI, o
  - generados dentro del contenedor/runtime definido.

## Deploy

- `git fetch origin`
- `git checkout main`
- `git pull --ff-only origin main`
- `composer install --no-dev --optimize-autoloader --no-interaction` dentro del runtime PHP correcto.
- `npm ci && npm run production` en el runtime Node correcto si CI no genero assets.
- Limpiar/optimizar caches Laravel.
- Validar `route:list` y `schedule:list`.
- Reiniciar/recargar servicios Docker segun compose real.

## Postdeploy inmediato

- Abrir `/login`.
- Abrir `/url`.
- Entrar como admin y cliente de prueba.
- Confirmar `/main`, sidebar y topbar.
- Confirmar:
  - `POST /notification/get`
  - `GET /dashboard`
  - Clientes
  - Generacion de ligas
  - Domiciliacion/cargos recurrentes en modo seguro
  - SPEI y reportes
  - Usuarios/Roles solo admin
- Confirmar consola browser sin errores criticos.
- Confirmar logs Docker/Laravel sin 500 nuevos.
- Confirmar que scheduler activo es el esperado y no hay duplicado.
- Confirmar que no se generaron cargos reales no autorizados.

## Criterio de salida

La publicacion puede aceptarse si:

1. produccion sigue respondiendo;
2. login admin/cliente funciona;
3. assets cargan correctamente;
4. logs no muestran errores nuevos;
5. scheduler esta bajo control;
6. no se publicaron secretos;
7. rollback por Git/Docker esta disponible;
8. riesgos residuales del cambio quedaron documentados.
