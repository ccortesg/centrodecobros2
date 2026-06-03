# Fase 27 - Template shared rationalization

Fecha: 2026-03-24
Estado final: `GO ejecutado`

## Resumen ejecutivo

La Fase 27 se ejecuto en `C:\temp\centrodecobros_phase27_template_shared_rationalization` y cerro en `GO`.

El corte elegido fue pequeno, puntual y reversible:

- racionalizar `resources/assets/plantilla/js/template.shared.js` retirando la subcapa shared UI sin callers ni DOM vivo comprobable;
- conservar solo la compatibilidad minima todavia justificada dentro del bundle legacy publicado;
- no tocar `principal.blade.php`;
- no cambiar el contrato publico de assets;
- no reabrir guest publica;
- no reactivar el residual ajax/hash.

El recorte se apoya en evidencia directa:

1. `/login` y `/url` siguen cargando solo `public/js/guest-public.js`.
2. El shell autenticado moderno ya resuelve sidebar, cabecera y navegacion fuera de `template.shared.js`.
3. No aparecieron callers reales para `window.capitalizeFirstLetter`, `resizeBroadcast`, tooltip/popover ni `.card-actions a`.
4. `window.init` seguia vivo solo por compatibilidad con `template.ajax-hash.js`.

Medicion del recorte:

- `resources/assets/plantilla/js/template.shared.js` baja de `4085` a `2172` bytes.
- `resources/assets/plantilla/js/template.shared.js` baja de `117` a `64` lineas.
- `public/js/plantilla.js` en produccion baja de `404791` a `403683` bytes.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase26_template_rationalization`
- Ruta nueva creada: `C:\temp\centrodecobros_phase27_template_shared_rationalization`
- La copia aislada se creo correctamente antes de modificar archivos.
- Backend confirmado: Laravel `12.54.1` con PHP `8.2.24`
- Frontend confirmado: Node `20.20.0`, npm `10.8.2`, Vue `3.5.30` puro

## Auditoria de `template.shared.js`

### Funciones presentes al inicio de la fase

`resources/assets/plantilla/js/template.shared.js` contenia:

- `getScopedSelector(...)`
- `resizeBroadcast()`
- `markActiveNavigationLinks()`
- `bindNoopAnchors()`
- `initScopedUi(...)` para tooltip/popover
- `initLegacySurface()`
- handler delegado para `.card-actions a`
- `window.capitalizeFirstLetter`
- `window.init`

### Callers reales comprobados

#### `window.init`

- caller real encontrado: `resources/assets/plantilla/js/template.ajax-hash.js`
- uso real actual: compatibilidad residual dentro de `loadJS(...)`
- conclusion: sigue vivo solo como alias minimo de compatibilidad

#### `bindNoopAnchors()`

- caller interno real: `templateState.init()` en el propio `template.shared.js`
- caller residual en disco, no publicado: `resources/assets/plantilla/js/template.guest.js`
- conclusion: se conserva como compatibilidad minima para superficies legacy no modernas

#### `markActiveNavigationLinks()`

- caller interno real: `templateState.init()` en el propio `template.shared.js`
- sin callers externos en `resources/`, `tests/`, `scripts/`, `app/` ni `routes/`
- conclusion: queda retenido como compatibilidad minima para navegacion legacy no moderna

#### `resizeBroadcast()`

- sin callers externos
- conclusion: deuda muerta

#### `initScopedUi(...)` para tooltip/popover

- sin callers externos vivos
- sin atributos `rel="tooltip"`, `data-rel="tooltip"`, `rel="popover"`, `data-rel="popover"` ni `data-toggle="popover"` en views, tests o scripts auditados
- conclusion: deuda muerta

#### Handler de `.card-actions a`

- sin markup vivo auditado en views o componentes para `.card-actions a`, `.btn-close`, `.btn-minimize` o `.btn-setting`
- conclusion: deuda muerta

#### `window.capitalizeFirstLetter`

- sin callers externos en codigo, views, tests ni scripts
- conclusion: deuda muerta

## Corte seleccionado

Se eligio una sola frontera principal:

`retirar la subcapa shared UI muerta de template.shared.js y dejar solo compatibilidad minima`

Justificacion:

1. Era el recorte con mejor ROI tecnico dentro del residual restante.
2. Evitaba abrir una refactorizacion mayor de toda la lane `plantilla.*`.
3. No tocaba `principal.blade.php`.
4. No cambiaba nombres de salida ni el contrato publico.
5. Mantenia encapsulado y dormido el residual ajax/hash.

## Cambios aplicados

### `resources/assets/plantilla/js/template.shared.js`

Retirado:

- `resizeBroadcast()`
- `initScopedUi(...)` de tooltip/popover
- handler delegado de `.card-actions a`
- `window.capitalizeFirstLetter`

Conservado:

- `markActiveNavigationLinks()`
- `bindNoopAnchors()`
- `templateState.init()` como init minimo
- `window.init` como alias de compatibilidad minima hacia `templateState.init()`

### `resources/assets/plantilla/js/template.js`

Ajuste minimo relacionado:

- se elimina el selector residual `cardActions`, ya sin uso despues del recorte

### `resources/assets/plantilla/js/template.guest.js`

Ajuste de coherencia en archivo historico no publicado:

- se elimina la llamada a `templateState.initScopedUi(...)`

## Estado resultante

### Lo que salio

- tooltip/popover
- `card-actions`
- `window.capitalizeFirstLetter`
- `resizeBroadcast`

### Lo que sigue vivo

- `bindNoopAnchors()` como compatibilidad minima para surfaces legacy no modernas
- `markActiveNavigationLinks()` como compatibilidad minima para navegacion legacy no moderna
- `window.init` solo como alias minimo consumido por `template.ajax-hash.js`

### Estado de `template.js`

`template.js` todavia mantiene guest branching residual:

- `guestContext`
- `guestSurface`
- `hasGuestContext()`
- `getGuestSurface()`

Conclusion:

- ya no gobierna `/login` ni `/url`
- pasa a ser la siguiente frontera puntual con mejor ROI

### Estado del residual ajax/hash

- permanece encapsulado en `window.CentroDeCobrosLegacyAjaxHash`
- permanece sin autoarranque
- no fue reactivado
- su unico acoplamiento restante con `template.shared.js` queda reducido a `window.init` como alias minimo

## Validaciones ejecutadas

### Entorno

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `node -v` -> `v20.20.0`
- `npm -v` -> `10.8.2`

### Build y artefactos

- `npm ci` -> `OK`; se mantienen `7` vulnerabilidades heredadas
- `npm run development` -> `OK`
- `npm run production` -> `OK`

Artefactos verificados:

- `public/js/app.js` -> `1859` bytes
- `public/js/plantilla.js` -> `403683` bytes
- `public/css/plantilla.css` -> `246986` bytes
- `public/js/guest-public.js` -> `1141` bytes

### Backend

- `php artisan route:list` -> `OK`; `97` rutas
- `php artisan schedule:list` -> `OK`; `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 136 assertions)`

Nota de entorno:

- `phpunit` fallo en el primer intento porque `wampmysqld64` estaba detenido.
- Se levanto temporalmente el servicio local.
- La suite quedo en verde.
- El servicio se devolvio a `Stopped` al terminar.

### Browser semiautomatico con `playwright-cli`

Servidor local auditado:

- `http://127.0.0.1:8027`

