# Fase 20 - Shell navigation modern

Ultima actualizacion: 2026-03-23

## Resumen ejecutivo

Fase 20 cierra en `GO ejecutado`.

La copia aislada `C:\temp\centrodecobros_phase20_shell_navigation_modern` se creo correctamente antes de modificar archivos y fue la unica ruta mutada durante la fase. La baseline `C:\temp\centrodecobros_phase19_shell_header_modern` no se modifico.

Resultado rector:

1. la navegacion shell autenticada deja de depender de `template.js` para la compatibilidad puntual de hash/ajax restante y para el `preventDefault` generico sobre `href="#"` dentro de `#app`;
2. `resources/assets/plantilla/js/template.js` deja de activar `$.ajaxLoad` y de bindear la navegacion shell autenticada cuando el shell moderno esta presente;
3. el contrato publico `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css` se preserva intacto;
4. `principal.blade.php` queda intacto en esta fase;
5. Laravel `12.54.1`, Vue `3.5.30` puro, Vite incremental para `app.js` y la lane legacy separada siguen operativos.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase19_shell_header_modern`
- Ruta nueva creada: `C:\temp\centrodecobros_phase20_shell_navigation_modern`
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
  - sin romper `principal.blade.php`;
  - sin abrir una refactorizacion masiva del shell;
  - sin tocar guest views ni produccion fuera del corte puntual auditado.

## Ruta base usada

`C:\temp\centrodecobros_phase19_shell_header_modern`

## Ruta nueva creada

`C:\temp\centrodecobros_phase20_shell_navigation_modern`

Confirmacion operativa:

- la copia aislada se creo correctamente antes de modificar archivos;
- no se trabajo directamente sobre `C:\temp\centrodecobros_phase19_shell_header_modern`;
- todos los cambios de la fase quedaron unicamente en `C:\temp\centrodecobros_phase20_shell_navigation_modern`.

## Verificacion de entorno real

| Comando | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 12.54.1` |
| `composer show laravel/framework` | `v12.54.1` |
| `node -v` | `v20.20.0` |
| `npm -v` | `10.8.2` |

## Diagnostico tecnico de la navegacion shell restante

### Que seguia controlando `template.js`

En la baseline de Fase 19 seguian vivos en `resources/assets/plantilla/js/template.js`:

1. `$.ajaxLoad = true`;
2. bootstrap inicial por `location.hash`;
3. `setUpUrl(url)` y `loadPage(url)` sobre `$.subPagesDirectory`;
4. `preventDefault` generico sobre `a[href="#"]`;
5. `bindSidebarInteractions()` con acoplamiento historico al shell.

### Que seguia dependiendo de hash/ajax

La auditoria real mostro dos capas distintas:

1. el shell autenticado ya navega por `window.CentroDeCobrosVueRoot.menu`, `data-menu-target` y los modulos Vue en `resources/views/contenido/contenido.blade.php`;
2. la dependencia restante a hash/ajax dentro del shell autenticado era ya solo residual:
   - compatibilidad con hashes legacy como `#roles` o `#clientes`;
   - `preventDefault` generico de anchors `href="#"` heredado desde `template.js`.

No se encontro evidencia de que el shell autenticado siga usando hoy `$.defaultPage`, `$.subPagesDirectory` o `$.page404` como ruta viva propia.

### Que siguio usando `a[href="#"]`

Se inventariaron tres grupos:

1. sidebar autenticado Blade con `href="#"` y `data-menu-target`;
2. cabecera autenticada con toggles y links noop ya modernizados desde Fases 18 y 19;
3. vistas guest y vendor legacy que siguen cargando `plantilla.js`.

El problema real ya no era "todos los anchors", sino que `template.js` seguia interceptando globalmente zonas del shell autenticado que ya tienen carril moderno propio.

### Frontera seleccionada y justificacion

El corte correcto para esta fase fue:

