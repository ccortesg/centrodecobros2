# Fase 22 - Template segmentation

Fecha: 2026-03-24
Estado final: `GO ejecutado`

## Resumen ejecutivo

La Fase 22 se ejecuto dentro del programa corto `C:\temp\centrodecobros_phase22_24_template_guest_hash_program` y cerro en `GO`.

El objetivo fue dejar de tratar `resources/assets/plantilla/js/template.js` como un archivo monolitico y explicitar, sin romper el contrato publico de assets, las tres zonas que seguian mezcladas:

1. bootstrap/config legacy minimo;
2. guest lane viva y utilidades vendor compartidas;
3. residuo ajax/hash no-shell.

La segmentacion aplicada deja `template.js` como bootstrap acotado y mueve el resto a archivos separados dentro de la misma lane legacy:

- `resources/assets/plantilla/js/template.shared.js`
- `resources/assets/plantilla/js/template.guest.js`
- `resources/assets/plantilla/js/template.ajax-hash.js`

El contrato publico se preservo intacto:

- `public/js/app.js`
- `public/js/plantilla.js`
- `public/css/plantilla.css`

`principal.blade.php` queda intacto.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase21_verify_cleanup_assessment`
- Ruta nueva creada: `C:\temp\centrodecobros_phase22_24_template_guest_hash_program`
- Backend confirmado: Laravel `12.54.1` con PHP `8.2.24`
- Frontend confirmado: Vue `3.5.30` puro, Node `20.20.0`, npm `10.8.2`

## Auditoria del `template.js` de Fase 21

Clasificacion del archivo de entrada `resources/assets/plantilla/js/template.js`:

1. `shell ya modernizado`
   - deteccion del shell moderno autenticado;
   - `preventDefault` generico sobre `a[href="#"]` para el shell;
   - `markActiveNavigationLinks()` sobre `nav > ul.nav`;
   - `$.ajaxLoad` condicionado al shell moderno.
2. `guest vivo`
   - ninguna guest view viva consumia `setUpUrl()` o `loadPage()`;
   - `/login` y `/url` si seguian cargando `plantilla.js`;
   - la lane guest dependia mas de Bootstrap/jQuery vendor que del hash/ajax.
3. `vendor/utilitario vivo`
   - colores y panel icons globales;
   - `card-actions`;
   - `tooltip` / `popover`;
   - helpers como `capitalizeFirstLetter`.
4. `ajax/hash residual no-shell`
   - `loadJS()`
   - `loadCSS()`
   - `setUpUrl()`
   - `loadPage()`
   - `$.defaultPage`
   - `$.subPagesDirectory`
   - `$.page404`
   - bootstrap por `location.hash`
5. `deuda removible`
   - autoarranque implicito del carril ajax/hash;
   - globals legacy sin callers vivos en codigo actual;
   - mezcla de guest y shell dentro del mismo archivo.

## Segmentacion aplicada

### 1. `resources/assets/plantilla/js/template.js`

Queda como bootstrap/config minimo:

- selectors compartidos;
- deteccion de shell autenticado moderno;
- deteccion de contexto guest;
- decision de autoarranque residual `ajaxHashMode`;
- referencias legacy `$.mainContent`, `$.navigation`;
- colores y panel icons globales.

Medicion del recorte:

- Fase 21: `222` lineas y `6805` bytes
- Fase 22: `49` lineas y `2156` bytes

### 2. `resources/assets/plantilla/js/template.shared.js`

Nuevo modulo para `vendor/utilitario vivo`:

- `markActiveNavigationLinks()` solo fuera del shell moderno;
- scoping de `a[href="#"]` para evitar reinterceptar el shell autenticado;
- `tooltip` / `popover`;
- `card-actions`;
- `window.init` y `window.capitalizeFirstLetter` por compatibilidad.

### 3. `resources/assets/plantilla/js/template.guest.js`

Nuevo modulo para `guest vivo`:

- inicializacion explicita del guest context;
- marcas `data-template-guest-ready` y `data-template-guest-screen-active`;
- uso de `data-template-context`, `data-template-view`, `data-template-surface` y `data-template-screen`.

### 4. `resources/assets/plantilla/js/template.ajax-hash.js`

Nuevo modulo para el residuo `ajax/hash residual no-shell`:

- encapsula `loadJS`, `loadCSS`, `setUpUrl`, `loadPage`;
- publica el namespace `window.CentroDeCobrosLegacyAjaxHash`;
- mantiene aliases globales legacy solo por compatibilidad temporal;
- elimina el autoarranque por defecto;
- solo puede reactivarse con `data-template-legacy-ajax="enabled"` mas `configure()` / `start()`.

## Cambios de build para sostener la segmentacion

Actualizados:

- `scripts/local/build_legacy_lane.js`
- `webpack.mix.js`

Nuevo orden JS de la lane legacy:

1. `jquery.min.js`
2. `popper.min.js`
3. `bootstrap.min.js`
4. `Chart.min.js`
5. `pace.min.js`
6. `template.js`
7. `template.shared.js`
8. `template.guest.js`
9. `template.ajax-hash.js`
10. `sweetalert2.all.js`

## Dictamen GO / NO-GO

Dictamen: `GO`

Justificacion:

1. la responsabilidad real de `template.js` ya no queda mezclada en un solo archivo;
2. guest, utilidades y residual ajax/hash quedaron separados y documentables;
3. el contrato de assets se preservo;
4. `principal.blade.php` no requirio cambios;
5. build, phpunit y browser focalizado siguieron en verde.

## Resultado final de Fase 22

`template.js` deja de ser el contenedor ambiguo de shell moderno, guest y hash/ajax residual.

Queda una segmentacion minima, explicita y reversible dentro de la misma lane legacy.
