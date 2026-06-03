# Fase 18 - Shell sidebar modern

Ultima actualizacion: 2026-03-15

## Resumen ejecutivo

Fase 18 cierra en `GO ejecutado`.

La copia aislada `C:\temp\centrodecobros_phase18_shell_sidebar_modern` se creo correctamente antes de modificar archivos y fue la unica ruta mutada durante la fase. La baseline `C:\temp\centrodecobros_phase17_menu_fix` no se modifico.

Resultado rector:

1. el sidebar autenticado ya no depende criticamente de `resources/assets/plantilla/js/template.js` para menu, submenu y togglers del shell;
2. la logica sensible del sidebar paso a un modulo moderno cargado desde `resources/assets/js/app.js` por Vite;
3. el contrato publico `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css` se preserva intacto;
4. `principal.blade.php` queda intacto;
5. Laravel `12.54.1`, Vue `3.5.30` puro y la lane legacy separada siguen operativos.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase17_menu_fix`
- Ruta nueva creada: `C:\temp\centrodecobros_phase18_shell_sidebar_modern`
- Backend de entrada confirmado:
  - PHP `8.2.24`
  - Laravel `12.54.1`
- Frontend de entrada confirmado:
  - Node `20.20.0`
  - npm `10.8.2`
  - Vue `3.5.30` puro
  - Vite incremental estable para `resources/assets/js/app.js`
- Restricciones rectoras respetadas:
  - sin meter toda la lane `plantilla.*` a Vite;
  - sin cambiar el contrato publico de assets;
  - sin romper `principal.blade.php`;
  - sin tocar contratos de negocio externos;
  - sin mezclar la fase con realtime end-to-end ni hardening amplio.

## Ruta base usada

`C:\temp\centrodecobros_phase17_menu_fix`

## Ruta nueva creada

`C:\temp\centrodecobros_phase18_shell_sidebar_modern`

Confirmacion operativa:

- la copia aislada se creo correctamente antes de modificar archivos;
- no se trabajo directamente sobre `C:\temp\centrodecobros_phase17_menu_fix`;
- todos los cambios de la fase quedaron unicamente en `C:\temp\centrodecobros_phase18_shell_sidebar_modern`.

## Verificacion de entorno real

| Comando | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 12.54.1` |
| `composer show laravel/framework` | `v12.54.1` |
| `node -v` | `v20.20.0` |
| `npm -v` | `10.8.2` |
| `npx -v` | `10.8.2` |

## Diagnostico tecnico del shell/sidebar

### Archivos que inicializaban el menu antes del cambio

1. `resources/assets/plantilla/js/template.js`
   - apertura y cierre de `li.nav-dropdown`;
   - togglers de `sidebar-hidden`, `sidebar-minimized`, `brand-minimized`, `aside-menu-hidden`, `sidebar-mobile-show`, `sidebar-opened`;
   - `preventDefault` sobre `a[href="#"]`.
2. `resources/assets/js/app.js`
   - montaba Vue sobre `#app`;
   - exponia `window.CentroDeCobrosVueRoot.menu`;
   - en Fase 17 emitia `centrodecobros:app-mounted`, pero el menu seguia dependiendo de jQuery/template.
3. `resources/views/plantilla/sidebaradministrador.blade.php` y `resources/views/plantilla/sidebarcliente.blade.php`
   - definian el markup real del sidebar;
   - seguian usando `@click="menu=..."` en cada item leaf.

### Listeners auditados en la baseline de Fase 17

En `template.js` seguian presentes listeners delegados jQuery para:

1. `nav > ul.nav a`
2. `.sidebar-toggler`
3. `.sidebar-minimizer`
4. `.brand-minimizer`
5. `.aside-menu-toggler`
6. `.mobile-sidebar-toggler`
7. `.sidebar-close`
8. `a[href="#"][data-top!=true]`

### Clases y estado DOM que controlan el shell

