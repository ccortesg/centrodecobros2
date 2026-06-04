# Analisis frontend

Ultima actualizacion: 2026-06-03

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

## Pruebas recomendadas

- `npm run production`
- Browser smoke de `/login`, `/url`, `/main`, sidebar/topbar y modulos tocados.
- Validar consola sin errores criticos.
- Si cambia realtime, validar `window.Echo`, `window.Pusher` y `broadcasting/auth` con credenciales aisladas.
