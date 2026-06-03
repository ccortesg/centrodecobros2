# Fase 26 - Template rationalization

Fecha: 2026-03-24
Estado final: `GO ejecutado`

## Resumen ejecutivo

La Fase 26 se ejecuto en `C:\temp\centrodecobros_phase26_template_rationalization` y cerro en `GO`.

El corte elegido fue pequeno, reversible y medible:

- retirar `resources/assets/plantilla/js/template.guest.js` del bundle legacy publicado `public/js/plantilla.js`;
- mantener el archivo en disco como evidencia historica y rollback tecnico;
- no tocar `principal.blade.php`;
- no cambiar el contrato publico de assets;
- no reactivar el residual ajax/hash;
- no abrir todavia una racionalizacion mayor de `template.shared.js`.

El recorte se apoya en evidencia directa:

1. `/login` y `/url` ya corren sobre `public/js/guest-public.js`;
2. `resources/views/transaccion/register.blade.php` sigue en disco, pero tampoco carga `js/plantilla.js`;
3. no hay vistas guest reales restantes que sigan descargando `public/js/plantilla.js`;
4. `template.guest.js` no tiene callers externos a si mismo.

Medicion del recorte:

- `public/js/plantilla.js` en produccion baja de `405445` bytes a `404791` bytes;
- reduccion neta del bundle legacy publicado: `654` bytes;
- la cadena JS legacy publicada pasa de `10` a `9` fuentes concatenadas.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase25_guest_public_extraction`
- Ruta nueva creada: `C:\temp\centrodecobros_phase26_template_rationalization`
- La copia aislada se creo correctamente antes de modificar archivos.
- Backend confirmado: Laravel `12.54.1` con PHP `8.2.24`
- Frontend confirmado: Node `20.20.0`, npm `10.8.2`, Vue `3.5.30` puro

## Auditoria de la lane restante de `plantilla.*`

### `resources/assets/plantilla/js/template.js`

Sigue vivo como bootstrap minimo del lane legacy:

- define `window.CentroDeCobrosLegacyTemplate`;
- mantiene selectors y helpers compartidos;
- decide `ajaxHashMode`;
- refresca `$.mainContent` y `$.navigation`;
- mantiene globals visuales heredados (`$.brand*`, `$.gray*`, panel icons).

Hallazgo:

- sigue cargando en `/main` porque `principal.blade.php` sigue amarrado a `js/plantilla.js`;
- sus ramas guest ya no gobiernan superficies publicas vivas;
- el valor observado en browser para shell fue `ajaxHashMode = "disabled"`.

### `resources/assets/plantilla/js/template.shared.js`

Mantiene estas responsabilidades dentro del bundle legacy:

- `bindNoopAnchors()`;
- `markActiveNavigationLinks()`;
- `initScopedUi()` para tooltip/popover;
- handler de `.card-actions a`;
- `window.init`;
- `window.capitalizeFirstLetter`.

Hallazgo de uso vivo:

- no aparecieron callers externos para `window.capitalizeFirstLetter`;
- `window.init` sigue enlazado solo por compatibilidad con `template.ajax-hash.js`;
- no aparecieron surfaces auditadas con `tooltip`, `popover` ni `card-actions`;
- header, sidebar y navegacion shell auditados ya no dependen de este archivo por las guardas de shell moderno.

Conclusion:

- `template.shared.js` permanece como residual controlado dentro del bundle legacy;
- no fue el corte elegido en esta fase para no mezclar dos fronteras.

### `resources/assets/plantilla/js/template.guest.js`

Responsabilidad historica:

- marcar `data-template-guest-ready`;
- marcar `data-template-guest-screen-active`;
- reusar `bindNoopAnchors()` e `initScopedUi()` sobre superficie guest.

Evidencia actual:

- `/login` y `/url` cargan solo `js/guest-public.js`;
- `register.blade.php` tambien carga `js/guest-public.js`;
- no existe ninguna vista auditada que siga cargando `js/plantilla.js` y exponga `data-template-context="guest"`;
- no aparecen callers externos a `templateState.guest`.

Conclusion:

- `template.guest.js` ya no era necesario dentro de `public/js/plantilla.js`;
- esta fase lo saca del bundle legacy publicado.

### `resources/assets/plantilla/js/template.ajax-hash.js`

Estado auditado:

- sigue encapsulado en `window.CentroDeCobrosLegacyAjaxHash`;
- no tiene callers externos comprobados;
- no autoarranca;
- conserva `window.init` como compatibilidad residual con `template.shared.js`.

Resultado:

- permanecio encapsulado y sin reactivarse;
- no se toco funcionalmente en esta fase.

### `public/js/plantilla.js`

Uso real restante comprobado en la plataforma actual:

- `/main` y shell autenticado siguen cargandolo por contrato estructural;
- conserva vendor legacy (`jquery`, `bootstrap`, `Chart`, `pace`, `sweetalert2`);
- conserva `template.js`, `template.shared.js` y `template.ajax-hash.js`;
- ya no contiene `template.guest.js`.

Verificacion por contenido del artefacto publicado:

- ausente `data-template-guest-ready`;
- ausente `data-template-guest-screen-active`;
- ausente `CentroDeCobrosLegacyTemplate.guest`;
- ausente `template.guest.js`.

## Corte seleccionado

Se eligio una sola frontera principal:

`retirar template.guest.js del bundle legacy restante`

Justificacion:

1. era el recorte con mejor ROI inmediato y con evidencia mas fuerte;
2. no requeria tocar `principal.blade.php`;
3. no cambiaba nombres de salida ni el contrato publico;
4. evitaba mezclar en la misma fase la discusion mas ambigua de `template.shared.js`;
5. dejaba la siguiente fase mejor enfocada.

## Cambios aplicados

### Build legacy

Actualizados:

- `scripts/local/build_legacy_lane.js`
- `webpack.mix.js`

Cambio:

- se elimina `resources/assets/plantilla/js/template.guest.js` del orden de concatenacion JS de `public/js/plantilla.js`.

### Superficies no tocadas

Se mantuvieron intactos:

- `resources/views/principal.blade.php`
- `resources/views/auth/contenido.blade.php`
- `resources/views/transaccion/contenido.blade.php`
- `resources/assets/js/guest-public.js`
- `resources/assets/plantilla/js/template.js`
- `resources/assets/plantilla/js/template.shared.js`
- `resources/assets/plantilla/js/template.ajax-hash.js`

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
- `public/js/plantilla.js` -> `404791` bytes
- `public/css/plantilla.css` -> `246986` bytes
- `public/js/guest-public.js` -> `1141` bytes

### Backend

- `php artisan route:list` -> `OK`; `97` rutas
- `php artisan schedule:list` -> `OK`; `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 136 assertions)`

Nota de entorno:

- `phpunit` fallo en el primer intento porque `wampmysqld64` estaba detenido;
- se levanto temporalmente el servicio local y luego la suite quedo en verde.

### Browser semiautomatico con `playwright-cli`

Servidor local auditado:

- `http://127.0.0.1:8010`

