# Analisis frontend

Ultima actualizacion: 2026-06-17

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
  - `.cdc-table-footer` y `.cdc-table-total` para paginacion + total de registros;
  - `.cdc-status-filter-heading` para poner el select debajo del texto `Status`;
  - `.cdc-column-description` para descripciones largas con ancho controlado;
  - `.cdc-sticky-col` para mantener acciones visibles dentro de tablas con scroll horizontal.
- Primer piloto aplicado en `Estado.vue` y `Ciudad.vue`; los demas modulos deben migrarse por prioridad, manteniendo los contratos backend y rutas existentes.

## Sesion expirada

`resources/assets/js/app.js` tiene un interceptor global de Axios para la sesion expirada:

- Detecta respuestas 401/419.
- Detecta respuestas HTML redirigidas a `/login` en peticiones AJAX.
- Muestra modal `Tu sesión caducó por inactividad` con SweetAlert2.
- Usa backdrop negro al 80% mediante `backdrop: 'rgba(0,0,0,0.8)'` y `.cdc-session-expired-active`.
- Al confirmar `OK`, limpia contenedores SweetAlert y redirige a `/login`.

Los componentes no deben duplicar este manejo; los `catch` locales deben continuar enfocados en errores funcionales del modulo.

## Iconografia

La iconografia visible en sidebars y componentes tocados se migro a FontAwesome (`fa fa-*`) porque ya se compila en `public/css/plantilla.css` y reduce el riesgo de glifos privados de `Simple Line Icons` si el servidor no entrega la fuente correcta.

Convenciones:

- Usar `fa fa-plus-circle` para crear.
- Usar `fa fa-search` para buscar.
- Usar `fa fa-cloud-upload` y `fa fa-cloud-download` para importar/exportar.
- Usar `fa fa-pencil`, `fa fa-eye`, `fa fa-trash`, `fa fa-times`, `fa fa-save` para acciones CRUD.
- Usar `title` y `aria-label` en botones icon-only.
- Mantener `public/fonts/fontawesome-*` publicado junto con `public/css/plantilla.css`; si vuelven glifos raros, revisar MIME/paths de `/fonts/*.woff2` en el vhost antes de cambiar componentes.

## Proxy HTTPS y logout

`resources/views/principal.blade.php` debe seguir usando `route('logout')`. Si la aplicacion se publica detras de Docker/reverse proxy, Laravel debe confiar en `X-Forwarded-Proto` y `X-Forwarded-Host` para no generar `http://.../logout` dentro de una pagina HTTPS. El ajuste vigente esta en `app/Http/Middleware/TrustProxies.php`.

## Convencion de filtros financieros

Los filtros de listados financieros deben mapearse a columnas reales, no a nombres genericos de UI:

- `status` solo debe usarse cuando la tabla tenga una columna real `status`, por ejemplo `respuestas.status` o `transaccionesDom.status`.
- `condicion` representa el estado operativo en tablas como `transacciones` y `pagospei`; si el UI muestra encabezado `Status`, el backend puede recibir `status` por compatibilidad, pero debe traducirlo a `transacciones.condicion` cuando esa sea la columna real.
- `enviada` debe usarse para columnas reales `enviada`, como `pagospei.enviada` y `cancelaspei.enviada`.
- Todo filtro nuevo debe validar valores permitidos antes de aplicar `where(...)`; un valor invalido debe responder 422 controlado, no construir SQL dinamico ni producir HTTP 500.

## Nuevos modulos financieros

### Domiciliacion Activa

- Componente: `resources/assets/js/components/DomiciliacionActiva.vue`.
- Menu: `menu==29`.
- Endpoint: `GET /domiciliacion-activa`.
- Fuente: `transacciones.tipo=2`, `productivo=1`, `condicion in (1,2)` y existencia de `respuestas.status='approved'`.
- Acciones:
  - cancelar reutiliza `PUT /transaccion/rechazar`;
  - cargo manual reutiliza `POST /transaccionDom/registrar`.
- La cancelacion debe mostrarse como exitosa cuando el endpoint responde HTTP 200 con `msg` exitoso; el backend normaliza a `error=""` si la cancelacion quedo persistida.

### Pagos Recibidos

- Componente: `resources/assets/js/components/PagoRecibido.vue`.
- Menu: `menu==30`.
- Endpoints:
  - `GET /pagos-recibidos`;
  - `PUT /pagos-recibidos/status`.
- Fuente unificada:
  - `respuestas.status='approved'`;
  - `pagospei` exitosos (`condicion=1` y mensaje/codigo de operacion exitosa);
  - `transaccionesDom.status='approved'` como canal `Cargo Recurrente`.
