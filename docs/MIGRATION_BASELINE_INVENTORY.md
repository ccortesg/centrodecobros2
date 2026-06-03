# Inventario Consolidado del Baseline Actual

Fecha de corte: 2026-03-11

## Resumen

Este documento consolida el estado real del stack, el tooling, las integraciones, los flujos críticos y los principales puntos de compatibilidad/incompatibilidad detectados en el baseline actual.

## Backend: paquetes y runtime observados

| Componente | Versión real | Evidencia | Observación |
| --- | --- | --- | --- |
| PHP CLI | `7.4.33` | `php -v` | Compatible con Laravel 8 actual |
| Composer | `2.7.3` | `composer --version` | Suficiente para fases futuras |
| Laravel Framework | `8.83.23` | `php artisan --version`, `composer.lock` | Última rama de mantenimiento de Laravel 8 |
| laravel/ui | `3.4.6` | `composer.lock` | Auth scaffolding legacy; parte del código parece residual |
| guzzlehttp/guzzle | `7.4.5` | `composer.lock` | Cliente HTTP crítico para integraciones |
| maatwebsite/excel | `3.1.40` | `composer.lock` | Crítico por exportaciones/importaciones |
| barryvdh/laravel-dompdf | `0.8.7` | `composer.lock` | Uso operativo pendiente por confirmar |
| pusher/pusher-php-server | `3.3.1` | `composer.lock` | Muy antiguo para futuras fases |
| telesign/telesign | `3.0.0` | `composer.lock` | OTP/SMS |
| wildbit/swiftmailer-postmark | `3.3.0` | `composer.lock` | Alto riesgo por transición de SwiftMailer |
| fideloper/proxy | `4.4.2` | `composer.lock` | Paquete legacy/abandonado |
| laravel/helpers | `1.5.0` | `composer.lock` | Paquete legacy/abandonado |
| phpunit/phpunit | `9.5.22` | `composer.lock` | No hay suite de negocio útil |

## Frontend: dependencias y build observados

| Componente | Versión real | Evidencia | Observación |
| --- | --- | --- | --- |
| Vue | `2.5.13` | `package-lock.json` | Runtime legacy |
| laravel-mix | `2.0.0` | `package-lock.json` | Toolchain obsoleto |
| webpack | `3.10.0` | `package-lock.json` | Asociado a Mix 2 |
| node-sass | `4.7.2` | `package-lock.json` | Impide asumir compatibilidad con Node moderno |
| sass-loader | `6.0.6` | `package-lock.json` | Muy antiguo |
| vue-loader | `13.7.1` | `package-lock.json` | Insuficiente para una ruta moderna sin ajustes |
| vue-template-compiler | `2.5.13` | `package-lock.json` | Amarrado a Vue 2.5 |
| bootstrap-sass | `3.3.7` | `package-lock.json` | CSS legacy |
| jQuery | `3.3.1` | `package-lock.json` | Sigue activo en flujos Blade |
| axios | `0.17.1` | `package-lock.json` | Muy antiguo y ampliamente usado |
| laravel-echo | `1.4.0` | `package-lock.json` | Realtime |
| pusher-js | `4.3.1` | `package-lock.json` | Cliente realtime |
| vue-select | `2.5.0` | `package-lock.json` | Solo Vue 2 |
| vue-barcode | `1.1.0` | `package-lock.json` | No se detectó uso real en código |
| lodash | `4.17.4` | `package-lock.json` | Antigua, pero no foco principal |

## Tooling y operación observados

| Elemento | Estado real |
| --- | --- |
| Apache | `2.4.58` instalado vía WAMP |
| Node/npm | No disponibles en `PATH` actual |
| Git | No hay `.git` en este directorio |
| Queue | `sync` |
| Scheduler | Dos tareas activas por frecuencia; implementadas sobre controladores |
| Broadcasting | Backend configurable por env, frontend parcialmente hardcoded |
| Assets | Cargados por rutas estáticas, no por `mix()` |

