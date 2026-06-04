# Modulo: Notificaciones y dashboard

Ultima actualizacion: 2026-06-03

## Proposito

Exponer metricas operativas y notificaciones para usuario autenticado.

## Archivos clave

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Notifications/NotifyAdmin.php`
- `resources/assets/js/app.js`
- `resources/assets/js/bootstrap.js`
- `resources/assets/js/components/Notification.vue`

## Rutas

- `GET dashboard`
- `POST notification/get`
- `GET|POST|HEAD broadcasting/auth`

## Infraestructura

- Tabla `notifications` de Laravel.
- Polling HTTP via `notification/get`.
- Broadcasting privado por Pusher/Echo si el ambiente lo configura.
- Frontend usa `VITE_PUSHER_APP_KEY` y `VITE_PUSHER_APP_CLUSTER`.
- Si no existe `VITE_PUSHER_APP_KEY`, `window.Echo` queda en `null`.

## Acceso por rol

- Usuario autenticado puede consultar dashboard/notificaciones segun sesion.
- Las metricas deben respetar el alcance del usuario autenticado.
- Middleware `Administrador` aplica a superficies protegidas; dashboard debe revisarse con admin y cliente en UAT.

## Estado actual

- Polling HTTP validado en fases anteriores.
- `DashboardController` se ajusto para funcionar en MySQL y SQLite de pruebas.
- Realtime websocket no esta validado end-to-end con credenciales aisladas.
- Configuracion Pusher ya no debe hardcodearse en frontend; usar variables `VITE_PUSHER_*`.

## Riesgos

- Realtime puede parecer inactivo si `window.Echo=null` o si `BROADCAST_DRIVER=log`.
- `broadcasting/auth` requiere sesion autenticada y configuracion consistente.
- Dashboard puede ocultar errores de datos si solo se valida con rol admin.

## Pruebas recomendadas

- `php artisan route:list`
- Feature/Smoke de `GET dashboard`.
- Smoke autenticado admin y cliente.
- Validar consola navegador:
  - `window.Echo`
  - `window.Pusher`
  - errores de `broadcasting/auth`
- Realtime solo con credenciales Pusher aisladas.

## Pendientes y mejoras

- Validacion E2E de realtime.
- Pruebas de dashboard por rol con datos controlados.
- Documentar definicion exacta de cada metrica.