- submenus: `li.nav-dropdown.open`
- item activo: `.sidebar .nav-link.active`
- colapso del sidebar: `body.sidebar-hidden`
- minimizado del sidebar: `body.sidebar-minimized`
- minimizado de marca: `body.brand-minimized`
- aside oculto: `body.aside-menu-hidden`
- sidebar mobile: `body.sidebar-mobile-show`
- sidebar opened heredado: `body.sidebar-opened` y `html.sidebar-opened`

### Dependencia real de jQuery / template.js antes del corte

- la apertura/cierre del submenu dependia de jQuery delegado dentro de `template.js`;
- los togglers del shell dependian de jQuery delegado dentro de `template.js`;
- el binding de seleccion de modulo dependia de directives Vue `@click` incrustadas en Blade;
- `template.js` y Vue seguian compartiendo responsabilidad sobre el mismo sidebar.

### Parte que ya podia controlarse desde `app.js` / Vite

- seleccion de modulo via `window.CentroDeCobrosVueRoot.menu`;
- sincronizacion de clases `active/open` sobre el sidebar vivo;
- togglers del shell que solo agregan/quitan clases del `body`;
- rebinding post-mount sin jQuery usando eventos DOM y delegacion nativa.

## Estrategia aplicada

Se movio al carril moderno exactamente la logica sensible del sidebar:

1. nuevo modulo `resources/assets/js/shell/sidebar.js`
   - delegacion nativa del sidebar autenticado;
   - sincronizacion de `menu`, `active` y `open`;
   - togglers de body del shell;
   - listeners idempotentes sobre `document`;
   - cero dependencia de jQuery.
2. `resources/assets/js/app.js`
   - inicializa el modulo moderno del sidebar;
   - emite `centrodecobros:menu-changed` desde el watcher del root Vue;
   - sincroniza el estado inicial del sidebar despues de `app.mount(...)`.
3. `resources/views/plantilla/sidebaradministrador.blade.php`
4. `resources/views/plantilla/sidebarcliente.blade.php`
   - se retiraron los `@click="menu=..."`;
   - se agregaron `data-shell-sidebar="authenticated"` y `data-menu-target="..."`;
   - el markup del sidebar sigue en Blade y el contrato de rutas/public assets no cambia.
5. `resources/assets/plantilla/js/template.js`
   - deja de bindear menu/submenu/togglers del shell;
   - conserva solo la utilidad legacy no sensible en esta fase.

## Logica modernizada vs logica legacy restante

### Ya modernizado en Fase 18

- apertura y cierre de submenu del sidebar autenticado;
- clases `active/open` del sidebar autenticado;
- sincronizacion de menu Vue con el sidebar;
- togglers del shell autenticado que cambian clases de `body`;
- rebinding post-mount sin depender del orden fragil `template.js -> mount Vue`.

### Sigue en `template.js` / lane legacy

- globals vendor legacy (`jQuery`, Bootstrap legacy, `Chart`, `swal`, Pace);
- wiring historico de `preventDefault` para anchors `#`;
- utilidades legacy de cards/tooltips/popovers/ajax load que no se abrieron en esta fase;
- vistas guest que siguen cargando `plantilla.js`.

### Riesgo legacy residual concreto

`template.js` ya no controla el sidebar autenticado, pero sigue concentrando vendor globals y wiring de paginas guest; por eso la lane `plantilla.*` todavia no puede entrar completa a Vite en un solo corte.

## Archivos modificados

### Codigo

- `resources/assets/js/app.js`
- `resources/assets/js/shell/sidebar.js`
- `resources/assets/plantilla/js/template.js`
- `resources/views/plantilla/sidebaradministrador.blade.php`
- `resources/views/plantilla/sidebarcliente.blade.php`
- `tests/Feature/Smoke/AuthenticatedReadOnlySmokeTest.php`

### Documentacion

