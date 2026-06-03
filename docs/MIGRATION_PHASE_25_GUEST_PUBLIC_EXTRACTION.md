# Fase 25 - Guest public extraction

Fecha: 2026-03-24
Estado final: `GO ejecutado`

## Resumen ejecutivo

La Fase 25 cerro en `GO`.

El corte seleccionado fue pequeno, reversible y medible:

- `/login` y `/url` dejan de cargar `public/js/plantilla.js`;
- ambas pantallas pasan a un runtime dedicado y minimo: `public/js/guest-public.js`;
- `public/css/plantilla.css` se conserva como contrato visual;
- `principal.blade.php` no cambia;
- `public/js/plantilla.js` sigue existiendo sin cambios de contrato para el shell autenticado y la lane legacy restante.

La reduccion real sobre la guest lane viva es fuerte:

- antes: `/login` y `/url` descargaban `public/js/plantilla.js` en produccion (`405445` bytes) con `jquery`, `bootstrap`, `Chart`, `pace`, `template.js`, `template.shared.js`, `template.guest.js`, `template.ajax-hash.js` y `sweetalert2`;
- ahora: `/login` y `/url` descargan `public/js/guest-public.js` en produccion (`1141` bytes), sin jQuery ni globals legacy.

Medicion del recorte:

- reduccion por pagina viva guest: `404304` bytes menos de JS descargado;
- reduccion relativa del payload JS guest: `99.72%`.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase22_24_template_guest_hash_program`
- Ruta nueva creada: `C:\temp\centrodecobros_phase25_guest_public_extraction`
- La copia aislada se creo correctamente antes de modificar archivos.
- Backend confirmado: Laravel `12.54.1` con PHP `8.2.24`
- Frontend confirmado: Node `20.20.0`, npm `10.8.2`, Vue `3.5.30` puro

## Auditoria de la guest lane viva real

Superficies publicas vivas confirmadas:

1. `/login` -> `resources/views/auth/login.blade.php`
2. `/url` -> `resources/views/transaccion/url.blade.php`

Superficie residual en disco, no tratada como entrypoint publico vivo:

- `resources/views/transaccion/register.blade.php`

### Que gobernaba hoy a `/login` y `/url`

#### `resources/assets/plantilla/js/template.js`

Gobernaba solo bootstrap/config legacy:

- selectors guest;
- deteccion de contexto guest;
- `ajaxHashMode = disabled` en guest;
- referencias `$.mainContent` / `$.navigation`;
- colores globals.

Conclusiones:

- `/login` y `/url` no necesitaban esos globals para funcionar;
- la unica parte util para guest era la deteccion de contexto, reemplazable por un runtime propio mucho mas pequeno.

#### `resources/assets/plantilla/js/template.shared.js`

Gobernaba:

- `bindNoopAnchors()` para `a[href="#"]`;
- `initScopedUi()` para `tooltip` / `popover`;
- `card-actions`;
- helpers `window.init` y `window.capitalizeFirstLetter`.

Hallazgo:

- ninguna de esas responsabilidades tenia caller real en `/login` ni en `/url`;
- no hay `tooltip`, `popover`, `dropdown`, `modal`, `card-actions`, `href="#"`, scripts inline ni jQuery inline en esas vistas.

#### `resources/assets/plantilla/js/template.guest.js`

Gobernaba:

- `data-template-guest-ready`;
- `data-template-guest-screen-active`;
- init del carril guest detectado por `data-template-context`.

Hallazgo:

- esta era la unica logica realmente viva y especifica de `/login` y `/url`;
- su superficie funcional era pequena y totalmente separable de `plantilla.js`.

#### `resources/assets/plantilla/js/template.ajax-hash.js`

Estado auditado:

- seguia encapsulado;
- no tenia callers vivos en `/login` ni `/url`;
- no se reactivo;
- se mantuvo `ajaxHashMode = disabled`.

### Dependencias reales previas de `public/js/plantilla.js`

Antes del corte, la guest lane viva seguia cargando innecesariamente:

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

Dependencias globales heredadas previas:

- `window.jQuery`
- `window.$`
- `window.CentroDeCobrosLegacyTemplate`
- aliases legacy del residual ajax/hash

## Corte seleccionado

Se eligio un solo corte pequeno y reversible:

- extraer la inicializacion viva de `/login` y `/url` a `resources/assets/js/guest-public.js`;
- compilarla a `public/js/guest-public.js` mediante un script dedicado;
- hacer que los layouts guest carguen `guest-public.js` en lugar de `plantilla.js`;
- conservar `plantilla.css`, `plantilla.js` y `app.js` como contrato publico existente;
- no tocar `principal.blade.php`.

## Cambios aplicados

### Nuevo runtime guest publico

Nuevo archivo:

- `resources/assets/js/guest-public.js`

Responsabilidades:

- detectar `data-template-context="guest"`;
- marcar `data-template-guest-ready="true"`;
- marcar `data-template-guest-screen-active`;
- marcar `data-template-runtime="guest-public"`;
- exponer `window.CentroDeCobrosGuestPublic`.

### Build reproducible

Nuevo script:

- `scripts/local/build_guest_public_lane.js`

Ajuste del build principal:

- `scripts/local/run_phase15_build.js`

Nuevo artefacto generado:

- `public/js/guest-public.js`

### Views guest

Actualizados:

- `resources/views/auth/contenido.blade.php`
- `resources/views/transaccion/contenido.blade.php`

Cambio:

- reemplazo de `js/plantilla.js` por `js/guest-public.js`

### Pruebas smoke

Actualizados:

- `tests/Feature/Smoke/PublicRoutesSmokeTest.php`
- `tests/Feature/Smoke/LegacyFunctionalAlignmentSmokeTest.php`

Nuevas garantias:

- `/login` y `/url` ahora amarran `js/guest-public.js`;
- `/login` y `/url` ya no amarran `js/plantilla.js`.

### Ajuste de entorno en la copia aislada

Archivo actualizado:

- `.env`

Cambio:

- `DB_HOST=localhost`

Justificacion:

- en este host `127.0.0.1:3306` estaba interceptado por `wslrelay`;
- `localhost` conecto correctamente con el MySQL local de WAMP y permitio repetir `phpunit` y la bateria browser autenticada.

## Validaciones ejecutadas

### Entorno

- `php artisan --version` -> `Laravel Framework 12.54.1`
- `composer show laravel/framework` -> `v12.54.1`
- `node -v` -> `v20.20.0`
- `npm -v` -> `10.8.2`

### Build y backend

- `npm ci` -> `OK`; se mantienen `7` vulnerabilidades heredadas del lane legacy
- `npm run development` -> `OK`
- `npm run production` -> `OK`
- `php artisan route:list` -> `OK`; `97` rutas
- `php artisan schedule:list` -> `OK`; `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 136 assertions)`

Artefactos verificados:

- `public/js/app.js` -> `1859` bytes
- `public/js/plantilla.js` -> `405445` bytes
- `public/css/plantilla.css` -> `246986` bytes
- `public/js/guest-public.js` -> `1141` bytes

### Browser semiautomatico con `playwright-cli`

Servidor local auditado:

- `http://127.0.0.1:8025`

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
- `window.CentroDeCobrosLegacyTemplate` ausente
- `window.jQuery` ausente
- POST seguro validado con `https://example.com`
- `iframe` renderizado correctamente
- consola del modulo: `0` errores / `0` warnings

