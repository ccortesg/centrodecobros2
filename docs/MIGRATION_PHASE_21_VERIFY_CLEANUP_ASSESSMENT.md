# Fase 21 - Verify Cleanup Assessment

Fecha: 2026-03-24
Estado final: `GO ejecutado`

## Resumen ejecutivo

Se ejecuto una auditoria exhaustiva del flujo heredado `verify/SMS` en una copia aislada nueva. La evidencia confirmo que el circuito estaba encapsulado en su propio perimetro (`routes/web.php` + `TransaccionController` + `resources/views/verificar/*`), sin callers vivos en blades, JS, tests ni rutas funcionales actuales fuera de ese perimetro. Adicionalmente, el historial documental ya registraba que `GET /verify/1` fallaba con `500` por un mismatch preexistente entre `showVerifyForm` y `verify.blade.php`.

Con esa evidencia se autorizo la eliminacion minima y segura del flujo:

- rutas `verify`, `verifySMS`, `sendSMS`;
- metodos `showVerifyForm`, `verifySMS`, `sendSMS`;
- vistas `resources/views/verificar/contenido.blade.php` y `resources/views/verificar/verify.blade.php`.

El contrato de assets `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css` se preservo intacto. La bateria de validacion backend, build y browser termino en verde.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase20_shell_navigation_modern`
- Ruta nueva creada: `C:\temp\centrodecobros_phase21_verify_cleanup_assessment`
- La copia aislada se creo correctamente antes de modificar archivos.
- Backend asumido y confirmado: Laravel `12.54.1` con PHP `8.2.24`
- Frontend asumido y confirmado: Vue `3.5.30` puro, Node `20.20.0`, npm `10.8.2`

## Inventario de referencias encontradas

### Referencias ejecutables antes del recorte

- [routes/web.php](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\routes\web.php): `GET /verify/{id}`, `POST /verifySMS`, `POST /sendSMS`
- [app/Http/Controllers/TransaccionController.php](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\app\Http\Controllers\TransaccionController.php): `showVerifyForm`, `sendSMS`, `verifySMS`
- [resources/views/verificar/contenido.blade.php](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\resources\views\verificar\contenido.blade.php): AJAX inline a `/sendSMS`
- [resources/views/verificar/verify.blade.php](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\resources\views\verificar\verify.blade.php): form `action="{{ route('verifySMS') }}"`

### Referencias internas y evidencia de no uso real

- Busqueda recursiva en `app/`, `routes/`, `resources/`, `tests/` y `scripts/local`:
  - sin callers adicionales a `showVerifyForm`, `verifySMS`, `sendSMS`
  - sin `route('verify')`, `route('verifySMS')` o `route('sendSMS')` fuera del propio flujo
  - sin JS/Vue/AJAX actuales fuera de `resources/views/verificar/*`
  - sin tests apuntando a esas rutas o metodos
- `php artisan route:list` al inicio confirmo que las 3 rutas aun estaban publicadas.
- La auditoria de `otp` / `isVerified` no encontro consumidores fuera de los metodos retirados.
- No se encontraron request objects, jobs, providers o middleware dedicados a este flujo.

### Evidencia historica/documental relevante

- [docs/MIGRATION_FEH1_FRONTEND_HARDENING_PLAN.md](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\docs\MIGRATION_FEH1_FRONTEND_HARDENING_PLAN.md) ya registraba que `/verify/1` devolvia `500`.
- [docs/MIGRATION_SMOKE_TESTS.md](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\docs\MIGRATION_SMOKE_TESTS.md) repetia el mismatch preexistente `transaccion` vs `$participante`.
- La documentacion viva de rutas/integraciones aun lo seguia describiendo como activo y fue actualizada en esta fase.

## Evidencia tecnica de uso/no uso

1. `GET /verify/{id}` no estaba enlazado desde otros blades ni desde JS vivo actual.
2. `POST /verifySMS` y `POST /sendSMS` solo eran invocados por el JS inline de una vista ya aislada del resto de la plataforma.
3. `showVerifyForm` entregaba `view('verificar.verify')->with('transaccion', ...)`, pero la vista consumia `$participante[...]`; esa desalineacion dejaba el flujo roto antes de cualquier validacion OTP real.
4. La plataforma funcional validada en browser para esta fase (`/login`, `/main`, `/url`, `Roles`, `Clientes`, `Usuarios`, `Reporte Ingresos SPEI`, `Reporte Ingresos por Cargos Recurrentes`) no depende de ese circuito.

Clasificacion final del flujo:

- `muerto y eliminable`

## Dictamen GO / NO-GO para eliminacion total

Dictamen: `GO`

Justificacion:

- no habia referencias vivas reales fuera del propio circuito heredado;
- el flujo ya estaba roto funcionalmente;
- la eliminacion no tocaba contratos externos de negocio ni assets publicos;
- la validacion completa posterior no mostro regresiones en rutas, build ni browser.

## Cambios aplicados

### Codigo

- Eliminadas las rutas verify/SMS de [routes/web.php](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\routes\web.php)
- Eliminados los metodos `showVerifyForm`, `sendSMS` y `verifySMS` de [app/Http/Controllers/TransaccionController.php](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\app\Http\Controllers\TransaccionController.php)
- Eliminadas las vistas:
  - [resources/views/verificar/contenido.blade.php](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\resources\views\verificar\contenido.blade.php)
  - [resources/views/verificar/verify.blade.php](C:\temp\centrodecobros_phase21_verify_cleanup_assessment\resources\views\verificar\verify.blade.php)

### Documentacion

- Actualizados documentos rectores y de referencia para reflejar que verify/SMS ya no es flujo vivo del sistema.

## Validaciones ejecutadas

### Entorno

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `node -v` -> `v20.20.0`
- `npm -v` -> `10.8.2`

### Backend y build

- `php artisan route:list` -> `OK`; pasa de `100` a `97` rutas y ya no publica `verify`, `verifySMS`, `sendSMS`
- `php artisan schedule:list` -> `OK`; `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 128 assertions)`
- `npm ci` -> `OK`; persisten `7` vulnerabilidades heredadas del lane legacy
- `npm run development` -> `OK`
- `npm run production` -> `OK`

### Browser semiautomatico focalizado

Servidor local: `http://127.0.0.1:8021`

Rutas y modulos auditados:

1. `/login`
2. `/url`
3. `/main#roles`
4. `Roles`
5. `Clientes`
6. `Usuarios`
7. `Reporte Ingresos SPEI`
8. `Reporte Ingresos por Cargos Recurrentes`

Resultados:

- `/login` -> `0` errores y `0` warnings
- `/url` -> `0` errores y `0` warnings
- `/main#roles` -> resuelve a `/main` sin hash residual
- shell autenticado -> `0` errores y `0` warnings
- `Roles` -> `GET /rol?page=1&buscar=&criterio=nombre` en `200`
- `Clientes` -> `GET /cliente?page=1&buscar=&criterio=nombre&offset=10` en `200`
- `Usuarios` -> `GET /user?page=1&buscar=&criterio=nombre` en `200`
- `Reporte Ingresos SPEI` -> `GET /cliente/selectCliente` y `GET /pagospei/reportePagoSpei?idcliente=0&fechaInicio=null&fechaFin=null` en `200`
- `Reporte Ingresos por Cargos Recurrentes` -> `GET /cliente/selectCliente` y `GET /transaccionDom/reporteTransaccionesDom?idcliente=0&fechaInicio=null&fechaFin=null` en `200`

## Resultado final

- El flujo `verify/SMS` estaba realmente muerto en la plataforma actual.
- La eliminacion total del circuito fue segura y minima.
- El riesgo de arrastrar rutas publicas rotas con OTP/SMS legacy se redujo.
- El contrato de assets quedo preservado.

## Riesgos residuales

- `telesign/telesign` sigue instalado en Composer; esta fase no abrio una limpieza de dependencias ni de imports residuales fuera del circuito verify/SMS.
- La siguiente deuda rectora sigue en `template.js` guest/legacy y no en verify/SMS.
- Continuan abiertos los riesgos generales de credenciales hardcoded, realtime no aislado y lane legacy fuera de Vite.

## Rollback detallado

1. Descartar por completo `C:\temp\centrodecobros_phase21_verify_cleanup_assessment`.
2. Mantener intacta la baseline anterior `C:\temp\centrodecobros_phase20_shell_navigation_modern`.
3. Si se requiere rollback granular dentro de la copia Phase 21, restaurar:
   - las 3 rutas en `routes/web.php`
   - los 3 metodos en `app/Http/Controllers/TransaccionController.php`
   - las 2 vistas de `resources/views/verificar/`
4. Repetir `php artisan route:list`, `php vendor/bin/phpunit`, `npm run development`, `npm run production` y la bateria browser.
