# Analisis frontend

Ultima actualizacion: 2026-06-04

## Estructura actual

- Entrada principal: `resources/assets/js/app.js`.
- Componentes Vue 3: `resources/assets/js/components/*.vue`.
- Bootstrap frontend: `resources/assets/js/bootstrap.js`.
- Shell visual server-side: `resources/views/principal.blade.php`, sidebars y `resources/views/contenido/contenido.blade.php`.
- Build: Vite para app principal, scripts locales para lane legacy y guest.

## Mezcla tecnologica

- Vue 3 para vistas de negocio autenticadas.
- Blade para composicion/layout y vistas publicas.
- jQuery/Bootstrap legacy en `plantilla.js`.
- `plantilla.css`, `plantilla.js` y `guest-public.js` se preservan como contrato publico generado.

## Dependencias fuertes del backend

- Endpoints legacy por ruta directa (`/transaccion/...`, `/cliente/...`, `Service/*`).
- Menu por enteros/targets acoplado a `contenido.blade.php`.
- Payloads y nombres de campos dependen de contratos backend historicos.
- `principal.blade.php` carga assets por rutas fijas; no tocar sin validacion.

## Formularios criticos

- Alta de transacciones: liga, domiciliacion, SPEI, lector/terminal.
- Clientes y archivos.
- Importacion masiva de ligas/domiciliacion.
- Reportes y exportaciones.
- Login y `/url` guest mediante lane `guest-public.js`.

## Flujos AJAX

- Axios para CRUD, reportes, importacion y exportaciones.
- Descargas por `responseType: 'blob'` en exportables.
- Polling de notificaciones por `notification/get`.
- Echo/Pusher solo si `VITE_PUSHER_APP_KEY` existe; realtime E2E sigue pendiente.

## Riesgos detectados

- Alto acoplamiento rutas-campos-componentes.
- Convivencia de Vue 3 + assets legacy aumenta costo de mantenimiento.
- Sin tipado/contratos frontend formalizados.
- Cambios en menu o assets pueden romper el shell autenticado.

## Convenciones UX/UI vigentes

Desde la ejecucion UX/UI del 2026-06-04 existe una base compartida no invasiva para migrar listados por etapas:

- `resources/assets/js/app.js` importa `resources/assets/js/styles/ux-ui.css` para que Vite empaquete estilos nuevos sin tocar `principal.blade.php`.
- Helpers globales Vue disponibles:
  - `$formatDateMx(value)` para `dd-mm-yyyy`;
  - `$formatTimeMx(value)` para `hh:mm:ss`;
  - `$formatDateTimeMx(value)` para `{ date, time }`;
  - `$paginationPages(pagination, radius = 2)` para paginacion compacta.
- Clases CSS opt-in:
  - `.cdc-list-toolbar` para filtros responsivos;
  - `.cdc-table-shell` para wrapper de tabla con scroll horizontal visible;
  - `.cdc-responsive-table` para normalizar celdas;
  - `.cdc-pagination` para paginacion compacta con wrap;
  - `.cdc-date-stack` y `.cdc-date-stack__time` para fecha y hora en dos lineas;
  - `.cdc-action-button` para botones icon-only con area tactil minima.
- Primer piloto aplicado en `Estado.vue` y `Ciudad.vue`; los demas modulos deben migrarse por prioridad, manteniendo los contratos backend y rutas existentes.

## Proxy HTTPS y logout

`resources/views/principal.blade.php` debe seguir usando `route('logout')`. Si la aplicacion se publica detras de Docker/reverse proxy, Laravel debe confiar en `X-Forwarded-Proto` y `X-Forwarded-Host` para no generar `http://.../logout` dentro de una pagina HTTPS. El ajuste vigente esta en `app/Http/Middleware/TrustProxies.php`.

## Convencion de filtros financieros

Los filtros de listados financieros deben mapearse a columnas reales, no a nombres genericos de UI:

- `status` solo debe usarse cuando la tabla tenga una columna real `status`, por ejemplo `respuestas.status` o `transaccionesDom.status`.
- `condicion` representa el estado operativo en tablas como `transacciones` y `pagospei`; si el UI muestra encabezado `Status`, el backend puede recibir `status` por compatibilidad, pero debe traducirlo a `transacciones.condicion` cuando esa sea la columna real.
- `enviada` debe usarse para columnas reales `enviada`, como `pagospei.enviada` y `cancelaspei.enviada`.
- Todo filtro nuevo debe validar valores permitidos antes de aplicar `where(...)`; un valor invalido debe responder 422 controlado, no construir SQL dinamico ni producir HTTP 500.

## Pruebas recomendadas

- `npm run production`
- Browser smoke de `/login`, `/url`, `/main`, sidebar/topbar y modulos tocados.
- Validar consola sin errores criticos.
- Si cambia realtime, validar `window.Echo`, `window.Pusher` y `broadcasting/auth` con credenciales aisladas.
