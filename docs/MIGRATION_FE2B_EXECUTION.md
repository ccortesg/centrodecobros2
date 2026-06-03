# FE-2B - Alineacion funcional legacy

Ultima actualizacion: 2026-03-13

## Estado final

Resultado: `GO con salvedades`

FE-2B queda ejecutada en la copia aislada `C:\temp\centrodecobros_phase6_fe2_build` sobre el build FE-2 ya aprobado, sin tocar `resources/views/principal.blade.php`, sin cambiar nombres de assets y sin mover todavia el runtime Vue `2.5.13`.

## Objetivo logrado

Se corrigio la desalineacion funcional viva documentada para:

1. Roles (`Role.vue` / `Rol.vue`)
2. Exportacion filtrada de `ReporteSpei.vue`
3. Exportacion filtrada de `ReporteCargosRecurrentes.vue`
4. Flujo guest `/url`

## Cambios aplicados

### Roles

1. `resources/assets/js/components/Role.vue` deja de ser una variante divergente y ahora apunta al componente canonico `Rol.vue`.
2. `resources/assets/js/app.js` registra tambien el tag `role` como alias compatible.
3. `routes/web.php` expone `GET /role` como alias compatible de `RolController@index`.

Resultado:

- `Rol.vue` sigue siendo el componente canonico;
- se preserva compatibilidad para wiring legacy que espere `role` o `/role`;
- la funcionalidad deja de depender de dos implementaciones divergentes.

### Reporte SPEI

1. Se agrego `GET /pagospei/exportarReporteSpei`.
2. `PagoSpeiController` ahora comparte el mismo criterio de filtro entre listado y exportacion.
3. Se agrego `app/Exports/ReportePagoSpeiExport.php` para descargar solo el reporte filtrado que la pantalla ya consume.

Resultado:

- `ReporteSpei.vue` ya no apunta a un endpoint inexistente;
- la exportacion respeta `idcliente`, `fechaInicio` y `fechaFin`;
- `GET /pagospei/exportar` se conserva intacto como exportacion generica.

### Reporte de cargos recurrentes

1. Se agrego `GET /transaccionDom/exportarTransacciones`.
2. `TransaccionDomController` ahora comparte el mismo criterio de filtro entre listado y exportacion.
3. Se agrego `app/Exports/ReporteTransaccionDomExport.php` para descargar solo el reporte filtrado.

Resultado:

- `ReporteCargosRecurrentes.vue` ya no apunta a un endpoint inexistente;
- la exportacion respeta `idcliente`, `fechaInicio` y `fechaFin`;
- `GET /transaccionDom/exportar` se conserva intacto como exportacion generica.

### Flujo guest `/url`

1. Se implemento `TransaccionController@showURL`.
2. Se implemento `TransaccionController@openPublic`.
3. `openPublic` valida que la URL sea valida y que use `http` o `https`.
4. `resources/views/transaccion/url.blade.php` deja de imprimir el `src` del `iframe` sin escape Blade.

Resultado:

- `GET /url` renderiza correctamente la vista publica;
- `POST /url` vuelve a operar con el contrato actual del formulario;
- el `iframe` se carga solo con URLs permitidas.

## Archivos tocados

### Codigo

1. `resources/assets/js/app.js`
2. `resources/assets/js/components/Role.vue`
3. `routes/web.php`
4. `app/Http/Controllers/TransaccionController.php`
5. `app/Http/Controllers/PagoSpeiController.php`
6. `app/Http/Controllers/TransaccionDomController.php`
7. `app/Exports/ReportePagoSpeiExport.php`
8. `app/Exports/ReporteTransaccionDomExport.php`
9. `resources/views/transaccion/url.blade.php`
10. `scripts/local/check_route_alignment.ps1`
11. `tests/Feature/Smoke/LegacyFunctionalAlignmentSmokeTest.php`

### Documentacion

1. `docs/MIGRATION_FE2B_EXECUTION.md`
2. `docs/MIGRATION_MASTER_PLAN.md`
3. `docs/MIGRATION_CHANGELOG.md`
4. `docs/MIGRATION_RISK_REGISTER.md`
5. `docs/MIGRATION_DECISIONS_LOG.md`
6. `docs/README.md`

## Validacion ejecutada

| Validacion | Resultado |
| --- | --- |
| `php artisan route:list` | OK; 100 rutas |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` | OK; sin gaps restantes |
| `vendor\bin\phpunit tests/Feature/Smoke/LegacyFunctionalAlignmentSmokeTest.php` | `OK (5 tests, 23 assertions)` |
| `vendor\bin\phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php` | `OK (19 tests, 104 assertions)` |
| `npm run dev` | OK |
| Playwright sobre `http://127.0.0.1:8001/url` | OK; la vista carga, acepta `https://example.com` y el `iframe` se renderiza |

## Salvedades residuales

1. La validacion manual en navegador para pantallas autenticadas de Roles y reportes no se automatizo por falta de credenciales reales en la sesion del navegador.
2. La cobertura de esas superficies protegidas queda respaldada por smoke tests HTTP y por `route:list`, no por navegacion manual autenticada.
3. Las exportaciones genericas existentes se preservaron sin cambio; FE-2B solo cerro el contrato filtrado que esperaba la UI.

## Resultado de salida

FE-2B queda en `GO con salvedades`.

Se cumple el objetivo tecnico de la fase:

1. la funcionalidad viva legacy deja de tener wiring roto;
2. el contrato de assets y Blade se mantiene intacto;
3. FE-3 puede abrirse como precheck separado, sin seguir arrastrando estos gaps funcionales.

## Siguiente accion recomendada

1. Abrir el precheck de FE-3 para evaluar el salto Vue `2.5.13 -> 2.7.x`.
2. Mantener FE-2B y FE-3 separados del hardening de dependencias frontend legacy.
3. Si se requiere homologacion funcional completa, ejecutar una validacion manual autenticada adicional sobre Roles, `ReporteSpei` y `ReporteCargosRecurrentes`.
