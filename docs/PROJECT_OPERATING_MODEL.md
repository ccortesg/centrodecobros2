# Modelo operativo vigente del proyecto

Ultima actualizacion: 2026-06-07

## Regla principal de trabajo

A partir del 2026-06-03 el proyecto se trabaja siempre sobre:

`C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

No se deben crear nuevas carpetas `phase*` para cambios, correcciones o documentacion futura. La carpeta actual ya es el repositorio de trabajo que se sube a GitHub cuando el propietario lo decida.

## Estado operativo actual

- Rama vigente: `main`.
- Remoto configurado: `https://github.com/ccortesg/centrodecobros2.git`.
- Produccion: la plataforma ya funciona en el servidor productivo por Docker, segun confirmacion del propietario el 2026-06-03.
- Docker: este repositorio no versiona `Dockerfile` ni `docker-compose.yml`; la orquestacion productiva vive en el servidor o en documentacion operativa externa.
- Backend: Laravel `12.54.1`, `composer.json` exige PHP `^8.2`.
- PHP local observado: `8.3.27`.
- Composer local observado: `2.2.6`.
- Frontend: Vue `3.5.30`, Vite `7.x`, Node Windows `v20.20.0`, npm `10.8.2`.
- Rutas actuales: `php artisan route:list` registra 100 rutas en el corte 2026-06-07.
- Produccion heredada del host: existe PHP `7.4.3`; la version nueva no debe exigir cambiar PHP global del servidor si Docker ya aisla el runtime.

## Politica de Git

1. Trabajar en `main` salvo que el propietario pida otra rama.
2. Antes de editar, ejecutar `git status --short`.
3. No publicar `.env`, credenciales, `vendor/`, `node_modules/`, logs, SQLite local, dumps SQL, outputs, test-results ni archivos accidentales de raiz.
4. Versionar codigo fuente, documentacion, pruebas, scripts y configuracion segura.
5. No versionar assets compilados:
   - `public/build/`
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/js/guest-public.js`
   - `public/css/plantilla.css`
   - `public/mix-manifest.json`
6. Los assets se generan en CI/deploy con `npm ci && npm run production`.

## Fronteras que requieren cuidado

- `resources/views/principal.blade.php` es frontera estable del shell autenticado.
- El contrato publico de assets (`public/js/app.js`, `public/js/plantilla.js`, `public/js/guest-public.js`, `public/css/plantilla.css`) no debe cambiarse salvo necesidad tecnica justificada y validada.
- Las rutas API legacy no usan prefijo `/api`; no renombrarlas sin plan de compatibilidad.
- No ejecutar migraciones sobre la DB productiva.
- No activar, duplicar ni modificar scheduler productivo sin solicitud explicita.
- No usar credenciales productivas de Pagadetodo para pruebas automatizadas.
- Pagadetodo real debe validarse solo desde servidor/IP autorizado. En ambiente local mantener `PAGADETODO_MOCK=true` porque el proveedor restringe las llamadas por IP address de origen.

## Base de datos

- La fuente operativa es MySQL productivo o el dump autorizado que entregue el propietario fuera de Git.
- `database/migrations` no reconstruye por si sola el esquema real historico.
- `database/centrodecobros.sql` no debe asumirse versionado ni publicable; `.gitignore` excluye dumps SQL.
- Las pruebas Feature usan SQLite aislado preparado por `scripts/local/prepare_phase33_browser_sqlite.php` y fixtures/traits de `tests/Support`.

## Validaciones recomendadas antes de subir cambios

Backend basico:

```bash
php artisan --version
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit --testsuite Unit
```

Feature con SQLite y Pagadetodo mock desde Windows/WAMP:

```powershell
cd /D C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia
set APP_ENV=testing&& set DB_CONNECTION=sqlite&& set DB_DATABASE=C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& set PAGADETODO_MOCK=true&& C:\wamp64\bin\php\php8.3.0\php.exe scripts\local\prepare_phase33_browser_sqlite.php C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit --testsuite Feature
```

Nota 2026-06-07: el carril Feature SQLite de `tests\Feature\Phase32`, `tests\Feature\Phase34` y `tests\Feature\UX` paso con WAMP PHP 8.3. El suite Feature completo fallo en este entorno porque varios smoke tests usan MySQL local y la cuenta `centro_user@localhost` no tiene acceso. No resolver ese bloqueo con credenciales productivas ni migraciones; preparar runner/dataset de testing o adaptar smoke a SQLite aislado.

Nota 2026-06-08: el propietario confirmo que Pagadetodo ya fue probado exitosamente desde servidor en sandbox y productivo. No intentar reproducir esas llamadas desde local: Pagadetodo restringe por IP de origen.

Frontend:

```powershell
cmd /c "node -v && npm -v"
cmd /c "npm ci"
cmd /c "npm run production"
cmd /c "npm audit --omit=dev --audit-level=low"
```

Documentacion:

```bash
git diff --check docs
```

## Operacion Docker productiva

Como los archivos Docker no estan en este repositorio, ningun agente debe inventar nombres de servicios, volumenes o redes. En servidor se debe inspeccionar primero:

```bash
docker ps
docker compose ps
docker compose config
```

El flujo de despliegue esperado es:

1. Respaldar codigo y DB antes de cambiar produccion.
2. Actualizar el checkout del servidor desde `main`.
3. Instalar dependencias PHP dentro del runtime Docker.
4. Generar assets con Node compatible dentro del pipeline o contenedor definido.
5. Limpiar/optimizar caches Laravel.
6. Validar rutas, scheduler configurado, logs, login y modulos criticos.
7. Mantener secretos y `.env` solo en servidor.

Los comandos exactos dependen del `docker compose` productivo real y deben documentarse cuando el propietario los comparta o cuando el agente tenga acceso al servidor.

## Reglas para agentes de IA

1. Leer este documento antes de proponer cambios.
2. Trabajar en la carpeta actual, no en copias nuevas.
3. Localizar ruta, controlador, componente Vue, tabla y documentacion antes de editar.
4. Mantener cambios pequenos y verificables.
5. Si cambia comportamiento visible, actualizar la documentacion del modulo afectado en la misma tarea.
6. Separar carriles de riesgo: Pagadetodo real en servidor/IP autorizado, scheduler, npm audit completo, Docker productivo y cambios de UI estructural.
7. Reportar claramente que validaciones se ejecutaron y cuales no.
