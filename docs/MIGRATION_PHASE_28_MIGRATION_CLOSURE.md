# Fase 28 - Migration closure

Fecha: 2026-03-24
Estado final: `GO ejecutado`

## Resumen ejecutivo

La Fase 28 se ejecuto en `C:\temp\centrodecobros_phase28_migration_closure` y cierra la migracion del proyecto dentro del alcance actual.

El corte elegido fue pequeno, puntual, reversible y de cierre:

- retirar del bootstrap legacy publicado el guest branching residual que seguia en `resources/assets/plantilla/js/template.js`;
- simplificar `resources/assets/plantilla/js/template.shared.js` para que ya no dependa de helpers guest retirados;
- mantener `resources/assets/plantilla/js/template.guest.js` solo como residual historico en disco, pero autocontenido para no perder rollback tecnico;
- preservar `principal.blade.php`;
- preservar el contrato publico de assets;
- mantener encapsulado y sin reactivar el residual ajax/hash.

Conclusiones rectoras:

1. `/login` y `/url` ya no tienen ningun acoplamiento funcional a `public/js/plantilla.js`.
2. El guest branching residual de `template.js` ya no tenia callers vivos en surfaces publicadas.
3. La deuda legacy minima aceptada queda encapsulada en:
   - bootstrap legacy autenticado y helpers visuales basicos de `template.js`;
   - compatibilidad minima de `template.shared.js` (`bindNoopAnchors()`, `markActiveNavigationLinks()`, `window.init`);
   - residual `ajax/hash` dormido en `window.CentroDeCobrosLegacyAjaxHash`;
   - `template.guest.js` solo como archivo historico no publicado.
4. La migracion puede declararse `COMPLETADA dentro del alcance actual`.

## Contexto de entrada

- Ruta base usada: `C:\temp\centrodecobros_phase27_template_shared_rationalization`
- Ruta nueva creada: `C:\temp\centrodecobros_phase28_migration_closure`
- La copia aislada se creo correctamente antes de modificar archivos.
- Backend confirmado: Laravel `12.54.1` con PHP `8.2.24`
- Frontend confirmado: Node `20.20.0`, npm `10.8.2`, Vue `3.5.30` puro

## Auditoria del residual guest bootstrap

### Lo que seguia presente al inicio

`resources/assets/plantilla/js/template.js` todavia exponia:

- `selectors.guestContext`
- `selectors.guestSurface`
- `hasGuestContext()`
- `getGuestSurface()`

El caller real restante ya no estaba en views publicadas, sino en residual interno:

- `template.shared.js` usaba `hasGuestContext()` solo para elegir un selector noop scoped;
- `template.guest.js` usaba `selectors.guestContext` y `getGuestSurface()` solo como archivo historico en disco;
- no aparecieron callers en `app/`, `routes/`, `tests/`, `scripts/` ni views que carguen `js/plantilla.js` y expongan `data-template-context="guest"`.

### Evidencia directa

1. `resources/views/auth/contenido.blade.php` y `resources/views/transaccion/contenido.blade.php` cargan solo `js/guest-public.js`.
2. `resources/views/principal.blade.php` sigue siendo la unica superficie publicada que carga `js/plantilla.js`, y es shell autenticado.
3. La busqueda de callers vivos en `resources/`, `tests/`, `scripts/`, `app/` y `routes/` ya no encontro uso publicado de `guestContext`, `guestSurface`, `hasGuestContext()` ni `getGuestSurface()` fuera del residual interno legacy.
4. Browser real confirmo:
   - `/login` y `/url` siguen con `js/guest-public.js` solamente;
   - `window.CentroDeCobrosLegacyTemplate` sigue ausente en esas vistas;
   - `/main` sigue estable con `window.CentroDeCobrosLegacyTemplate.state.ajaxHashMode = "disabled"`.

### Decision del corte

Se eligio una sola frontera principal:

`retirar del bundle legacy publicado el conocimiento guest ya sin callers vivos y encapsular el residual historico no publicado`

Justificacion:

1. Era el ultimo corte pequeno con ROI claro dentro de la migracion activa.
2. No exigia mover `plantilla.*` a Vite.
3. No exigia tocar `principal.blade.php`.
4. No cambiaba contratos de negocio externos.
5. Permitia cerrar la migracion sin abrir una fase extra artificial.

## Cambios aplicados

### `resources/assets/plantilla/js/template.js`

Retirado:

- `selectors.guestContext`
- `selectors.guestSurface`
- `hasGuestContext()`
- `getGuestSurface()`

