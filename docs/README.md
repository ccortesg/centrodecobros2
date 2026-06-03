# Documentacion tecnica y de migracion - Centro de Cobros

## Punto de entrada actual

Leer en este orden:

1. `docs/MIGRATION_PHASE_34_VALIDACION_PAGADETODO_WEBHOOKS_IDEMPOTENCIA.md`
2. `docs/MIGRATION_PHASE_35_GITHUB_SANDBOX_RELEASE.md`
3. `docs/PROJECT_STATUS_DIAGNOSTIC_2026-06-02.md`
4. `docs/MIGRATION_DEPLOY_AND_ROLLBACK_RUNBOOK.md`
5. `docs/MIGRATION_RELEASE_CHECKLIST.md`
6. `docs/STACK_AND_DEPENDENCIES.md`
7. `docs/ROUTES_AND_FLOW.md`
8. `docs/SECURITY_AND_RISKS.md`
9. `docs/MIGRATION_PHASE_33_ENTORNO_SANDBOX_E2E.md`
10. `docs/MIGRATION_PHASE_32_PRUEBAS_OWNERSHIP_CONTRATOS_API.md`
11. `docs/MIGRATION_PHASE_31_ESTABILIZACION_FUNCIONAL_ACCESOS_SEGURIDAD.md`
12. `docs/MIGRATION_MASTER_PLAN.md`
13. `docs/MIGRATION_DECISIONS_LOG.md`
14. `docs/MIGRATION_RISK_REGISTER.md`
15. `docs/MIGRATION_CHANGELOG.md`
16. `docs/PROJECT_STATUS_DIAGNOSTIC_2026-05-27.md`

## Estado actual consolidado

