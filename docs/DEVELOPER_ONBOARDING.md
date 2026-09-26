# Onboarding de desarrolladores

Ultima actualizacion: 2026-08-06

## Lectura inicial obligatoria

1. `AGENTS.md`
2. `docs/CODEX_PROJECT_HANDOFF.md`
3. `docs/PROJECT_OPERATING_MODEL.md`
4. `docs/README.md`
5. `docs/AI_AGENT_GUIDE.md`
6. `docs/ROUTES_AND_FLOW.md`
7. `docs/ARCHITECTURE.md`
8. `docs/SECURITY_AND_RISKS.md`
9. `docs/INTEGRATIONS.md`
10. `docs/MODULES/*.md`

Los documentos `MIGRATION_*` sirven como bitacora historica y evidencia de decisiones; no sustituyen el modelo operativo vigente.

## Primer chequeo tecnico

```bash
git status --short
php artisan --version
php artisan route:list
php artisan schedule:list
php vendor/bin/phpunit --testsuite Unit --do-not-cache-result
```

Si la tarea toca frontend:

```bash
node -v
npm -v
npm ci
npm run production
```

Si la tarea toca contratos Pagadetodo, ownership o webhooks, usar exclusivamente la base MySQL desechable configurada en `.env.testing`:

```bash
cd /home/ccortesg/workspace/centrodecobros2
php vendor/bin/phpunit --testsuite Feature --do-not-cache-result
```

No usar `--parallel`: las Feature reconstruyen la misma base `centrodecobros_testing`. El arnes aborta fuera de `APP_ENV=testing` o si MySQL selecciona otra base.

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

1. Trabajar en `/home/ccortesg/workspace/centrodecobros2`, no en copias nuevas ni en el origen Windows preservado.
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
6. Ejecutar prueba aislada o Feature MySQL en `centrodecobros_testing`.
7. Documentar hallazgo si cambia el estado operativo.
