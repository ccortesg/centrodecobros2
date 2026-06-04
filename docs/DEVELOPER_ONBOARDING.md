# Onboarding de desarrolladores

Ultima actualizacion: 2026-06-03

## Lectura inicial obligatoria

1. `docs/PROJECT_OPERATING_MODEL.md`
2. `docs/README.md`
3. `docs/AI_AGENT_GUIDE.md`
4. `docs/ROUTES_AND_FLOW.md`
5. `docs/ARCHITECTURE.md`
6. `docs/SECURITY_AND_RISKS.md`
7. `docs/INTEGRATIONS.md`
8. `docs/MODULES/*.md`

Los documentos `MIGRATION_*` sirven como bitacora historica y evidencia de decisiones; no sustituyen el modelo operativo vigente.

## Primer chequeo tecnico

```bash
git status --short
php artisan --version
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit --testsuite Unit
```

Si la tarea toca frontend:

```powershell
cmd /c "node -v && npm -v"
cmd /c "npm ci"
cmd /c "npm run production"
```

Si la tarea toca contratos Pagadetodo, ownership o webhooks:

```powershell
cd /D C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia
set APP_ENV=testing&& set DB_CONNECTION=sqlite&& set DB_DATABASE=C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& set PAGADETODO_MOCK=true&& C:\wamp64\bin\php\php8.3.0\php.exe scripts\local\prepare_phase33_browser_sqlite.php C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia\storage\phase34_validation.sqlite&& C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit --testsuite Feature
```

## Donde vive la logica real

- `app/Http/Controllers/TransaccionController.php`
- `app/Http/Controllers/TransaccionDomController.php`
- `app/Http/Controllers/RespuestaController.php`
- `app/Http/Controllers/ClienteController.php`
- `app/Http/Controllers/UserController.php`
- `resources/assets/js/components/*.vue`
- `resources/views/contenido/contenido.blade.php`
- `routes/web.php`
- `routes/api.php`

No esperar boundaries limpios por dominio. Gran parte de las reglas vive en controladores y en nombres legacy de campos.

## Reglas para cambios

1. Trabajar en la carpeta actual, no en copias nuevas.
2. Mantener cambios pequenos y rastreables.
3. No ejecutar migraciones productivas.
4. No tocar scheduler sin orden explicita.
5. No publicar secretos ni artefactos locales.
6. No tocar `principal.blade.php` ni contrato publico de assets sin necesidad justificada.
7. Si cambia el comportamiento visible, actualizar la ficha del modulo.

## Riesgos comunes

- Asumir que `database/migrations` equivale al schema real.
- Romper rutas legacy sin prefijo `/api`.
- Cambiar payloads externos sin sandbox oficial.
- Mezclar remediacion completa de `npm audit` con tareas funcionales.
- Validar solo con rol admin y olvidar ownership de rol cliente.
- Activar scheduler duplicado contra la misma DB.
- Versionar assets compilados o secretos.

## Diagnostico rapido de fallas

1. Revisar request real del componente Vue.
2. Confirmar ruta exacta en `route:list`.
3. Revisar controlador y query real.
4. Revisar `storage/logs/laravel.log`.
5. Confirmar rol, ownership y datos de prueba.
6. Ejecutar prueba aislada o Feature SQLite.
7. Documentar hallazgo si cambia el estado operativo.