1. mover al carril moderno la compatibilidad puntual de hash legacy hacia `menu`;
2. mover al carril moderno el `preventDefault` de anchors noop del shell autenticado;
3. desactivar en `template.js` la navegacion hash/ajax legacy cuando el shell autenticado moderno esta presente.

Se eligio este corte y no otro porque:

1. es pequeno, reversible y de alto ROI sobre la deuda real restante;
2. reduce doble binding entre `app.js`, `template.js` y Blade sin abrir aun guest views;
3. no obliga a migrar toda la lane `plantilla.*` ni a tocar `principal.blade.php`;
4. preserva el contrato de assets mientras baja la dependencia real del shell respecto a `template.js`.

## Estrategia aplicada

Se movio al carril moderno exactamente la logica puntual de navegacion shell restante:

1. nuevo modulo `resources/assets/js/shell/navigation.js`
   - controla el `preventDefault` de `a[href="#"]` dentro del shell autenticado;
   - traduce hashes legacy conocidos a valores de `window.CentroDeCobrosVueRoot.menu`;
   - limpia el hash despues de sincronizar el menu;
   - evita que el hash stale sobreviva a cambios de menu.
2. `resources/assets/js/shell/index.js`
   - exporta `initAuthenticatedShellNavigation()`.
3. `resources/assets/js/app.js`
   - inicializa el nuevo modulo junto con sidebar y header;
   - sincroniza el shell autenticado desde el hash legacy en el mount moderno.
4. `resources/assets/plantilla/js/template.js`
   - detecta si existe shell autenticado moderno;
   - deja `$.ajaxLoad = false` en ese caso;
   - no vuelve a bindear la navegacion shell autenticada legacy.

## Logica modernizada vs logica legacy restante

### Ya modernizado en Fase 20

- compatibilidad de entrada `location.hash -> menu Vue` para hashes shell conocidos;
- `preventDefault` de anchors noop del shell autenticado;
- limpieza de hash residual al entrar o cambiar de modulo en el shell autenticado;
- exclusion efectiva del shell autenticado del wiring `$.ajaxLoad` / `bindSidebarInteractions()` de `template.js`.

### Sigue en `template.js` / lane legacy

- `setUpUrl(...)` y `loadPage(...)` para la lane legacy aun no retirada;
- utilidades legacy de cards / tooltip / popover;
- vendor globals (`jQuery`, Bootstrap legacy, `Chart`, `swal`, Pace);
- wiring de guest views `auth`, `transaccion`, `verificar` y `/url`.

### Riesgo residual concreto

La dependencia fuerte del shell autenticado respecto a `template.js` ya bajo otra vez, pero la lane guest y los residuos ajax/hash legacy siguen existiendo en `template.js`; esa es la siguiente frontera natural si se quiere seguir reduciendo el acoplamiento de `plantilla.*`.

## Archivos modificados

### Codigo

- `resources/assets/js/app.js`
- `resources/assets/js/shell/index.js`
- `resources/assets/js/shell/navigation.js`
- `resources/assets/plantilla/js/template.js`

### Documentacion

- `docs/MIGRATION_PHASE_20_SHELL_NAVIGATION_MODERN.md`
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
- `resources/views/plantilla/sidebaradministrador.blade.php`
- `resources/views/plantilla/sidebarcliente.blade.php`
- `resources/assets/js/shell/sidebar.js`
- `resources/assets/js/shell/header.js`
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
- `public/js/plantilla.js` -> `402940` bytes
- `public/css/plantilla.css` -> `246986` bytes

### Backend y no regresion

- `php artisan route:list` -> `OK`, `100` rutas
- `php artisan schedule:list` -> `OK`, `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 128 assertions)`

Nota operativa:

- la suite `phpunit` dependia de la base local real en `127.0.0.1:3306`;
- se verifico el entorno y se ejecuto la suite contra MySQL local ya disponible en el host, sin cambios de codigo para la fase.

### Browser semiautomatico focalizado

