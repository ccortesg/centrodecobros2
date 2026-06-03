# Fase 23 - Guest lane modernization

Fecha: 2026-03-24
Estado final: `GO ejecutado`

## Resumen ejecutivo

La Fase 23 cerro en `GO`.

La auditoria confirmo que la guest lane viva actual no es "toda transaccion" ni el flujo verify/SMS historico. Lo realmente vivo y comprobable en esta copia es:

- `/login` -> `resources/views/auth/login.blade.php`
- `/url` -> `resources/views/transaccion/url.blade.php`

Hallazgo importante:

- `resources/views/transaccion/register.blade.php` sigue existiendo en disco, pero `GET /` ya no renderiza esa vista; `TransaccionController@showForm()` redirige al flujo autenticado y por eso `register` se trata como blade residual, no como guest entrypoint viva del programa actual.
- verify/SMS queda explicitamente excluido porque ya fue retirado en Fase 21.

## Estrategia aplicada

Se eligio un corte pequeno y reversible:

1. marcar las guest views reales con contexto explicito;
2. mover su init a un carril mas controlado dentro de la lane legacy;
3. evitar que la guest lane siga pasando por wiring pensado para shell o hash/ajax.

## Cambios aplicados

### Layouts guest

Actualizados:

- `resources/views/auth/contenido.blade.php`
- `resources/views/transaccion/contenido.blade.php`

Nuevos atributos:

- `data-template-context="guest"`
- `data-template-view="auth"` o `data-template-view="transaccion"`
- `data-template-surface="guest"`

### Pantallas guest auditadas

Actualizados:

- `resources/views/auth/login.blade.php`
- `resources/views/transaccion/register.blade.php`
- `resources/views/transaccion/url.blade.php`

Nuevos atributos:

- `data-template-screen="login"`
- `data-template-screen="register"`
- `data-template-screen="url"`

### Init guest controlado

Nuevo modulo:

- `resources/assets/plantilla/js/template.guest.js`

Responsabilidades:

- detectar guest context real;
- registrar `data-template-guest-ready="true"`;
- registrar la pantalla activa mediante `data-template-guest-screen-active`;
- inicializar solo la superficie guest auditada.

## Dependencias quitadas o acotadas

Reduccion aplicada:

1. las guest views ya no dependen de inferencias implicitas de shell;
2. la intercepcion `a[href="#"]` queda scopeada y deja de ser un binding ambiguo para guest + shell;
3. la guest lane ya no comparte el autoarranque del residual ajax/hash.

## jQuery inline

Resultado de auditoria:

- no se encontro jQuery inline vivo en `/login` ni en `/url`;
- por eso no se requirio extraer scripts inline de esas vistas;
- el corte correcto fue aislar su init y sus marcas de contexto, no reescribir formularios que ya estaban limpios.

## Pruebas y validacion browser

### Pruebas automatizadas

Se reforzaron smoke tests:

- `tests/Feature/Smoke/PublicRoutesSmokeTest.php`
- `tests/Feature/Smoke/LegacyFunctionalAlignmentSmokeTest.php`

Nuevas aserciones:

- presencia de `data-template-context="guest"`
- presencia de `data-template-view="auth"` / `data-template-view="transaccion"`
- presencia de `data-template-screen="login"` / `data-template-screen="url"`

### Browser

Validado con `playwright-cli`:

1. `/login`
   - `guestView = auth`
   - `guestScreen = login`
   - `data-template-guest-ready = true`
   - `$.ajaxLoad = false`
   - consola en `0` errores / `0` warnings
2. `/url`
   - `guestView = transaccion`
   - `guestScreen = url`
   - `data-template-guest-ready = true`
   - `$.ajaxLoad = false`
   - consola en `0` errores / `0` warnings

## Dictamen GO / NO-GO

Dictamen: `GO`

Justificacion:

1. la guest lane viva quedo acotada por evidencia a `/login` y `/url`;
2. se modernizo por cortes pequenos y reversibles;
3. no se rompio el contrato de assets;
4. no se toco `principal.blade.php`;
5. verify/SMS no se reabrio.

## Resultado final de Fase 23

La guest lane sigue siendo legacy porque aun carga `public/js/plantilla.js`, pero ahora esta identificada, inicializada y documentada como carril propio en vez de depender de un `template.js` monolitico.
