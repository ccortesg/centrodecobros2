# Módulo: Notificaciones y dashboard

## Propósito
Exponer métricas de montos y notificaciones en tiempo real para usuario autenticado.

## Archivos clave
- `DashboardController` (sumatorias por mes/año)
- `NotificationController`
- `app/Notifications/NotifyAdmin.php`
- `resources/assets/js/app.js` + `Notification.vue`

## Infra
- Broadcasting por Pusher/Echo (`private App.User.{id}`).
- `notifications` tabla Laravel.
- Polling `notification/get` validado en el workspace actual.
- Realtime websocket no validado end-to-end en la lane local; `FE-H1-L4` quedo bloqueada y depende de `VAL-A1`.

## Riesgos
- Configuración de realtime parcialmente hardcoded en frontend.
- Middleware `Administrador` ya restringe acceso por rol desde Fase 31: `idrol=1` accede al grupo protegido, `idrol=2` queda limitado por allowlist de rutas/metodos y otros roles reciben `403`.
- El riesgo residual esta en formalizar politicas por accion si aparecen roles adicionales y en validar realtime con credenciales aisladas.
- La lane local actual sigue en `BROADCAST_DRIVER=log`, por lo que notificaciones realtime no deben darse por validadas.