Se valido con `playwright-cli` sobre `http://127.0.0.1:8010`.

Flujos auditados:

1. `/login`
2. `/url`
3. `/main#roles`
4. cabecera autenticada
5. sidebar autenticado
6. `Roles`
7. `Clientes`
8. `Usuarios`
9. `Reporte Ingresos SPEI`
10. `Reporte Ingresos por Cargos Recurrentes`

Resultados browser:

1. `/login` limpio con `0` errores y `0` warnings.
2. `/url` limpio con `0` errores y `0` warnings.
3. `/main#roles`
   - resuelve en `/main` sin hash residual;
   - sincroniza `window.CentroDeCobrosVueRoot.menu = 4`;
   - deja `Roles` como opcion activa en el sidebar.
4. cabecera autenticada:
   - dropdown de cuenta abre y cierra correctamente;
   - sin reintroducir warnings ni errores.
5. sidebar autenticado:
   - `Clientes` -> `menu = 9`;
   - `Usuarios` -> `menu = 3`;
   - `Ingresos SPEI` -> `menu = 20`;
   - `Ingresos Cargos Recurrentes` -> `menu = 25`;
   - en todos los casos `window.location.hash === ''`.
6. consola final del shell auditado:
   - `0` errores;
   - `0` warnings.

## Resultado final

Estado B: `GO ejecutado`

Confirmaciones finales:

1. la frontera de navegacion shell restante era la compatibilidad hash/ajax puntual y el `preventDefault` generico de anchors noop;
2. se movio fuera de `template.js` la logica exacta de:
   - compatibilidad de hash legacy a `menu`;
   - `preventDefault` de `a[href="#"]` dentro del shell autenticado.
3. disminuyo la dependencia real de `template.js` porque el shell autenticado ya no usa su wiring `$.ajaxLoad` ni su binding de anchors noop para navegar;
4. el shell quedo estable en navegador en los flujos auditados;
5. el contrato publico de assets sigue intacto:
   - `public/js/app.js`
   - `public/js/plantilla.js`
   - `public/css/plantilla.css`
6. `principal.blade.php` queda intacto en esta fase.

## Riesgos residuales

1. `template.js` sigue concentrando la lane guest y utilidades vendor legacy.
2. `setUpUrl(...)` y `loadPage(...)` siguen presentes mientras no se cierre una fase especifica sobre la lane legacy restante.
3. hashes shell no inventariados en el mapa moderno simplemente se ignoran; no se eliminaron aun por completo los residuos hash/ajax legacy.
4. `npm ci` sigue reportando `7` vulnerabilidades heredadas (`5 low`, `2 moderate`).
5. realtime (`Echo` / `Pusher`) sigue fuera de alcance end-to-end.

## Rollback detallado

1. Detener cualquier servidor local o sesion browser abierta de `C:\temp\centrodecobros_phase20_shell_navigation_modern`.
2. Descartar por completo `C:\temp\centrodecobros_phase20_shell_navigation_modern`.
3. Volver a usar `C:\temp\centrodecobros_phase19_shell_header_modern` como baseline valida anterior.
4. Si se requiere rehacer la fase, volver a clonar desde `C:\temp\centrodecobros_phase19_shell_header_modern` hacia una nueva copia aislada.
5. No copiar archivos de regreso a la baseline de Fase 19.

## Recomendacion unica para la fase siguiente

Abrir `Fase 21 - segmentacion puntual de la lane residual de template.js entre shell ya modernizado y guest/legacy real`.

Motivo rector:

1. sidebar, cabecera y navegacion shell autenticada ya quedaron fuera de la parte critica de `template.js`;
2. la deuda siguiente con mejor ROI ya no es realtime, sino aislar o retirar el residuo `setUpUrl/loadPage/ajaxLoad` que queda mezclado con guest views;
3. ese siguiente corte puede seguir preservando `principal.blade.php` y el contrato `app.js` / `plantilla.js` / `plantilla.css`.
