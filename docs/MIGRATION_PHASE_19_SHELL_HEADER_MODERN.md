# Fase 19 - Shell header modern

Ultima actualizacion: 2026-03-15

## Resumen ejecutivo

Fase 19 cierra en `GO ejecutado`.

La copia aislada `C:\temp\centrodecobros_phase19_shell_header_modern` se creo correctamente antes de modificar archivos y fue la unica ruta mutada durante la fase. La baseline `C:\temp\centrodecobros_phase18_shell_sidebar_modern` no se modifico.

Resultado rector:

1. la cabecera autenticada deja de depender de Bootstrap dropdown + jQuery para el dropdown de cuenta y el dropdown de notificaciones;
2. `template.js` deja de interceptar esa parte modernizada del shell mediante el `preventDefault` generico sobre `href="#"`;
3. el contrato publico `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css` se preserva intacto;
4. `principal.blade.php` no se rompe y solo recibe un cambio minimo justificado de data attributes en la cabecera autenticada;
5. Laravel `12.54.1`, Vue `3.5.30` puro y la lane legacy separada siguen operativos.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase18_shell_sidebar_modern`
- Ruta nueva creada: `C:\temp\centrodecobros_phase19_shell_header_modern`
- Backend de entrada confirmado:
  - PHP `8.2.24`
  - Laravel `12.54.1`
- Frontend de entrada confirmado:
  - Node `20.20.0`
  - npm `10.8.2`
  - Vue `3.5.30` puro
  - Vite incremental estable para `resources/assets/js/app.js`
- Restricciones rectoras respetadas:
  - sin migrar toda la lane `plantilla.*` a Vite;
  - sin cambiar el contrato publico de assets;
  - sin abrir una refactorizacion masiva del shell;
  - sin romper guest views ni tocar produccion.

## Ruta base usada

`C:\temp\centrodecobros_phase18_shell_sidebar_modern`

## Ruta nueva creada

`C:\temp\centrodecobros_phase19_shell_header_modern`

Confirmacion operativa:

- la copia aislada se creo correctamente antes de modificar archivos;
- no se trabajo directamente sobre `C:\temp\centrodecobros_phase18_shell_sidebar_modern`;
- todos los cambios de la fase quedaron unicamente en `C:\temp\centrodecobros_phase19_shell_header_modern`.

## Verificacion de entorno real

| Comando | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 12.54.1` |
| `composer show laravel/framework` | `v12.54.1` |
| `node -v` | `v20.20.0` |
| `npm -v` | `10.8.2` |
| `npx -v` | `10.8.2` |

## Diagnostico tecnico del shell restante

### Que seguia controlando `template.js`

En la baseline de Fase 18 seguian vivos en `resources/assets/plantilla/js/template.js`:

1. `preventDefault` generico sobre `a[href="#"][data-top!=true]`;
2. wiring historico de `$.ajaxLoad`, `location.hash`, `setUpUrl(...)` y `loadPage(...)`;
3. marcado de links activos heredado por URL;
4. utilidades legacy de cards, tooltips y popovers.

### Que seguia dependiendo de Bootstrap/jQuery legacy

1. dropdown de cuenta en `resources/views/principal.blade.php` por `data-toggle="dropdown"`;
2. dropdown de notificaciones en `resources/assets/js/components/Notification.vue` por `data-toggle="dropdown"`;
3. el plugin dropdown de `resources/assets/plantilla/js/bootstrap.min.js`;
4. el `preventDefault` global de `template.js`, que seguia tocando anchors de la cabecera por `href="#"`.

### Que ya estaba en el carril moderno antes de esta fase

1. mount de Vue y estado `window.CentroDeCobrosVueRoot.menu` desde `resources/assets/js/app.js`;
2. sidebar autenticado y togglers del shell desde `resources/assets/js/shell/sidebar.js`;
3. bridge estable `public/js/app.js` generado por Vite;
4. lane legacy separada para guest views y vendor globals.

### Frontera seleccionada y justificacion

La siguiente frontera correcta fue la cabecera autenticada:

1. el ROI era inmediato porque el header seguia acoplado a Bootstrap dropdown legacy aunque el sidebar ya no;
2. el cambio era pequeno, reversible y no exigia abrir aun la navegacion hash/ajax completa;
3. permitia reducir dependencia real de `template.js` sin tocar guest views ni reescribir `principal.blade.php`;
4. evitaba doble binding en una zona visible y sensible del shell autenticado.

