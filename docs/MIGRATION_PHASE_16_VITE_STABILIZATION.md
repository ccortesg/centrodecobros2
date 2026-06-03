# Fase 16 - Estabilizacion post-Vite

Ultima actualizacion: 2026-03-15

## Resumen ejecutivo

Fase 16 cierra en `GO ejecutado`.

La copia aislada `C:\temp\centrodecobros_phase16_vite_stabilization` se creo correctamente antes de modificar archivos y fue la unica ruta mutada durante la fase. La baseline `C:\temp\centrodecobros_phase15_vite_impl` no se modifico.

El hardening confirma tres hechos:

1. el bridge estable de `public/js/app.js` ya es suficientemente robusto para mantenerse como baseline;
2. `plantilla.js` y `plantilla.css` deben seguir separados de Vite durante esta fase;
3. la siguiente fase ya no debe ser otra estabilizacion del mismo tipo, sino una integracion gradual y controlada de `plantilla.*` a un carril mas moderno.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase15_vite_impl`
- Ruta nueva creada: `C:\temp\centrodecobros_phase16_vite_stabilization`
- Backend de entrada confirmado:
  - PHP `8.2.24`
  - Laravel `12.54.1`
- Frontend de entrada confirmado:
  - Node `22.22.1`
  - npm `10.9.4`
  - Vue `3.5.30` puro
  - Vite `7.3.1` operativo para `resources/assets/js/app.js`
- Restricciones rectoras respetadas:
  - sin meter `plantilla.js` ni `plantilla.css` a Vite en esta fase;
  - sin romper `principal.blade.php`;
  - sin cambiar el contrato publico `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css`;
  - sin tocar contratos de negocio externos;
  - sin mezclar la fase con realtime end-to-end ni modernizacion masiva de vendor legacy.

## Confirmacion de copia aislada

- Ruta base usada: `C:\temp\centrodecobros_phase15_vite_impl`
- Ruta nueva creada: `C:\temp\centrodecobros_phase16_vite_stabilization`
- Confirmacion operativa:
  - la copia se creo correctamente antes de modificar archivos;
  - no se trabajo directamente sobre `C:\temp\centrodecobros_phase15_vite_impl`;
  - todos los cambios de esta fase quedaron unicamente en `C:\temp\centrodecobros_phase16_vite_stabilization`.

## Verificacion de entorno real

| Comando | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 12.54.1` |
| `composer show laravel/framework` | `v12.54.1` |
| `node -v` | `v22.22.1` |
| `npm -v` | `10.9.4` |

## Estrategia aplicada

1. Releer la documentacion rectora y la implementacion Phase 15.
2. Auditar el bridge `public/js/app.js`, el manifest de Vite, la lane legacy `plantilla.*` y las vistas Blade sensibles.
3. Endurecer solo lo necesario para build reproducible y fallos explicitos:
   - validacion del manifest y de los assets hasheados;
   - resolucion relativa del bridge desde `js/app.js`;
   - dependencia directa de `esbuild` para la lane legacy;
   - validacion de orden, existencia y no-vacio de `plantilla.*`;
   - smoke tests mas estrictos sobre el contrato de assets.
4. Revalidar build, comandos Laravel, PHPUnit y browser focalizado.
5. Emitir una recomendacion unica para la fase siguiente.

## Hallazgos sobre el bridge estable de `public/js/app.js`

### Como se genera

`npm run development` y `npm run production` siguen orquestados por `scripts/local/run_phase15_build.js`, que ejecuta:

1. `scripts/local/build_legacy_lane.js`
2. `vite build`
3. `scripts/local/build_vite_bridge.js`

El bridge final permanece en `public/js/app.js`.

### Como resuelve el bundle hasheado de `public/build`

`scripts/local/build_vite_bridge.js` lee `public/build/manifest.json`, toma la entrada `resources/assets/js/app.js`, recolecta el JS hasheado y el CSS asociado, y genera un script clasico que:

1. inyecta los CSS emitidos por Vite;
2. inserta el modulo JS hasheado;
3. preserva la ruta publica fija `public/js/app.js`.

En Phase 16 el bridge ya no fija rutas absolutas `/build/...`; ahora resuelve `../build/...` desde la propia URL de `js/app.js`. Eso evita fragilidad innecesaria frente a despliegues bajo subruta o diferencias entre rutas relativas y absolutas en Blade.

### Que pasa si no existe manifest o bundle esperado

Ahora el build falla de forma explicita si ocurre cualquiera de estos casos:

