# Documentacion tecnica - Centro de Cobros

Ultima actualizacion: 2026-06-03

## Punto de entrada actual

Leer en este orden para entender el estado vigente:

1. `docs/PROJECT_OPERATING_MODEL.md`
2. `docs/AI_AGENT_GUIDE.md`
3. `docs/ROUTES_AND_FLOW.md`
4. `docs/ARCHITECTURE.md`
5. `docs/ENVIRONMENT_AND_OPERATION.md`
6. `docs/DEVELOPER_ONBOARDING.md`
7. `docs/SECURITY_AND_RISKS.md`
8. `docs/INTEGRATIONS.md`
9. `docs/MODULES/*.md`
10. `docs/MIGRATION_DEPLOY_AND_ROLLBACK_RUNBOOK.md`
11. `docs/MIGRATION_RELEASE_CHECKLIST.md`
12. `docs/MIGRATION_PHASE_34_VALIDACION_PAGADETODO_WEBHOOKS_IDEMPOTENCIA.md`
13. `docs/MIGRATION_PHASE_35_GITHUB_SANDBOX_RELEASE.md`
14. `docs/MIGRATION_MASTER_PLAN.md`
15. `docs/MIGRATION_DECISIONS_LOG.md`
16. `docs/MIGRATION_RISK_REGISTER.md`
17. `docs/MIGRATION_CHANGELOG.md`

Los documentos `MIGRATION_PHASE_*` son evidencia historica por fase. Para tareas nuevas, la regla rectora es `PROJECT_OPERATING_MODEL.md`.

## Estado actual consolidado

- Workspace/repositorio vigente: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`.
- Regla vigente desde 2026-06-03: no crear nuevas carpetas de fase; trabajar siempre sobre este repositorio.
- Rama vigente: `main`.
- Remoto: `https://github.com/ccortesg/centrodecobros2.git`.
- Produccion: funcionando en servidor por Docker, confirmado por el propietario el 2026-06-03.
- Docker: no hay `Dockerfile` ni `docker-compose.yml` versionados en este repo; la orquestacion vive en servidor.
- Laravel: `12.54.1`.
- PHP requerido: `^8.2`.
- PHP local observado: `8.3.27`.
- Composer local observado: `2.2.6`.
- Frontend: Vue `3.5.30`, Vite `7.x`.
- Node/npm observados desde Windows: Node `v20.20.0`, npm `10.8.2`.
- Assets compilados: no se versionan; se generan con `npm ci && npm run production` en CI/deploy.
- Contrato publico preservado:
  - `public/js/app.js`
  - `public/js/plantilla.js`
  - `public/js/guest-public.js`
  - `public/css/plantilla.css`
- `principal.blade.php` se trata como frontera estable.

## Arquitectura resumida

- Laravel MVC clasico con controladores grandes que concentran reglas de negocio, persistencia e integraciones.
- Vue 3 monta componentes legacy en el shell autenticado y consume endpoints web/API sin prefijo `/api`.
- Vite genera el bundle principal; la lane `plantilla.*` y assets guest siguen como contrato publico separado.
- MySQL productivo es la fuente operativa de datos; migrations historicas no reconstruyen todo el esquema real.
- Feature tests usan SQLite aislado con `PAGADETODO_MOCK=true`.

## Alcance tecnico cerrado hasta Fase 35

- Migracion a Laravel 12 y Vue 3.
- Build Vite estabilizado sin romper contrato publico de assets.
- Middleware `Administrador` con reglas reales por rol.
- Ownership/whitelists en superficies criticas para cliente, archivos, transacciones, respuestas, SPEI, domiciliacion y exportaciones.
- Mock controlado Pagadetodo por `services.pagadetodo.mock`.
- Webhooks `Service/*` endurecidos con validacion minima e idempotencia local en Fase 34.
- Repo Git inicializado en `main`, `.gitignore` saneado y workflow GitHub de validacion sandbox agregado.
- Credenciales Pagadetodo/Pusher externalizadas hacia `.env`, `config/services.php` y variables `VITE_PUSHER_*`.

## Condiciones abiertas

- Sandbox oficial Pagadetodo sigue pendiente de URL/credenciales no productivas.
- Firma/origen de webhooks queda pendiente hasta recibir especificacion del proveedor.
- Realtime Pusher/Echo requiere validacion end-to-end con credenciales aisladas.
- `npm audit` completo puede reportar deuda dev/tooling; la frontera runtime es `npm audit --omit=dev`.
- Docker productivo funciona, pero su compose/orquestacion no esta documentado dentro del repo.
- Scheduler productivo no debe modificarse ni duplicarse sin solicitud explicita.

## Validacion rapida

```bash
git status --short
php artisan --version
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit --testsuite Unit
git diff --check docs
```

Para Feature completo local, usar el runner WAMP PHP 8.3 descrito en `docs/PROJECT_OPERATING_MODEL.md`.

## Siguiente prompt recomendado

El prompt vigente para futuras tareas esta en `docs/MIGRATION_NEXT_PROMPTS.md`. Ese archivo ya refleja que no se deben crear nuevas carpetas de fase y que todo trabajo se realiza sobre este repositorio.