- Workspace vigente: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`
- Baseline origen: `C:\temp\centrodecobros_phase33_entorno_sandbox_e2e`
- Fase actual cerrada: `Fase 34 - Validacion Pagadetodo, webhooks e idempotencia`
- Preparacion GitHub/release sandbox: `Fase 35 - Preparacion GitHub y release sandbox paralelo`
- Ultimo corte documental: `2026-06-02`
- Dictamen vigente: `GO tecnico parcial fuerte para sandbox controlado; NO-GO para liberacion directa o cobro real`
- Laravel: `12.54.1`
- PHP observado en shell: `8.3.27`
- Vue: `3.5.30` puro
- `league/commonmark`: `2.8.2`
- Git: repo local inicializado y renombrado a rama `main`; primer push debe ir a GitHub privado despues de revisar `git status --short`.
- Tag recomendado despues del primer commit validado: `sandbox-phase34-v1.0.2`.
- Deploy paralelo objetivo: subdominio/vhost separado con PHP 8.3-FPM o contenedor; mismo MySQL solo con scheduler deshabilitado y `PAGADETODO_MOCK=true` hasta sandbox oficial.
- Assets compilados: no se versionan; se generan con `npm ci && npm run production` en CI/deploy.
- Contrato publico preservado:
  - `public/js/app.js`
  - `public/js/plantilla.js`
  - `public/css/plantilla.css`
  - `public/js/guest-public.js`
- `principal.blade.php` intacto en Fase 34

## Alcance cerrado en Fase 34

- copia aislada nueva creada desde Fase 33;
- `Service/EntregarPagoLiga`, `Service/EntregarPagoLigaToken` y `Service/EntregarPagoLector` con validacion minima, campos opcionales seguros e idempotencia por `idtransaccion + reference`;
- `Service/ConsultaClabe` con errores nulos corregidos;
- `Service/PagoClabe` con validacion minima e idempotencia por `transaccion`;
- `Service/CancelaClabe` con validacion minima e idempotencia por `transaccion + autorizacion`;
- pruebas `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`;
- Feature completo WAMP/SQLite en verde: 54 tests, 234 assertions;
- sandbox oficial Pagadetodo sigue bloqueado por falta de credenciales/URL no productivas.

## Alcance cerrado en preparacion GitHub/release sandbox

- repositorio Git local inicializado y renombrado a `main`;
- `.gitignore` ampliado para secretos, dependencias, logs, SQLite, dumps SQL, outputs, test-results, snippets accidentales y assets compilados;
- workflow GitHub agregado en `.github/workflows/sandbox-release-validation.yml`;
- decision definida: assets compilados fuera de Git y generados por CI/deploy;
- credenciales Pagadetodo y Pusher externalizadas a `.env`/`config/services.php` para no publicar secretos;
- runbook y checklist actualizados para branch/tag, vhost/subdominio, PHP 8.3, scheduler deshabilitado y `PAGADETODO_MOCK=true`.

## Alcance cerrado en Fase 33

- Feature tests autenticados recuperados usando PHP WAMP `8.3.0` con `pdo_sqlite`;
- SQLite persistente `storage/phase33_browser.sqlite` para browser real;
- browser real guest/admin/cliente validado con 0 errores de consola;
- `DashboardController` compatible con MySQL y SQLite para pruebas;
- smoke DB compatible con MySQL y SQLite;
- sandbox Pagadetodo simulado con mock controlado; sandbox oficial queda pendiente sin usar produccion.

## Alcance cerrado en Fase 32

- ownership por controlador para clientes, archivos, transacciones, respuestas, SPEI y domiciliacion;
- whitelists de criterios dinamicos para superficies criticas;
- exports criticos filtrados por propietario para rol cliente;
- mock Pagadetodo controlado por `services.pagadetodo.mock`;
- Feature tests de ownership/contratos escritos con schema aislado y skip explicito si falta `pdo_sqlite`;
- Unit tests sin DB para regresion de guards, whitelists, exports y mock;
- browser guest `/login`, `/` y `/main` validado; admin/cliente bloqueados por DB local.

## Alcance cerrado en Fase 31

- middleware `Administrador` con autorizacion real por rol;
- correccion de `CancelarDomiciliacion` sin `$e` indefinido;
- validaciones tempranas en APIs SPEI, lector, domiciliacion y cargo recurrente;
- filtros `ClientReference` SPEI corregidos;
- `UserController` sin seleccion de password hash y update de password condicional;
- pruebas unitarias/aisladas de regresion y documentacion de bloqueo DB.

## Alcance cerrado en Fase 30

- cierre del advisory de `league/commonmark`;
- revalidacion completa de build, backend y browser;
- auditoria especifica de integraciones hardcoded, dependencias de entorno y prerequisitos de deploy;
- emision de checklist final, runbook final y criterio de rollback;
- conclusion formal unica de liberacion.

## Condiciones abiertas que siguen vigentes

- secretos reales Pagadetodo/Pusher deben provisionarse y rotarse fuera de Git;
- realtime sigue pendiente de validacion end-to-end con credenciales aisladas;
- `npm audit` mantiene `29` vulnerabilidades (`5 low`, `16 moderate`, `8 high`);
- MySQL local sigue bloqueado para `centro_user`, pero Feature completos ya corren contra SQLite con PHP WAMP;
- sandbox oficial Pagadetodo sigue pendiente; solo se simulo contrato local;
- la lane `plantilla.*` sigue fuera de Vite por decision de alcance;
- Ubuntu 20.04 del servidor ya no debe tratarse como base ideal para instalar PHP 8.3 nativo; usar contenedor o migrar/actualizar host antes de coexistencia FPM limpia.

## Siguiente fase recomendada

Ejecutar primer commit/push a GitHub privado y validar workflow verde antes de desplegar sandbox paralelo. El detalle operativo esta en:

- `docs/MIGRATION_PHASE_35_GITHUB_SANDBOX_RELEASE.md`
- `docs/MIGRATION_PHASE_34_VALIDACION_PAGADETODO_WEBHOOKS_IDEMPOTENCIA.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