- falta `public/build/manifest.json`;
- el manifest no se puede parsear;
- falta la entrada `resources/assets/js/app.js`;
- falta el JS hasheado de Vite;
- falta algun CSS referenciado por el manifest.

El hardening quita el supuesto fragil de "manifest presente y correcto por defecto".

### Supuestos fragiles detectados y estado final

| Supuesto | Estado en Phase 15 | Estado en Phase 16 |
| --- | --- | --- |
| Rutas del bridge fijadas a `/build/...` | Fragil | Mitigado con resolucion relativa desde `js/app.js` |
| Manifest parseable por defecto | Fragil | Mitigado con error explicito |
| Assets hasheados siempre existentes | Fragil | Mitigado con validacion filesystem |
| `public/js/app.js` regenerado y no vacio | Implicito | Mitigado con verificacion final del orquestador |
| Alias de Vue con compilador | Sigue siendo requisito | Sigue abierto y documentado |

### Dictamen del bridge

`public/js/app.js` es suficientemente estable para mantenerse como baseline actual.

## Hallazgos sobre la lane legacy `plantilla.*`

### Como se genera

La lane legacy sigue fuera de Vite y ahora queda endurecida por `scripts/local/build_legacy_lane.js`.

- `public/css/plantilla.css` se concatena desde:
  1. `resources/assets/plantilla/css/font-awesome.min.css`
  2. `resources/assets/plantilla/css/simple-line-icons.min.css`
  3. `resources/assets/plantilla/css/style.css`
- `public/js/plantilla.js` se concatena desde:
  1. `resources/assets/plantilla/js/jquery.min.js`
  2. `resources/assets/plantilla/js/popper.min.js`
  3. `resources/assets/plantilla/js/bootstrap.min.js`
  4. `resources/assets/plantilla/js/Chart.min.js`
  5. `resources/assets/plantilla/js/pace.min.js`
  6. `resources/assets/plantilla/js/template.js`
  7. `resources/assets/plantilla/js/sweetalert2.all.js`

Ese orden sigue alineado con `webpack.mix.js`, que permanece como referencia historica de rollback.

### Endurecimientos aplicados

1. `esbuild` deja de depender de hoisting transitivo desde Vite y pasa a dependencia directa del proyecto.
2. Cada fuente legacy ahora se valida por existencia y contenido no vacio antes de concatenar.
3. El script registra el orden efectivo de CSS y JS en cada build.
4. Las salidas `public/js/plantilla.js` y `public/css/plantilla.css` se validan como archivos no vacios al cierre.
5. En `development`, los bundles quedan anotados con banners de origen para facilitar auditoria de orden.

### Dependencias globales auditadas

- `jQuery` / `window.$` / `window.jQuery`
- Bootstrap legacy sobre jQuery + Popper
- `Chart` global consumido por `Dashboard.vue`
- `swal` global consumido por multiples componentes Vue
- `template.js` para toggles y wiring del shell

### Riesgos de orden de carga

El orden sigue siendo estructuralmente sensible:

1. si `jquery.min.js` no carga antes de Bootstrap, el shell legacy rompe;
2. si `Chart.min.js` se mueve detras de `app.js`, `Dashboard.vue` pierde el global;
3. si `sweetalert2.all.js` no queda disponible al montar componentes Vue, los modulos con `swal(...)` fallan;
4. guest views como `verificar/contenido.blade.php` siguen dependiendo de jQuery inline y de `plantilla.js`.

### Dictamen de la lane legacy

`plantilla.js` y `plantilla.css` deben seguir separados temporalmente en Phase 16. La lane ya es reproducible y controlada, pero todavia no es razonable meterla completa a Vite en un solo corte.

## Archivos modificados

### Tooling y pruebas

- `package.json`
- `package-lock.json`
- `scripts/local/build_vite_bridge.js`
- `scripts/local/build_legacy_lane.js`
- `scripts/local/run_phase15_build.js`
- `tests/Feature/Smoke/AuthenticatedReadOnlySmokeTest.php`
- `tests/Feature/Smoke/PublicRoutesSmokeTest.php`
- `tests/Feature/Smoke/LegacyFunctionalAlignmentSmokeTest.php`

### Documentacion

- `docs/MIGRATION_PHASE_16_VITE_STABILIZATION.md`
- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_CHANGELOG.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
- `docs/README.md`

### Archivos sensibles auditados y preservados sin cambios

- `resources/views/principal.blade.php`
- `resources/views/auth/contenido.blade.php`
- `resources/views/transaccion/contenido.blade.php`
- `resources/views/verificar/contenido.blade.php`
- `resources/assets/js/app.js`
- `resources/assets/js/bootstrap.js`
- `webpack.mix.js`