#### `/login`

- scripts cargados: solo `js/guest-public.js`
- `data-template-context = guest`
- `data-template-view = auth`
- `data-template-guest-ready = true`
- `data-template-guest-screen-active = login`
- `data-template-runtime = guest-public`
- `window.CentroDeCobrosLegacyTemplate` ausente
- `window.jQuery` ausente
- consola: `0` errores / `0` warnings

#### `/url`

- scripts cargados: solo `js/guest-public.js`
- `data-template-view = transaccion`
- `data-template-guest-screen-active = url`
- POST seguro validado con `https://example.com`
- `iframe` renderizado correctamente
- consola: `0` errores / `0` warnings

#### `/main` y shell autenticado

- `meta[name="userId"] = 1`
- `window.CentroDeCobrosLegacyTemplate.state.ajaxHashMode = "disabled"`
- `window.CentroDeCobrosVueRoot.menu = 0` al entrar
- `notification/get` y `dashboard` en `200`
- toggle de cuenta auditado con `aria-expanded = true`
- grupo `Acceso` del sidebar auditado con `open = true`
- consola: `0` errores / `0` warnings

#### Modulos auditados

1. `Roles`
   - `window.CentroDeCobrosVueRoot.menu = 4`
   - `GET /rol?page=1&buscar=&criterio=nombre` -> `200`
2. `Clientes`
   - `window.CentroDeCobrosVueRoot.menu = 9`
   - `GET /cliente?page=1&buscar=&criterio=nombre&offset=10` -> `200`
3. `Usuarios`
   - `window.CentroDeCobrosVueRoot.menu = 3`
   - `GET /user?page=1&buscar=&criterio=nombre` -> `200`
4. `Reporte Ingresos por Cargos Recurrentes`
   - `window.CentroDeCobrosVueRoot.menu = 25`
   - `GET /cliente/selectCliente` -> `200`
   - `GET /transaccionDom/reporteTransaccionesDom?idcliente=0&fechaInicio=null&fechaFin=null` -> `200`
5. `Reporte Ingresos SPEI`
   - `window.CentroDeCobrosVueRoot.menu = 20`
   - `GET /cliente/selectCliente` -> `200`
   - `GET /pagospei/reportePagoSpei?idcliente=0&fechaInicio=null&fechaFin=null` -> `200`

## Estado del contrato de assets

Preservado sin cambios:

- `public/js/app.js`
- `public/js/plantilla.js`
- `public/css/plantilla.css`
- `public/js/guest-public.js`

`principal.blade.php` queda intacto.

## Rollback

1. No usar `C:\temp\centrodecobros_phase27_template_shared_rationalization` como workspace activo si se desea revertir la fase.
2. Volver a tomar `C:\temp\centrodecobros_phase26_template_rationalization` como baseline valida anterior.
3. Descartar `C:\temp\centrodecobros_phase27_template_shared_rationalization` para revertir completamente la fase.

## Recomendacion unica posterior

Recomendacion unica:

`Fase 28 - racionalizar el guest branching residual que sigue en template.js`

Justificacion:

- `template.shared.js` ya quedo reducido a compatibilidad minima.
- `/login` y `/url` ya no dependen de `plantilla.js`.
- El siguiente ROI puntual esta en retirar o acotar `guestContext` / `guestSurface` / `hasGuestContext()` / `getGuestSurface()` del bootstrap legacy restante, sin mezclar aun una migracion total de `plantilla.*` a Vite.
