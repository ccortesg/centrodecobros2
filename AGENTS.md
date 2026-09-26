# Instrucciones Para Agentes

## Contexto minimo

Centro de Cobros es una aplicacion financiera Laravel 12 con shell Blade, componentes Vue 3, MySQL, integracion Pagadetodo, APIs legacy sin prefijo `/api`, auditoria de integraciones y notificaciones webhook por Database Queue.

El contexto tecnico completo y vigente esta en `docs/CODEX_PROJECT_HANDOFF.md`. Leelo antes de cambiar comportamiento.

## Workspace esperado

- Ruta activa WSL2 para desarrollo local/HTTP: `/home/ccortesg/workspace/centrodecobros2`.
- La ruta Windows `/mnt/c/temp/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` es el origen preservado de la migracion del 2026-08-04; no debe volver a usarse como workspace activo salvo instruccion expresa.
- Rama observada: `main`; remoto: `origin`.
- Produccion vive en Docker fuera de este repositorio. No hay `Dockerfile` ni Compose versionados aqui.

## Stack confirmado

- PHP `^8.2`; Laravel `12.54.1`; PHPUnit `11.5.55`.
- Vue `3.5.30`; Vite `7.3.5`; Node declarado en `.nvmrc`: `22.22.1`.
- MySQL operativo local; las pruebas Feature locales reconstruyen exclusivamente la base desechable `centrodecobros_testing`. CI conserva su SQLite efimero aislado.
- Composer y npm deben usar los lockfiles existentes. No actualizar dependencias ni lockfiles sin solicitud.

## Mapa de trabajo

- `routes/web.php`, `routes/api.php`: contratos web y API legacy.
- `app/Http/Controllers`: logica historica principal; `TransaccionController` y `TransaccionDomController` tienen alto acoplamiento financiero.
- `app/Services`: auditoria, webhooks configurables y sincronizacion de estados.
- `resources/views/contenido/contenido.blade.php`: switchboard de modulos.
- `resources/assets/js/components`: UI Vue.
- `resources/assets/js/styles/ux-ui.css`: estilos compartidos.
- `database/migrations`: cambios recientes aditivos, pero no reconstruyen todo el esquema historico.
- `tests/Support`: esquema/fixtures MySQL controlados de Feature con bloqueo por ambiente y nombre de base.
- `docs/MODULES`: fichas funcionales por dominio.

## Reglas no negociables

1. No revelar ni versionar `.env`, credenciales, tokens, dumps, logs, uploads ni secretos HMAC.
2. No ejecutar llamadas reales Pagadetodo desde local. El proveedor restringe IP de origen; usar `PAGADETODO_MOCK=true` en pruebas autorizadas.
3. No ejecutar migraciones productivas, queries destructivos ni activar/modificar scheduler sin autorizacion explicita.
4. No tocar `resources/views/principal.blade.php` salvo necesidad tecnica justificada y verificada.
5. No versionar `public/build`, `public/js`, `public/css` ni `public/mix-manifest.json`.
6. No cambiar rutas, payloads ni respuestas API legacy sin plan de compatibilidad.
7. No asumir que ocultar un menu autoriza una operacion: validar middleware y ownership en backend.
8. No reactivar la deduplicacion comentada de `RespuestaController::storePublic()` o `storeLectorPublic()` sin decision explicita; el comportamiento vigente conserva cada intento recibido.
9. No resolver el posible JOIN duplicado de cargos recurrentes como cambio incidental; es un riesgo financiero separado.
10. No limpiar, revertir ni sobrescribir cambios locales preexistentes.

## Convenciones

- Seguir patrones existentes y hacer cambios pequenos por modulo.
- Validar criterios dinamicos mediante allowlists.
- Mantener `idusuario` y `productivo` en todo query de datos de Cliente.
- Tratar `idrol=1` como Administrador y `idrol=2` como Cliente; otros roles quedan bloqueados por el middleware actual.
- Usar `America/Hermosillo` para reglas de negocio temporales.
- Sanitizar solamente bitacoras/UI/exports; no reserializar `webhook_deliveries.raw_body`, porque HMAC depende de bytes exactos.
- Documentar cualquier cambio de rutas, estado, payload, scheduler, tablas, roles o despliegue en el mismo trabajo.

## Instalacion y ejecucion local

No ejecutes estos pasos automaticamente si las dependencias ya existen.

```bash
cd /home/ccortesg/workspace/centrodecobros2
composer install --no-interaction
npm ci
php artisan serve
```

La aplicacion completa usa la base local `centrodecobros`. PHPUnit usa `.env.testing` ignorado y la base desechable `centrodecobros_testing`; nunca debe apuntar a `centrodecobros`. Las credenciales locales no se documentan ni se versionan.

## Validaciones

Validacion base no destructiva:

```bash
php artisan --version
php artisan route:list --no-ansi
php artisan schedule:list --no-ansi
composer validate --no-check-publish --no-interaction
php vendor/bin/phpunit --testsuite Unit --do-not-cache-result
git diff --check
```

Lint PHP de codigo propio:

```bash
find app bootstrap config database routes tests -type f -name '*.php' -print0 \
  | xargs -0 -n1 php -l
```

Build frontend, solo si la tarea lo requiere:

```bash
npm ci
npm run production
```

Feature tests locales destruyen y reconstruyen `centrodecobros_testing`. Ejecutarlos solo con `.env.testing`, `APP_ENV=testing` y `PAGADETODO_MOCK=true`, sin `--parallel`; el arnes aborta si el nombre real seleccionado por MySQL no es exactamente `centrodecobros_testing`. El workflow CI puede seguir usando su SQLite efimero en memoria.

## Git y documentacion

- No hacer push, pull, merge, rebase, cambio de rama ni reescritura de historial sin solicitud.
- Revisar `git status --short --branch` antes y despues.
- Mantener documentos historicos; marcar lo sustituido y enlazar al handoff vigente.
- `docs/CODEX_PROJECT_HANDOFF.md` prevalece para estado actual; `docs/MIGRATION_PHASE_*` conserva evidencia historica.

## Definicion de terminado

Una tarea termina cuando el cambio solicitado esta implementado, no amplia el alcance, conserva contratos/ownership, tiene validaciones proporcionales, `git diff --check` pasa, no hay secretos ni assets compilados nuevos, y la documentacion vigente refleja cualquier cambio operativo o funcional.
