# Fase 17 - Correccion menu fix del shell autenticado

Ultima actualizacion: 2026-03-15

## Resumen ejecutivo

Fase 17 cierra en `GO ejecutado`.

La copia aislada `C:\temp\centrodecobros_phase17_menu_fix` se creo correctamente antes de modificar archivos y fue la unica ruta mutada durante la fase. La baseline `C:\temp\centrodecobros_phase16_vite_stabilization` no se modifico.

El resultado rector de la fase es:

1. el menu lateral del shell autenticado vuelve a abrir y cerrar submenus en browser;
2. el contrato de assets `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css` se preserva intacto;
3. `principal.blade.php` queda intacto;
4. Laravel `12.54.1`, Vue `3.5.30` puro y la lane legacy separada siguen operativos.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase16_vite_stabilization`
- Ruta nueva creada: `C:\temp\centrodecobros_phase17_menu_fix`
- Backend de entrada confirmado:
  - PHP `8.2.24`
  - Laravel `12.54.1`
- Frontend de entrada confirmado:
  - Node `20.20.0`
  - npm `10.8.2`
  - Vue `3.5.30` puro
  - Vite incremental estable para `resources/assets/js/app.js`

## Confirmacion de copia aislada

- la copia se creo correctamente antes de modificar archivos;
- no se trabajo directamente sobre `C:\temp\centrodecobros_phase16_vite_stabilization`;
- todos los cambios de esta fase quedaron unicamente en `C:\temp\centrodecobros_phase17_menu_fix`.

## Verificacion de entorno real

| Comando | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 12.54.1` |
| `composer show laravel/framework` | `v12.54.1` |
| `node -v` | `v20.20.0` |
| `npm -v` | `10.8.2` |

## Diagnostico de causa raiz

### Script responsable del sidebar

El wiring real de apertura/cierre del sidebar y los submenus sigue en `resources/assets/plantilla/js/template.js`, dentro de la lane legacy que genera `public/js/plantilla.js`.

Dependencias auditadas:

1. `template.js` depende de `jQuery` global.
2. `bootstrap.min.js` tambien vive en la misma lane legacy.
3. no se detecto `metisMenu`; el comportamiento es propio de `template.js`.

### Orden de carga real auditado

En `resources/views/principal.blade.php` el shell sigue cargando:

1. `js/app.js`
2. `js/plantilla.js`

Pero `public/js/app.js` ya no es un bundle clasico. Es un bridge que inserta un `script type="module"` hacia `public/build/assets/app-*.js`. Eso hace que:

1. `template.js` se ejecute como script clasico durante el parseo del HTML;
2. el modulo Vue de Vite monte despues, de forma asincrona;
3. el root Vue remonte `#app` y reemplace el DOM sobre el que `template.js` habia tomado referencias y bind directo.

### Evidencia de la regresion

Hallazgos reproducidos antes del fix:

1. click sobre un submenu como `Catálogos` no agregaba la clase `open`; `openCount` seguia en `0`.
2. `window.$` podia quedar reemplazado por el jQuery importado en `resources/assets/js/app.js`, perdiendo propiedades legacy como `$.navigation`.
3. una reinicializacion manual sobre el `nav` actual del browser restauraba inmediatamente el `open`, confirmando que el problema no era de CSS ni markup sino de binding/orden.

### Causa raiz consolidada

La causa raiz fue un conflicto de inicializacion entre la lane Vite y la lane legacy:

1. `template.js` cacheaba `$.navigation` y hacia binds directos sobre el DOM pre-mount;
2. Vue remonta despues el shell autenticado y deja esos handlers apuntando a nodos viejos;
3. `resources/assets/js/app.js` ademas volvia a pisar `window.$` y `window.jQuery`, agravando la perdida de estado global legacy.

## Estrategia aplicada

Se aplico el cambio minimo necesario, sin ampliar alcance:

1. `resources/assets/plantilla/js/template.js`
   - ahora rehace referencias vivas a `#ui-view` y `nav > ul.nav`;
   - migra los binds criticos del sidebar a delegacion sobre `document`, con `off/on` namespaced para evitar doble inicializacion;
   - escucha el evento `centrodecobros:app-mounted` para rebind despues del mount de Vue.
2. `resources/assets/js/app.js`
   - deja de sobrescribir el jQuery global legacy si ya existe;
   - usa el jQuery global existente como preferencia y solo hace fallback al import ESM;
   - emite `centrodecobros:app-mounted` despues de `app.mount(...)`.