#### `/main` y shell autenticado

- scripts cargados: `js/app.js`, asset Vite hasheado y `js/plantilla.js`
- consola: `0` errores / `0` warnings
- dropdown de cuenta: `OK`
- dropdown de notificaciones: `OK`
- sidebar `Acceso`: `OK`

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

## Estado resultante de la guest lane

### Que dejo de depender de `plantilla.js`

`/login` y `/url` dejan de depender de:

- `template.js`
- `template.shared.js`
- `template.guest.js`
- `template.ajax-hash.js`
- `jquery`
- `bootstrap` JS
- `Chart`
- `pace`
- `sweetalert2`

### Que sigue legacy

Sigue legacy despues del corte:

- `public/css/plantilla.css` como contrato visual de guest;
- `public/js/plantilla.js` para el shell autenticado y la lane legacy restante;
- `template.js` / `template.shared.js` dentro del bundle legacy autenticado;
- `template.guest.js` como residuo dentro de `plantilla.js`, pero ya sin uso en la guest lane publica viva;
- `register.blade.php` como blade residual en disco.

### Estado del residual ajax/hash

- permanece encapsulado;
- permanece desactivado por defecto;
- no fue reactivado;
- no hubo evidencia nueva que justificara tocarlo.

### Estado de `principal.blade.php`

- intacto;
- sin cambios.

## Recomendacion unica posterior

Recomendacion unica:

`Fase 26 - abrir una racionalizacion controlada de la lane restante de plantilla.*`

Justificacion:

- la deuda publica viva de JS guest ya salio de `plantilla.js`;
- el siguiente ROI ya no esta en `/login` o `/url`, sino en la racionalizacion del bundle legacy restante que todavia sostiene shell compartido y utilidades heredadas.