## Puntos de acoplamiento técnico

| Acoplamiento | Evidencia | Riesgo para migración |
| --- | --- | --- |
| Blade -> Vue por variable `menu` | `principal.blade.php`, `contenido.blade.php`, sidebars | Alto; navegación y renderizado dependen de IDs numéricos |
| Blade/jQuery en OTP | `resources/views/verificar/contenido.blade.php` | Medio-alto; flujo público no cubierto por shell Vue |
| Scheduler -> controladores | `app/Console/Kernel.php` | Alto; upgrade de framework puede afectar invocación y dependencias |
| Rutas API -> controladores legacy | `routes/api.php` | Alto; contratos externos sensibles y sin `/api` prefix |
| Frontend realtime -> Pusher hardcoded | `resources/assets/js/bootstrap.js` | Alto; poca separación por ambiente |
| Exportación/importación -> Excel/PhpSpreadsheet | controladores y componentes | Alto; flujos de negocio críticos |
| BD real -> uso inferido, no migration-driven | dump + código | Crítico |

## Integraciones externas detectadas

| Integración | Canal | Evidencia | Sensibilidad |
| --- | --- | --- | --- |
| Pagadetodo | HTTP saliente | `TransaccionController`, `TransaccionDomController` | Crítica |
| Callbacks a clientes | HTTP saliente | `RespuestaController`, `TransaccionController`, columnas `ligaPago`/`ligaRecurrente` | Crítica |
| TeleSign OTP/SMS | HTTP saliente SDK | `TransaccionController@sendSMS`, `telesign/telesign` | Alta |
| Pusher | WebSocket / broadcast | `NotifyAdmin`, `bootstrap.js`, `config/broadcasting.php` | Alta |
| SMTP/Postmark | Correo | `config/mail.php`, dependencia Postmark | Media-alta |

## Flujos funcionales críticos que no deben romperse

| Flujo | Entrada principal | Persistencia principal | Salida externa | Criticidad |
| --- | --- | --- | --- | --- |
| Generación de liga de pago | UI `Transaccion.vue`, API `GenerarLigaPago` | `transacciones` | URL del proveedor/callback eventual | Crítica |
| Generación de domiciliación | UI/API | `transacciones` | Alta en proveedor | Crítica |
| Cargo recurrente | Scheduler y `CargoDomiciliacion` | `transaccionesDom`, `cancelacionesDom` | Callback recurrente | Crítica |
| SPEI | API `GenerarSpei`, `ConsultaClabe`, `PagoClabe`, `CancelaClabe` | `transacciones`, `consultaspei`, `pagospei`, `cancelaspei` | Callback de pago/cancelación | Crítica |
| Respuestas/webhooks | `Service/EntregarPago*` | `respuestas` | Reenvío a `ligaPago` si aplica | Crítica |
| OTP/SMS público | `/verify/{id}`, `/sendSMS`, `/verifySMS` | `transacciones.otp` | SMS TeleSign | Alta |
| Exportaciones | Endpoints `*/exportar` | Lectura de múltiples tablas | Descarga XLS/XLSX | Alta |
| Importación masiva | Endpoints `/transaccion/importar/*` | `storage/app/imports`, `transacciones` | Generación secuencial de ligas | Alta |
| Notificaciones | polling + broadcast | `notifications` | Echo/Pusher | Media |

## Observaciones operativas adicionales

1. `php artisan schedule:list` sí funciona y refleja dos tareas: una diaria y otra cada cinco minutos.
2. `php artisan route:list` no funciona en el estado actual por la referencia a `ArticuloController`.
3. `RegisterController` y parte del auth scaffolding parecen residuales e inconsistentes con el esquema real de `users`.
4. `RoleController` existe, pero las rutas usan `RolController`; `RoleController` parece residuo no operativo.
5. No se detectaron `TRIGGER`, `PROCEDURE`, `FUNCTION`, `VIEW` ni `EVENT` en el dump.