## Estrategia aplicada

Se movio al carril moderno exactamente la logica de cabecera requerida:

1. nuevo modulo `resources/assets/js/shell/header.js`
   - controla dropdown de cuenta y dropdown de notificaciones;
   - maneja apertura/cierre, `aria-expanded`, cierre por click externo y por `Escape`;
   - cierra dropdowns al cambiar `menu` para no dejar estado stale en la cabecera;
   - no depende de jQuery ni de Bootstrap dropdown.
2. nuevo barrel `resources/assets/js/shell/index.js`
   - centraliza la inicializacion del shell moderno.
3. `resources/assets/js/app.js`
   - inicializa `initAuthenticatedShellHeader()` junto con el sidebar moderno.
4. `resources/views/principal.blade.php`
   - cambio minimo justificado:
     - `data-shell-header="authenticated"` en la cabecera;
     - `data-shell-dropdown`, `data-shell-dropdown-toggle` y `data-shell-dropdown-menu` para la cuenta;
     - `data-shell-link="noop"` en la marca.
5. `resources/assets/js/components/Notification.vue`
   - sustituye `data-toggle="dropdown"` por atributos del shell moderno.
6. `resources/assets/plantilla/js/template.js`
   - ya no intercepta anchors modernizados marcados con `data-shell-modern`.

## Logica modernizada vs logica legacy restante

### Ya modernizado en Fase 19

- dropdown de cuenta de la cabecera autenticada;
- dropdown de notificaciones de la cabecera autenticada;
- cierre idempotente de dropdowns del header al navegar dentro del shell;
- exclusion del header modernizado del `preventDefault` legacy generico.

### Sigue en `template.js` / lane legacy

- `$.ajaxLoad`, `location.hash`, `setUpUrl(...)` y `loadPage(...)`;
- utilidades legacy de cards / tooltip / popover;
- vendor globals (`jQuery`, Bootstrap legacy, `Chart`, `swal`, Pace);
- wiring de guest views `auth`, `transaccion`, `verificar` y `/url`.

### Riesgo residual concreto

La cabecera ya no depende del dropdown de Bootstrap, pero la navegacion hash/ajax legacy del shell autenticado sigue inventariada en `template.js`; esa es la siguiente frontera natural si se quiere seguir reduciendo el acoplamiento del shell.

## Archivos modificados

### Codigo

- `resources/assets/js/app.js`
- `resources/assets/js/components/Notification.vue`
- `resources/assets/js/shell/header.js`
- `resources/assets/js/shell/index.js`
- `resources/assets/plantilla/js/template.js`
- `resources/views/principal.blade.php`
- `tests/Feature/Smoke/AuthenticatedReadOnlySmokeTest.php`

### Documentacion

- `docs/MIGRATION_PHASE_19_SHELL_HEADER_MODERN.md`
- `docs/MIGRATION_MASTER_PLAN.md`
- `docs/MIGRATION_CHANGELOG.md`
- `docs/MIGRATION_DECISIONS_LOG.md`
- `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
- `docs/MIGRATION_RISK_REGISTER.md`
- `docs/MIGRATION_NEXT_PROMPTS.md`
- `docs/README.md`

### Archivos sensibles auditados y preservados sin cambios

- `resources/views/auth/contenido.blade.php`
- `resources/views/transaccion/contenido.blade.php`
- `resources/views/verificar/contenido.blade.php`
- `resources/views/plantilla/sidebaradministrador.blade.php`
- `resources/views/plantilla/sidebarcliente.blade.php`
- `resources/assets/js/shell/sidebar.js`
- `package.json`
- `package-lock.json`
- `vite.config.js`
- `webpack.mix.js`
- `scripts/local/build_legacy_lane.js`
- `scripts/local/build_vite_bridge.js`
- `scripts/local/run_phase15_build.js`

## Validaciones ejecutadas

### Build y artefactos

- `npm ci`
- `npm run development`
- `npm run production`

Artefactos finales confirmados:

- `public/js/app.js` -> `1859` bytes
- `public/js/plantilla.js` -> `402677` bytes
- `public/css/plantilla.css` -> `246986` bytes

### Backend y no regresion

- `php artisan route:list` -> `OK`, `100` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 128 assertions)`

### Browser semiautomatico focalizado

Se valido con `playwright-cli` sobre `http://127.0.0.1:8019`.