- `docs/MIGRATION_PHASE_18_SHELL_SIDEBAR_MODERN.md`
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
- `package.json`
- `package-lock.json`
- `vite.config.js`
- `scripts/local/build_vite_bridge.js`
- `scripts/local/build_legacy_lane.js`
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
- `php vendor/bin/phpunit` -> `OK (23 tests, 125 assertions)`

### Browser semiautomatico focalizado

Se valido con `playwright-cli` sobre `http://127.0.0.1:8018`.

Flujos auditados:

1. `/login`
2. `/main`
3. apertura de submenu `Acceso`
4. cierre de submenu `Acceso`
5. navegacion a `Roles`
6. navegacion a `Clientes`
7. navegacion a `Usuarios`
8. navegacion a `Reporte Ingresos SPEI`
9. navegacion a `Reporte Ingresos por Cargos Recurrentes`
10. `/url`

Resultados browser:

1. `/login` limpio en consola con `0` errores y `0` warnings; solo quedo un mensaje `verbose` del navegador sobre `autocomplete`.
2. `/main` limpio en consola con `0` errores y `0` warnings.
3. `Acceso` abre y `li.nav-dropdown` queda en clase `nav-item nav-dropdown open`.
4. `Acceso` cierra y `li.nav-dropdown` vuelve a `nav-item nav-dropdown`.
5. `Roles` navega y deja `window.CentroDeCobrosVueRoot.menu = 4`.
6. `Clientes` navega y deja `window.CentroDeCobrosVueRoot.menu = 9`.
7. `Usuarios` navega y deja `window.CentroDeCobrosVueRoot.menu = 3`.
8. `Reporte Ingresos SPEI` navega y deja `window.CentroDeCobrosVueRoot.menu = 20`.
9. `Reporte Ingresos por Cargos Recurrentes` navega y deja `window.CentroDeCobrosVueRoot.menu = 25`.
10. `/url` renderiza correctamente y queda con `0` errores y `0` warnings en consola.

Nota metodologica:

- para automatizar `/main` sin dejar cambios persistentes en datos reales, se reseteo temporalmente el password local del usuario admin de la copia Fase 18 y se restauro su hash original al cierre de la validacion browser;
- el cambio quedo confinado a esta copia aislada y no afecta la baseline de Fase 17.

## Resultado final

Estado B: `GO ejecutado`

Confirmaciones finales:

1. el sidebar ya quedo menos dependiente de `template.js`;
2. desaparecio la fragilidad principal por orden de inicializacion entre sidebar/menu y mount de Vue;
3. el shell quedo estable en navegador en los modulos auditados;
4. el contrato publico de assets sigue intacto:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
5. `principal.blade.php` quedo intacto.

## Riesgos residuales

1. `template.js` sigue vivo para la lane legacy y guest views.
2. `plantilla.js` sigue concentrando vendor globals (`jQuery`, Bootstrap legacy, `Chart`, `swal`, Pace).
3. el root Vue sigue dependiendo del build con compilador porque el template vive en Blade.
4. `npm ci` sigue reportando `7` vulnerabilidades heredadas (`5 low`, `2 moderate`).
5. realtime (`Echo` / `Pusher`) sigue fuera del alcance end-to-end.

## Rollback detallado

1. Detener cualquier servidor local o sesion browser abierta de `C:\temp\centrodecobros_phase18_shell_sidebar_modern`.
2. Descartar por completo `C:\temp\centrodecobros_phase18_shell_sidebar_modern`.
3. Volver a usar `C:\temp\centrodecobros_phase17_menu_fix` como baseline valida anterior.
4. Si se requiere rehacer la fase, volver a clonar desde `C:\temp\centrodecobros_phase17_menu_fix` hacia una nueva copia aislada.
5. No copiar archivos de regreso a la baseline de Fase 17.

## Recomendacion unica para la fase siguiente

Abrir una fase puntual para integrar al carril moderno la cabecera/togglers restantes del shell autenticado que todavia viven en `template.js`, manteniendo `plantilla.*` separado para guest views y vendor globals.