- El listado usa `monto` normalizado desde backend: `respuestas.amount` no se divide, mientras `pagospei.monto`, `transacciones.Amount` y `transaccionesDom.Amount` se dividen entre 100 antes de responder.
- La pantalla no muestra columna `Status` ni boton de actualizacion; el endpoint de status queda solo por compatibilidad.
- Filtros visibles: texto por criterio en primer renglon y rango de fechas con `Buscar` en segundo renglon; ambos renglones ocupan media pantalla en desktop y ancho completo en movil.
- El selector de cantidad de registros vive debajo del encabezado `Folio` y muestra solo cantidades.

### Tabla compartida de generacion de ligas

- `Transaccion.vue` recibe `idrol` desde `contenido.blade.php`.
- Admin conserva la tabla completa.
- Usuarios no admin no ven `Forma de Pago`, `Usuario` ni `Productivo` en el listado principal.

## Estados de domiciliacion

La pantalla compartida `Transaccion.vue` conserva el parametro frontend `status`, pero para transacciones lo traduce en backend a `transacciones.condicion`.

Catalogo operativo vigente para `transacciones.condicion`:

- `0`: Pendiente.
- `1`: Activo.
- `2`: Cancelado.
- `3`: Pagado.
- `4`: Vencido.
- `5`: Error, usado cuando existe respuesta aprobada de domiciliacion sin token.

Para domiciliacion:

- Una liga nueva tipo `2` nace como `Pendiente=0`.
- Una respuesta aprobada con token la marca como `Activo=1`.
- Una respuesta aprobada sin token la marca como `Error=5`.
- Si vence sin respuesta aprobada, `revisarStatus()` la marca como `Vencido=4`.
- `transacciones.intentos` cuenta cargos recurrentes fallidos (`transaccionesDom.code != 00`) y se reinicia con cargo aprobado.

## Pruebas recomendadas

- `npm run production`
- Browser smoke de `/login`, `/url`, `/main`, sidebar/topbar y modulos tocados.
- Validar consola sin errores criticos.
- Si cambia realtime, validar `window.Echo`, `window.Pusher` y `broadcasting/auth` con credenciales aisladas.

## Corte diagnostico 2026-06-07

- No se ejecuto `npm run production` en este corte porque no hubo cambios frontend y los assets compilados no se versionan.
- En bash/WSL directo `node` no esta disponible; via Windows si responde `node v20.20.0` y `npm 10.8.2`.
- `resources/assets/js/app.js` registra 22 componentes Vue actuales. No existe `resources/assets/js/components/ReporteTransacciones.vue`; los reportes reales registrados son `ReporteLigas.vue`, `ReporteLigasDom.vue`, `ReporteSpei.vue` y `ReporteCargosRecurrentes.vue`.
- `route:list` vigente registra 103 rutas. Las pantallas nuevas de mayor riesgo frontend siguen siendo `DomiciliacionActiva.vue` y `PagoRecibido.vue` por ownership, filtros, exportacion y datos financieros.
- Addendum 2026-06-18: `DomiciliacionActiva.vue` y `PagoRecibido.vue` incorporan boton `Exportar`; `Respuesta.vue` usa `respuestas.reference` para la opcion visible `Ref. Respuesta`; `Transaccion.vue` conserva `transacciones.responseReference` como criterio principal y exporta con filtros actuales cuando se envian.
- Addendum 2026-06-18: `Transaccion.vue`, `Respuesta.vue`, `TransaccionDom.vue` y `PagoRecibido.vue` usan filtros `Desde/Hasta`; los tres primeros y Pagos Recibidos inicializan ultimos 30 dias y agregan boton `Limpiar`. Los selectores de cantidad de registros inician en `50`.
- Addendum 2026-06-27: `Cliente.vue` abre el modal de alta/edicion llamando `/estado/selectEstado` y `/ciudad/selectCiudad`; estos endpoints deben responder para rol Cliente porque alimentan los selects y el default Sonora/Hermosillo. Si el allowlist de `Administrador` no los incluye, el sintoma visible es `403` en consola y combos vacios.
- Addendum 2026-07-01: `Transaccion.vue` muestra columna/filtro `Status` para ligas tipo `1`, `2`, `3` y `4`; las opciones se filtran por tipo para evitar estados no operables en cada pantalla.
- Addendum 2026-07-03: `IntegrationAudit.vue` agrega los targets admin `menu==31/32/33` para `Outgoing API Requests`, `Incoming API Requests` y `User Activity Log`. El menu `Integraciones` vive despues de `Acceso` en `sidebaradministrador.blade.php`; no se agrega al sidebar cliente. Los filtros default usan el mes actual, paginacion inicia en 50 y el modal de detalle muestra informacion ya sanitizada por backend.
- Los pendientes UX/UI de listados anchos y migracion responsive siguen abiertos salvo pilotos ya documentados; cualquier cambio visual debe incluir build y browser smoke admin/cliente.
