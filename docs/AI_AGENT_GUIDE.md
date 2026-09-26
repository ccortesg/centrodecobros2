# Guia para agentes de IA

Ultima actualizacion: 2026-08-04

## Regla operativa obligatoria

Trabajar siempre en:

`/home/ccortesg/workspace/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

La ruta `/mnt/c/temp/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia` es el origen preservado de la migracion a WSL. No crear copias nuevas para fases, fixes, documentacion o actualizaciones futuras, salvo instruccion explicita posterior del propietario.

## Orden de lectura recomendado

1. `AGENTS.md`
2. `docs/CODEX_PROJECT_HANDOFF.md`
3. `docs/PROJECT_OPERATING_MODEL.md`
4. `docs/README.md`
5. `docs/ROUTES_AND_FLOW.md`
6. `docs/ARCHITECTURE.md`
7. `docs/ENVIRONMENT_AND_OPERATION.md`
8. `docs/SECURITY_AND_RISKS.md`
9. `docs/INTEGRATIONS.md`
10. `docs/MODULES/*.md`
11. `routes/web.php` y `routes/api.php`
12. Controlador y componente Vue del modulo que se vaya a tocar.

## Fuentes de verdad

- Rutas actuales: `php artisan route:list`, `routes/web.php`, `routes/api.php`.
- Reglas ejecutables: controladores, especialmente `TransaccionController`, `TransaccionDomController`, `RespuestaController`, `ClienteController` y controladores SPEI.
- UI autenticada: `resources/views/contenido/contenido.blade.php`, `resources/assets/js/app.js`, componentes en `resources/assets/js/components`.
- Configuracion externa: `.env` en servidor, `.env.example`, `config/services.php`, `config/broadcasting.php`.
- Esquema operativo: MySQL productivo o dump autorizado fuera de Git. No asumir que `database/migrations` reconstruye el sistema real.
- Pruebas Feature: SQLite preparado por `scripts/local/prepare_phase33_browser_sqlite.php` y soporte bajo `tests/Support`.
- Inventario de rutas vigente: `php artisan route:list` muestra 121 rutas en el corte 2026-08-04.
- Addendum 2026-07-03: los modulos de auditoria de integraciones agregan rutas `integraciones/*` y el comando manual `audit:purge`; confirmar el inventario vigente con `route:list` en cada tarea.
- Diagnostico vigente: `docs/CODEX_PROJECT_HANDOFF.md`. Los diagnosticos fechados son historicos.

## Flujo de trabajo para cualquier cambio

1. Ejecutar `git status --short`.
2. Identificar ruta, controlador, metodo, modelo/tabla y componente Vue.
3. Leer la ficha del modulo en `docs/MODULES`.
4. Revisar restricciones de rol y ownership.
5. Confirmar si toca integraciones externas, scheduler, assets publicos o DB.
6. Implementar el cambio mas pequeno posible.
7. Ejecutar validaciones proporcionales al riesgo.
8. Actualizar documentacion si cambia comportamiento, operacion, rutas o riesgos.
9. Reportar comandos ejecutados y resultados.

## Puntos de cuidado

- No ejecutar migraciones sobre DB productiva.
- No activar scheduler ni cron sin orden explicita.
- No usar credenciales productivas de Pagadetodo en pruebas.
- No intentar llamadas reales Pagadetodo desde ambiente local: el proveedor restringe por IP address de origen. Usar `PAGADETODO_MOCK=true` local y validar llamadas reales solo desde servidor/IP autorizado.
- No publicar `.env`, dumps SQL, SQLite, logs, `vendor/`, `node_modules/`, outputs ni assets compilados.
- No tocar `principal.blade.php` ni contrato publico de assets sin justificacion y validacion.
- No renombrar rutas API legacy ni agregar prefijo `/api` sin plan de compatibilidad.
- No ampliar campos auditados ni exportados sin revisar `App\Services\AuditSanitizer`; headers/payloads deben quedar sanitizados antes de persistirse.
- Si una relacion de DB se infiere desde codigo, documentarla como inferida.
- Para webhooks configurables, nunca sanitizar ni reserializar el cuerpo real ya guardado en `webhook_deliveries.raw_body`; HMAC depende de esos bytes exactos.
- `shadow` debe conservar callbacks legacy y no hacer una segunda llamada HTTP. `active` es el unico modo que reemplaza legacy.
- No habilitar `WEBHOOK_NOTIFICATIONS_ENABLED=true` ni cambiar un cliente a `active` sin tablas migradas y worker de cola persistente.
- No imprimir, exportar ni registrar `webhook_user_settings.hmac_secret`; solo se muestra una vez al crear/rotar.
- No agregar allowlist/DNS/rangos privados a URLs sin nueva decision del propietario; la regla vigente es formato valido + HTTPS.
- El posible JOIN duplicado de `ejecutarCron` esta fuera del alcance de webhooks y debe tratarse como pendiente financiero separado.
- La deduplicacion en `RespuestaController::storePublic()` y `storeLectorPublic()` esta comentada por decision del propietario. No reactivarla como cambio incidental.
- El endpoint API `CargoDomiciliacion` requiere revisar ownership por `idusuario/productivo`; tratarlo como P0 antes de ampliar esa integracion.

## Checklist previo a cambios funcionales

- [ ] Ruta localizada.
- [ ] Controlador/metodo identificado.
- [ ] Componente Vue localizado si hay UI.
- [ ] Tablas/columnas verificadas desde uso real o dump autorizado.
- [ ] Validaciones y middleware revisados.
- [ ] Impacto por rol revisado.
- [ ] Impacto en reportes/exportaciones revisado.
- [ ] Impacto en callbacks/webhooks/scheduler revisado.
- [ ] Plan de pruebas definido.

## Validaciones base

```bash
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit --testsuite Unit
git diff --check docs
```

Si la tarea toca contratos Pagadetodo o ownership, ejecutar tambien Feature con SQLite/WAMP y `PAGADETODO_MOCK=true`.
Si la tarea toca auditoria de integraciones, ejecutar `tests/Unit/AuditSanitizerTest.php` y `tests/Feature/IntegrationAuditFeatureTest.php`.
Si toca notificaciones webhook, ejecutar tambien `tests/Unit/WebhookSecurityContractTest.php` y `tests/Feature/WebhookNotificationFeatureTest.php` con WAMP PHP 8.3/SQLite.

## Nota de validacion 2026-08-04

- El PHP CLI WSL 8.3.31 no trae `pdo_sqlite`; usar un runtime de testing autorizado con esa extension. WAMP queda como carril historico, no como workspace canonico.
- `tests\Feature\Phase32`, `tests\Feature\Phase34` y `tests\Feature\UX` pasaron con WAMP PHP 8.3 y SQLite aislado: 52 tests, 170 assertions.
- `vendor\bin\phpunit --testsuite Feature` completo fallo en este entorno por smoke tests que intentan MySQL local con `centro_user@localhost`. No corregir eso tocando `.env`, credenciales ni migraciones productivas; abrir un carril de runner/dataset de testing o adaptar los smoke a SQLite controlado.
- `npm run production` solo debe ejecutarse si hay cambio frontend o si la tarea pide validar build; no versionar los assets generados.
- Auditoria actual: lint 169 PHP OK; Unit 28 tests/171 assertions OK; 121 rutas y tres tareas scheduler. Feature/build/browser no se ejecutaron en este corte.