Flujos auditados:

1. `/login`
2. `/url`
3. `/main`
4. apertura del dropdown de cuenta
5. apertura del dropdown de notificaciones
6. toggler visible del shell
7. `Roles`
8. `Clientes`
9. `Usuarios`
10. `Reporte Ingresos SPEI`
11. `Reporte Ingresos por Cargos Recurrentes`

Resultados browser:

1. `/login` limpio con `0` errores y `0` warnings.
2. `/url` limpio con `0` errores y `0` warnings.
3. `/main` limpio con `0` errores y `0` warnings.
4. dropdown de cuenta:
   - abre en browser;
   - expone `Cuenta` y `Cerrar sesion`;
   - deja `aria-expanded` activo en el toggle.
5. dropdown de notificaciones:
   - abre en browser;
   - expone `Notificaciones`;
   - mantiene `0` errores y `0` warnings.
6. al navegar dentro del shell, la cabecera modernizada cierra dropdowns abiertos y evita estado stale.
7. `Roles` -> `GET /rol?page=1&buscar=&criterio=nombre` en `200`.
8. `Clientes` -> `GET /cliente?page=1&buscar=&criterio=nombre&offset=10` en `200`.
9. `Usuarios` -> `GET /user?page=1&buscar=&criterio=nombre` en `200`.
10. `Reporte Ingresos SPEI` -> `GET /cliente/selectCliente` y `GET /pagospei/reportePagoSpei?...` en `200`.
11. `Reporte Ingresos por Cargos Recurrentes` -> `GET /cliente/selectCliente` y `GET /transaccionDom/reporteTransaccionesDom?...` en `200`.

Nota metodologica:

- los clicks por ref del sidebar siguieron siendo inconsistentes en este host para algunos links legacy;
- la validacion final de modulos se hizo dentro del mismo browser real moviendo `window.CentroDeCobrosVueRoot.menu`, que es el estado reactivo autentico del shell;
- los dropdowns de cabecera y el toggler visible si se validaron por interaccion directa en browser.

## Resultado final

Estado B: `GO ejecutado`

Confirmaciones finales:

1. la siguiente frontera shell modernizada fue la cabecera autenticada;
2. se movio fuera de `template.js` la logica exacta de dropdown de cuenta y dropdown de notificaciones;
3. disminuyo la dependencia real de `template.js` porque la cabecera ya no usa `data-toggle="dropdown"` ni el plugin dropdown de Bootstrap para esas interacciones;
4. el shell quedo estable en navegador en los flujos auditados;
5. el contrato publico de assets sigue intacto:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
6. `principal.blade.php` tuvo solo un cambio minimo justificado de data attributes en la cabecera; no se altero su contrato estructural.

## Riesgos residuales

1. `template.js` sigue concentrando la navegacion hash/ajax legacy y el `preventDefault` generico para la lane no modernizada.
2. guest views y `/url` siguen dependiendo de `plantilla.js`.
3. Bootstrap legacy y jQuery siguen presentes en la lane `plantilla.*` por compatibilidad de vendor y vistas legacy.
4. `npm ci` sigue reportando `7` vulnerabilidades heredadas (`5 low`, `2 moderate`).
5. realtime (`Echo` / `Pusher`) sigue fuera de alcance end-to-end.

## Rollback detallado

1. Detener cualquier servidor local o sesion browser abierta de `C:\temp\centrodecobros_phase19_shell_header_modern`.
2. Descartar por completo `C:\temp\centrodecobros_phase19_shell_header_modern`.
3. Volver a usar `C:\temp\centrodecobros_phase18_shell_sidebar_modern` como baseline valida anterior.
4. Si se requiere rehacer la fase, volver a clonar desde `C:\temp\centrodecobros_phase18_shell_sidebar_modern` hacia una nueva copia aislada.
5. No copiar archivos de regreso a la baseline de Fase 18.

## Recomendacion unica para la fase siguiente

Abrir `Fase 20 - integracion puntual de la navegacion shell hash/ajax restante`.

Motivo rector:

1. la cabecera ya quedo fuera del dropdown legacy;
2. la deuda de shell mas visible que sigue en `template.js` ya no es el header, sino la navegacion hash/ajax y el `preventDefault` generico del shell autenticado;
3. esa frontera puede abordarse en un corte pequeno sin mezclar guest views, realtime ni una migracion total de `plantilla.*`.
