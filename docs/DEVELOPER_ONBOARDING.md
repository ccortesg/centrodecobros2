# Onboarding de desarrolladores

Ultima actualizacion: 2026-03-14

## Lectura inicial obligatoria

1. `docs/README.md`
2. `docs/MIGRATION_MASTER_PLAN.md`
3. `docs/MIGRATION_FE3_POSTCHECK.md`
4. `docs/MIGRATION_SMOKE_TESTS.md`
5. `docs/MIGRATION_RISK_REGISTER.md`
6. `docs/MIGRATION_DECISIONS_LOG.md`

## Primer chequeo tecnico

Antes de tocar codigo o documentacion, correr:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1
powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1
php vendor\bin\phpunit tests\Feature\Smoke tests\Feature\ExampleTest.php
```

Si la tarea toca frontend:

```powershell
nvm use 22.22.1
npm ci
npm run development
```

## Donde vive la logica real

- `app/Http/Controllers/TransaccionController.php`
- `app/Http/Controllers/TransaccionDomController.php`
- `app/Http/Controllers/RespuestaController.php`
- `resources/assets/js/components/*.vue`
- `routes/web.php`

No esperar una capa de servicios formal ni boundaries limpios por dominio.

## Regla de orientacion

Para estado actual del proyecto:

- usar `MIGRATION_MASTER_PLAN`, `MIGRATION_FE3_POSTCHECK` y `MIGRATION_SMOKE_TESTS`.

Para contexto historico:

- usar `MIGRATION_PHASE_*`, `MIGRATION_FRONTEND_PRECHECK`, `MIGRATION_PACKAGE_COMPATIBILITY_MATRIX` y demas snapshots.

## Riesgos comunes al intervenir

1. Asumir que `database/migrations` reconstruye el sistema real.
2. Tocar payloads o rutas legacy sin revisar contratos frontend/backend.
3. Mezclar hardening de dependencias con tareas de migracion de runtime o bundler.
4. Trabajar en la lane Node incorrecta.
5. Confiar en que middleware, auth o roles actuales equivalen a seguridad robusta.

## Guia rapida de diagnostico

Si falla una operacion:

1. revisar request real del componente Vue;
2. confirmar la ruta exacta en `routes/web.php`;
3. revisar el controlador y la query real;
4. revisar `storage/logs/laravel.log`;
5. correr o ampliar la smoke suite correspondiente;
6. documentar el hallazgo en `docs/` si cambia el estado operativo.

## Nota sobre el frontend

- La lane operativa actual es `Node 22.22.1` / npm `10.9.4`.
- `Node 8.17.0` queda solo como referencia historica o rollback documental de snapshots anteriores.
- `npm audit` actual no esta resuelto; eso se atiende en una fase separada de hardening.