Conservado como bootstrap legacy minimo aceptado:

- `selectors.authenticatedShell`
- `selectors.noopAnchor`
- `hasAuthenticatedModernShell()`
- `shouldEnableLegacyAjaxHash()`
- `refreshReferences()`
- globals visuales heredados (`$.brand*`, `$.gray*`, panel icons)
- `state.ajaxHashMode`

Medicion:

- baja de `2120` a `1704` bytes
- baja de `48` a `40` lineas

### `resources/assets/plantilla/js/template.shared.js`

Racionalizado para dejar solo compatibilidad minima publicada:

- se elimina el branching guest interno basado en `getScopedSelector(...)`
- `bindNoopAnchors()` queda reducido al selector noop publicado
- se mantienen `markActiveNavigationLinks()`, `bindNoopAnchors()`, `templateState.init()` y `window.init`

Medicion:

- baja de `2172` a `1721` bytes
- baja de `64` a `54` lineas

### `resources/assets/plantilla/js/template.guest.js`

Ajuste de coherencia en archivo no publicado:

- deja de depender de `selectors.guestContext` y `getGuestSurface()` definidos en `template.js`
- pasa a resolver sus propios selectores guest localmente

Razon:

- el archivo permanece solo como residual historico / rollback tecnico y no debe quedar roto por el cierre del bootstrap publicado.

### `public/js/plantilla.js`

Resultado del build de produccion:

- baja de `403683` a `403207` bytes
- reduccion neta publicada de esta fase: `476` bytes

## Legacy residual aceptado tras el cierre

Se acepta explicitamente como deuda residual fuera del alcance actual:

1. `public/css/plantilla.css` y layouts Blade guest siguen siendo contrato visual legacy de `/login` y `/url`.
2. `template.shared.js` retiene solo compatibilidad minima publicada:
   - `bindNoopAnchors()`
   - `markActiveNavigationLinks()`
   - `window.init`
3. `template.ajax-hash.js` permanece encapsulado en `window.CentroDeCobrosLegacyAjaxHash`, opt-in y sin autoarranque.
4. `template.guest.js` permanece en disco solo como residual historico no publicado.
5. `principal.blade.php` se mantiene intacto por restriccion estructural deliberada.

Esto ya no bloquea el cierre de migracion dentro del alcance aprobado.

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
- `public/js/plantilla.js` -> `403207` bytes
- `public/css/plantilla.css` -> `246986` bytes
- `public/js/guest-public.js` -> `1141` bytes

### Backend

- `php artisan route:list` -> `OK`; `97` rutas
- `php artisan schedule:list` -> `OK`; `2` tareas
- `php vendor/bin/phpunit` -> `OK (23 tests, 136 assertions)`

Nota de entorno:

- `phpunit` fallo en el primer intento con `wampmysqld64` detenido;
- el servicio local se levanto temporalmente;
- la suite quedo en verde;
- el servicio se devolvera a `Stopped` al terminar la fase.

### Browser semiautomatico con `playwright-cli`

Servidor local auditado:

- `http://127.0.0.1:8028`

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

## Dictamen de cierre

Decision unica:

`migracion completada dentro del alcance actual`

Fundamento:

1. Laravel `12.54.1` y Vue `3.5.30` puro siguen estables.
2. `app.js` via Vite y `plantilla.*` legacy reproducible mantienen el contrato actual sin drift funcional observado.
3. El residual guest bootstrap ya salio del runtime publicado que quedaba vivo.
4. El residual ajax/hash permanece encapsulado, dormido y sin reactivarse.
5. Lo que queda legacy es deuda residual controlada, no un bloqueador de migracion en el alcance actual.

## Rollback

1. No usar `C:\temp\centrodecobros_phase28_migration_closure` como baseline activa si se desea revertir esta fase.
2. Volver a tomar `C:\temp\centrodecobros_phase27_template_shared_rationalization` como baseline valida anterior.
3. Descartar `C:\temp\centrodecobros_phase28_migration_closure` para revertir completamente la fase.

## Recomendacion unica posterior

Recomendacion unica:

`cerrar la migracion y mover la deuda legacy residual a backlog controlado, sin abrir una Fase 29 rectora`

La deuda residual aceptada ya no pertenece al carril de migracion activa:

- estilos/layout guest legacy;
- contrato fijo de `principal.blade.php`;
- residual ajax/hash opt-in;
- lane `plantilla.*` aun fuera de Vite por decision de alcance;
- realtime, hardening y remediacion de vulnerabilidades como pistas separadas.