## Archivos modificados

### Codigo

- `resources/assets/plantilla/js/template.js`
- `resources/assets/js/app.js`

### Documentacion

- `docs/MIGRATION_PHASE_17_MENU_FIX.md`
- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_CHANGELOG.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
- `docs/README.md`

### Archivos sensibles auditados y conservados sin cambios

- `resources/views/principal.blade.php`
- `resources/views/auth/contenido.blade.php`
- `resources/views/transaccion/contenido.blade.php`
- `resources/views/verificar/contenido.blade.php`

## Validaciones ejecutadas

### Build y artefactos

- `npm ci`
- `npm run development`
- `npm run production`

Artefactos finales confirmados:

- `public/js/app.js` -> `1859` bytes
- `public/js/plantilla.js` -> `404237` bytes
- `public/css/plantilla.css` -> `246986` bytes

### Backend y no regresion

- `php artisan route:list` -> `OK`, `100` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 123 assertions)`

### Browser semiautomatico focalizado

Se valido sobre `http://127.0.0.1:8017` usando `playwright-cli`.

Rutas y flujos auditados:

1. `/login`
2. `/main`
3. apertura y cierre de submenu del sidebar
4. navegacion a `Roles`
5. navegacion a `Reporte Ingresos SPEI`
6. navegacion a `Reporte Ingresos por Cargos Recurrentes`

Resultados finales de browser:

1. `/login` limpio en consola: `0` errores, `0` warnings.
2. `/main` limpio en consola: `0` errores, `0` warnings.
3. despues del fix, `window.$.navigation[0]` vuelve a apuntar al `nav` vivo del shell (`cachedIsCurrentNav: true`).
4. el submenu `Acceso` abre con clase `open` y vuelve a cerrar correctamente.
5. `Roles` navega por submenu y deja `window.CentroDeCobrosVueRoot.menu = 4`.
6. `Reporte Ingresos SPEI` navega por submenu y deja `window.CentroDeCobrosVueRoot.menu = 20`.
7. `Reporte Ingresos por Cargos Recurrentes` navega por submenu y deja `window.CentroDeCobrosVueRoot.menu = 25`.
8. en los tres modulos auditados la consola final quedo en `0` errores y `0` warnings.

Nota metodologica:

- el click por ref del CLI de Playwright siguio siendo inconsistente en este host para algunos links del sidebar;
- la validacion final se hizo dentro del mismo browser real disparando `click()` sobre los nodos DOM auditados y verificando clases, estado Vue y render del modulo.

## Resultado final

Estado B: `GO ejecutado`

Confirmaciones finales:

1. la causa raiz era de orden de inicializacion y rebinding del shell legacy, no de CSS ni del markup del sidebar;
2. `resources/assets/plantilla/js/template.js` y `resources/assets/js/app.js` fueron suficientes para resolver el problema;
3. el problema quedo resuelto en navegador;
4. el contrato publico de assets sigue intacto:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
5. `principal.blade.php` quedo intacto.

## Riesgos residuales

1. `template.js` sigue siendo una pieza legacy sensible al orden de inicializacion del shell.
2. `plantilla.*` sigue fuera de Vite y todavia concentra jQuery, Bootstrap legacy y globals del shell.
3. `npm ci` sigue reportando `7` vulnerabilidades heredadas (`5 low`, `2 moderate`).
4. realtime (`Echo` / `Pusher`) sigue fuera de alcance end-to-end.

## Rollback detallado

1. Detener cualquier servidor local o sesion browser abierta de `C:\temp\centrodecobros_phase17_menu_fix`.
2. Descartar por completo `C:\temp\centrodecobros_phase17_menu_fix`.
3. Volver a usar `C:\temp\centrodecobros_phase16_vite_stabilization` como baseline valida anterior.
4. Si se requiere rehacer la fase, volver a clonar desde `C:\temp\centrodecobros_phase16_vite_stabilization` hacia una nueva copia aislada.
5. No copiar archivos de regreso a la baseline de Fase 16.

## Recomendacion unica para la fase siguiente

Abrir `Fase 18 - integracion gradual del shell legacy de plantilla.* a un carril mas moderno`.

Motivo rector:

1. el bug corregido en Fase 17 nace exactamente en la frontera entre `app.js` moderno y `template.js` legacy;
2. el siguiente ROI real ya no esta en otra estabilizacion equivalente, sino en modernizar por cortes pequenos el shell legacy mas sensible sin romper el contrato publico.