#### `/login`

- scripts cargados: solo `js/guest-public.js`
- `data-template-guest-ready = true`
- `data-template-guest-screen-active = login`
- `data-template-runtime = guest-public`
- `window.CentroDeCobrosLegacyTemplate` ausente
- `window.jQuery` ausente
- consola: `0` errores / `0` warnings

#### `/url`

- scripts cargados: solo `js/guest-public.js`
- `data-template-guest-ready = true`
- `data-template-guest-screen-active = url`
- `data-template-runtime = guest-public`
- POST seguro validado con `https://example.com`
- `iframe` renderizado correctamente
- consola: `0` errores / `0` warnings

#### `/main` y shell autenticado

- scripts cargados: `js/app.js`, asset Vite hasheado y `js/plantilla.js`
- `window.CentroDeCobrosLegacyTemplate.state.ajaxHashMode = "disabled"`
- dropdown de cuenta: `OK`
- dropdown de notificaciones: `OK`
- sidebar `Acceso`: cierra y reabre en `OK`
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

## Estado final de la lane restante

### Lo que dejo de ser necesario en `public/js/plantilla.js`

- todo `template.guest.js` salio del bundle publicado;
- `public/js/plantilla.js` ya no contiene logica de marcas guest activas;
- la guest publica viva sigue totalmente separada en `public/js/guest-public.js`.

### Lo que sigue vivo o retenido

- `template.js` como bootstrap minimo legacy;
- `template.shared.js` como residual controlado y no recortado aun;
- `template.ajax-hash.js` encapsulado y dormido;
- vendor legacy del shell autenticado;
- `public/css/plantilla.css` como contrato visual;
- `public/js/plantilla.js` como contrato JS legacy del shell autenticado.

### Lo que queda pendiente

- racionalizar `template.shared.js`;
- revisar despues el guest branching residual que sigue en `template.js`;
- decidir mas adelante si `window.init` y la compatibilidad `ajax/hash` permiten otro recorte controlado.

## Estado del contrato de assets

Preservado sin cambios:

- `public/js/app.js`
- `public/js/plantilla.js`
- `public/css/plantilla.css`
- `public/js/guest-public.js`

`principal.blade.php` queda intacto.

## Recomendacion unica posterior

Recomendacion unica:

`Fase 27 - racionalizar de forma puntual template.shared.js dentro de la lane legacy restante`

Justificacion:

- `template.guest.js` ya salio del bundle;
- el siguiente ROI ya no esta en guest publica;
- la siguiente deuda claramente acotable esta en `template.shared.js`, en especial `window.init`, tooltip/popover, `card-actions` y el binding noop residual.
