# Guia para agentes de IA

Ultima actualizacion: 2026-06-03

## Regla operativa obligatoria

Trabajar siempre en:

`C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

No crear copias nuevas para fases, fixes, documentacion o actualizaciones futuras, salvo instruccion explicita posterior del propietario.

## Orden de lectura recomendado

1. `docs/PROJECT_OPERATING_MODEL.md`
2. `docs/README.md`
3. `docs/ROUTES_AND_FLOW.md`
4. `docs/ARCHITECTURE.md`
5. `docs/ENVIRONMENT_AND_OPERATION.md`
6. `docs/SECURITY_AND_RISKS.md`
7. `docs/INTEGRATIONS.md`
8. `docs/MODULES/*.md`
9. `routes/web.php` y `routes/api.php`
10. Controlador y componente Vue del modulo que se vaya a tocar.

## Fuentes de verdad

- Rutas actuales: `php artisan route:list`, `routes/web.php`, `routes/api.php`.
- Reglas ejecutables: controladores, especialmente `TransaccionController`, `TransaccionDomController`, `RespuestaController`, `ClienteController` y controladores SPEI.
- UI autenticada: `resources/views/contenido/contenido.blade.php`, `resources/assets/js/app.js`, componentes en `resources/assets/js/components`.
- Configuracion externa: `.env` en servidor, `.env.example`, `config/services.php`, `config/broadcasting.php`.
- Esquema operativo: MySQL productivo o dump autorizado fuera de Git. No asumir que `database/migrations` reconstruye el sistema real.
- Pruebas Feature: SQLite preparado por `scripts/local/prepare_phase33_browser_sqlite.php` y soporte bajo `tests/Support`.

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
- No publicar `.env`, dumps SQL, SQLite, logs, `vendor/`, `node_modules/`, outputs ni assets compilados.
- No tocar `principal.blade.php` ni contrato publico de assets sin justificacion y validacion.
- No renombrar rutas API legacy ni agregar prefijo `/api` sin plan de compatibilidad.
- Si una relacion de DB se infiere desde codigo, documentarla como inferida.

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