## Validaciones ejecutadas

### Build y dependencias

- `npm install --save-dev esbuild@^0.27.4`
- `npm ci`
- `npm ls esbuild vite laravel-vite-plugin @vitejs/plugin-vue vue --depth=0`
- `npm run development`
- `npm run production`

Resultados:

- `npm ci` -> `OK`; se mantienen `7` vulnerabilidades heredadas (`5 low`, `2 moderate`) del ecosistema legacy restante.
- `npm run development` -> `OK`
- `npm run production` -> `OK`
- artefactos de produccion confirmados:
  - `public/js/app.js` -> `1859` bytes
  - `public/js/plantilla.js` -> `402913` bytes
  - `public/css/plantilla.css` -> `246986` bytes
  - `public/build/manifest.json` -> `211` bytes

### Backend y no regresion

- `php artisan route:list` -> `OK`, `100` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 123 assertions)`

### Browser semiautomatico focalizado

Se valido sobre `http://127.0.0.1:8016` sirviendo la copia Phase 16. La sesion autenticada se genero localmente dentro de `storage/framework/sessions` de esta misma copia y se inyecto como cookie `laravel_session` cifrada con el prefijo real de Laravel.

Rutas y modulos auditados:

1. `/login`
2. `/url`
3. `/main`
4. `Roles`
5. `Reporte Ingresos SPEI`
6. `Reporte Ingresos por Cargos Recurrentes`
7. shell autenticado

Resultado browser final:

- `0` errores y `0` warnings en consola en las sesiones finales auditadas.
- `/login` -> `200`, limpio.
- `/url` -> `200`, limpio.
- `/main` -> `200`, shell montado con `notification/get` y `dashboard` en `200`.
- `Roles` -> `GET /rol?page=1&buscar=&criterio=nombre` en `200`.
- `Reporte Ingresos SPEI` -> `GET /cliente/selectCliente`, `GET /pagospei/reportePagoSpei?...` y `GET /pagospei/exportarReporteSpei?...` en `200`.
- `Reporte Ingresos por Cargos Recurrentes` -> `GET /cliente/selectCliente`, `GET /transaccionDom/reporteTransaccionesDom?...` y `GET /transaccionDom/exportarTransacciones?...` en `200`.
- globals confirmados en shell autenticado:
  - `Chart`
  - `swal`
  - `jQuery`
  - `axios`
  - `Echo`
  - `Pusher`

## Estado final

Estado B: `GO ejecutado`

Confirmaciones finales:

1. el bridge de `app.js` queda suficientemente estable para mantenerse como baseline;
2. `plantilla.js` y `plantilla.css` deben seguir separados temporalmente en esta fase;
3. `principal.blade.php` sigue intacto y las vistas guest siguen resolviendo el shell;
4. el contrato publico de assets sigue intacto:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
5. Laravel `12.54.1` y Vue `3.5.30` puro siguen estables.

## Riesgos residuales

1. el shell autenticado sigue dependiendo del build de Vue con compilador porque el template raiz vive en Blade;
2. `plantilla.*` sigue cargando globals legacy y vistas guest con jQuery inline;
3. `npm ci` sigue reportando `7` vulnerabilidades heredadas del ecosistema legacy restante;
4. `watch` / `hot` siguen sin ser criterio rector de aceptacion para esta baseline;
5. realtime (`Echo` / `Pusher`) sigue fuera de alcance end-to-end y no cambia su estado rector.

## Rollback detallado

1. Detener cualquier servidor local o sesion browser abierta de `C:\temp\centrodecobros_phase16_vite_stabilization`.
2. Descartar por completo `C:\temp\centrodecobros_phase16_vite_stabilization`.
3. Volver a usar `C:\temp\centrodecobros_phase15_vite_impl` como baseline valida anterior.
4. Si se requiere rehacer la fase, volver a clonar desde `C:\temp\centrodecobros_phase15_vite_impl` hacia una nueva copia aislada.
5. No copiar archivos de regreso a la baseline de Fase 15.

## Recomendacion unica para la fase siguiente

Abrir `Fase 17 - integracion gradual de plantilla.* a un carril mas moderno`.

Motivo rector:

1. el bridge Vite de `app.js` ya quedo estabilizado;
2. la lane legacy `plantilla.*` ya es reproducible y observable;
3. el siguiente ROI real ya no esta en otra fase de estabilizacion igual, sino en empezar a desmontar gradualmente la dependencia de globals legacy sin romper el contrato publico.
