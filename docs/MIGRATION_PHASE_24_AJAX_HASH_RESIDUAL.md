# Fase 24 - Ajax hash residual

Fecha: 2026-03-24
Estado final: `GO ejecutado`

## Resumen ejecutivo

La Fase 24 audito y cerro la deuda residual `ajax/hash` no-shell.

Resultado final:

- no se encontraron callers vivos en codigo actual para `$.defaultPage`, `$.subPagesDirectory`, `$.page404`, `setUpUrl(...)` ni `loadPage(...)` fuera del propio `template.js` historico;
- el shell autenticado ya no depende de esa capa desde Fase 20;
- la guest lane viva actual (`/login`, `/url`) tampoco la usa;
- por prudencia se eligio `encapsular y desactivar por defecto`, no borrar a ciegas.

## Auditoria pedida

### `$.ajaxLoad`

- ya no se usa como trigger operativo del shell moderno;
- en guest real queda en `false`;
- ahora solo se activa si existe `data-template-legacy-ajax="enabled"`.

### `$.defaultPage`, `$.subPagesDirectory`, `$.page404`

- no hay definiciones vivas en `app/`, `routes/`, `resources/`, `tests/` ni `scripts/local`;
- quedan como opciones de configuracion residual del namespace encapsulado.

### `setUpUrl(...)` y `loadPage(...)`

- sin callers vivos fuera del propio archivo residual;
- se conservan solo como aliases legacy hacia `window.CentroDeCobrosLegacyAjaxHash`.

### `location.hash`

- el shell moderno ya resuelve esta compatibilidad desde `resources/assets/js/shell/navigation.js`;
- el residual legacy ya no la toma automaticamente.

### `preventDefault` generico sobre `a[href="#"]`

- sigue existiendo solo en forma scopeada dentro de `template.shared.js`;
- deja de interceptar el shell autenticado moderno;
- no vuelve a gobernar header/sidebar/navigation del shell.

## Evidencia de no uso vivo

Busqueda dirigida en `app/`, `routes/`, `resources/`, `tests/` y `scripts/`:

- sin callers externos a:
  - `$.defaultPage`
  - `$.subPagesDirectory`
  - `$.page404`
  - `setUpUrl(...)`
  - `loadPage(...)`
- el unico `location.hash` vivo del shell actual sigue en `resources/assets/js/shell/navigation.js`
- el browser guest confirmo:
  - `$.ajaxLoad = false`
  - `ajaxHashMode = disabled`

## Encapsulacion aplicada

Nuevo modulo:

- `resources/assets/plantilla/js/template.ajax-hash.js`

Contrato residual temporal:

- namespace: `window.CentroDeCobrosLegacyAjaxHash`
- API:
  - `configure({ defaultPage, subPagesDirectory, page404 })`
  - `start()`
  - `setUpUrl(url)`
  - `loadPage(url)`
  - `loadJS(jsFiles, pageScript)`
  - `loadCSS(cssFile, end, callback)`

Autoarranque actual:

- `desactivado`

Condicion de reactivacion:

- `data-template-legacy-ajax="enabled"`

Compatibilidad temporal mantenida:

- `window.setUpUrl`
- `window.loadPage`
- `window.loadJS`
- `window.loadCSS`

## Validacion funcional

### Guest browser

- `/login`
  - `$.ajaxLoad = false`
  - `ajaxHashMode = disabled`
  - `0` errores / `0` warnings
- `/url`
  - `$.ajaxLoad = false`
  - `ajaxHashMode = disabled`
  - `0` errores / `0` warnings

### Shell browser

Validado con sesion autenticada real:

- `/main` -> limpio en `0` errores / `0` warnings
- dropdown de cuenta -> `OK`
- dropdown de notificaciones -> `OK`
- sidebar `Acceso` -> cierra y reabre en `OK`
- `Roles` -> `GET /rol?page=1&buscar=&criterio=nombre` en `200`
- `Clientes` -> `GET /cliente?page=1&buscar=&criterio=nombre&offset=10` en `200`
- `Usuarios` -> `GET /user?page=1&buscar=&criterio=nombre` en `200`
- `Reporte Ingresos SPEI` -> `GET /cliente/selectCliente` y `GET /pagospei/reportePagoSpei?idcliente=0&fechaInicio=null&fechaFin=null` en `200`
- `Reporte Ingresos por Cargos Recurrentes` -> `GET /cliente/selectCliente` y `GET /transaccionDom/reporteTransaccionesDom?idcliente=0&fechaInicio=null&fechaFin=null` en `200`

## Dictamen GO / NO-GO

Dictamen: `GO`

Justificacion:

1. el residual `ajax/hash` no tenia callers vivos comprobables;
2. retirarlo por completo en esta fase seguia siendo mas agresivo que lo necesario;
3. encapsularlo y desactivarlo por defecto elimina la responsabilidad ambigua sin borrar a ciegas;
4. el shell y la guest lane quedaron operativos despues de build, phpunit y browser.

## Recomendacion resultante

Recomendacion unica posterior:

`Fase 25 - seguir modernizando la guest/legacy lane viva para reducir mas agresivamente la dependencia de plantilla.js en superficies publicas`
