# Smoke Tests Minimos Locales

Ultima actualizacion: 2026-03-14

## 1. Automaticos backend y baseline actual

### Comandos base

Para la copia FE-3 actual:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1
powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1
php artisan route:list
php artisan schedule:list
php vendor\bin\phpunit tests\Feature\Smoke tests\Feature\ExampleTest.php
```

### Resultado ejecutado en FE-3 postcheck

| Comando | Resultado |
| --- | --- |
| `php artisan --version` | `Laravel Framework 11.48.0` |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1` | OK en `PHP 8.2.24`, `Composer 2.7.3`, `Node 22.22.1`, `npm 10.9.4`, DB `centrodecobros` |
| `powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1` | `OK` |
| `php artisan route:list` | `OK`; `100` rutas |
| `php artisan schedule:list` | `OK`; `2` tareas |
| `php vendor\bin\phpunit tests\Feature\Smoke tests\Feature\ExampleTest.php` | `OK (21 tests, 114 assertions)` |

### Cobertura automatica validada hoy

| ID | Validacion | Tipo | Estado |
| --- | --- | --- | --- |
| AUTO-01 | Runtime PHP, Composer, Node y npm | Script | Validado |
| AUTO-02 | Conexion a BD local y conteos base | Script | Validado |
| AUTO-03 | `route:list` | Script | Validado |
| AUTO-04 | `schedule:list` | Script | Validado |
| AUTO-05 | Alineacion rutas/controladores/frontend | Script | Validado |
| AUTO-06 | Shell autenticado `/main` y `/dashboard` | HTTP smoke | Validado |
| AUTO-07 | Lectura AJAX de `cliente`, `transaccion`, `respuesta`, `transaccionDom` | HTTP smoke | Validado |
| AUTO-08 | Alias `/role` | HTTP smoke | Validado |
| AUTO-09 | Exportaciones filtradas de reportes vivos (`pagospei`, `transaccionDom`, `transaccion`) | HTTP smoke | Validado |
| AUTO-10 | Exportacion general de `transaccion?tipo=1` | HTTP smoke | Validado |

## 2. Manuales seguros en local

Ejecutar sin disparar integraciones externas:

1. Abrir `/login` y autenticar con una credencial local existente.
2. Abrir `/main` y confirmar menu, shell Blade y carga de assets.
3. Abrir `/dashboard` y confirmar tarjetas y graficas.
4. Navegar modulos criticos en modo lectura:
   - `/transaccion`
   - `/respuesta`
   - `/transaccionDom`
   - `/consultaspei`
   - `/pagospei`
   - `/cancelaspei`
   - `/rol`
5. Validar exportaciones seguras:
   - `/cliente/exportar`
   - `/transaccion/exportar`
   - `/respuesta/exportar`
   - `/consultaspei/exportar`
   - `/pagospei/exportar`
   - `/cancelaspei/exportar`

### Resultado ejecutado en FE-3 postcheck

- la plataforma siguio operando correctamente tras `npm ci`, `npm run development` y `npm run production`;
- los modulos criticos se validaron en navegador sin regresion visible;
- la exportacion del modulo `Ligas de pago` quedo validada y ya no reproduce el `500` historico del export general;
- `check_route_alignment.ps1` ya no reporta gaps abiertos.

### Resultado ejecutado en FE-H1-L2

- `npm ci`, `npm run development` y `npm run production` siguieron en verde tras converger `jquery` y retirar el segundo runtime Bootstrap JS del shell;
- Playwright CLI autenticado valido `main`, dropdown de usuario, notificaciones, sidebar/toggles, `cliente`, `user`, `transaccion`, `respuesta`, `transaccionDom` y reportes sensibles sin errores de consola;
- las exportaciones seguras siguieron cubiertas por la smoke HTTP (`cliente/exportar`, `transaccion/exportar`, `respuesta/exportar`) sin regresion en el batch;
- el intento de smoke publico sobre `/verify/1` expuso un mismatch preexistente entre `TransaccionController@showVerifyForm` y `verify.blade.php`; no se considero regresion de `L2` porque falla antes de ejecutar el JavaScript compartido.

### Resultado ejecutado en FE-H1-L3

- `npm ci`, `npm run development` y `npm run production` siguieron en verde tras subir `axios` a `1.13.6`.
- Playwright CLI autenticado valido `main`, polling de notificaciones, `user`, `cliente`, `transaccion` y `Reporte Ingresos por Ligas de Pago` sin regresion visible.
- La exportacion general `GET /transaccion/exportar?tipo=1` siguio descargando `transacciones.csv`.
- La exportacion filtrada `GET /transaccion/exportarTransacciones?idcliente=0&fechaInicio=null&fechaFin=null&tipo=1` siguio descargando `reporteTransacciones.xlsx`.
- El submit `FormData` de `/transaccion/importar/iniciar` con archivo invalido devolvio `422` controlado y mensaje UI `No fue posible iniciar la importación.`; se tomo como validacion positiva del transporte multipart, no como regresion.

### Resultado ejecutado en FE-H1-L4 evaluacion de gating

- La lectura de `config/broadcasting.php` y `php artisan tinker` confirmo que la lane local sigue resolviendo `BROADCAST_DRIVER=log`.
- El probe browser autenticado confirmo `window.Echo` y `window.Pusher` presentes, con el canal privado `App.User.1` montado pero `pusherState=disconnected`.
- La captura de red del probe solo mostro polling `POST /notification/get`; no hubo `broadcasting/auth` ni evidencia de websocket autenticado.
- Con esa evidencia, `FE-H1-L4` quedo en `NO-GO documentado`: el polling sigue cubierto, pero broadcasting realtime con credenciales reales permanece fuera del baseline automatico.

## 3. FE-3 build reproducible en Node 22

### Secuencia recomendada

```powershell
nvm use 22.22.1
npm cache verify
npm ci
npm run development
npm run production
```

### Resultado ejecutado el 2026-03-14

- `node -v` -> `v22.22.1`
- `npm -v` -> `10.9.4`
- `npm cache verify` -> OK; cache verificada y comprimida
- `npm ci` -> OK
- `npm run development` -> OK
- `npm run production` -> OK
- artefactos emitidos:
  - `public/js/app.js`
  - `public/js/plantilla.js`
  - `public/css/plantilla.css`

### Salvedades observadas

- `npm ci` sigue emitiendo warnings de deprecacion en dependencias legacy y transitivas.
- `npm audit` bajo a `13` vulnerabilidades (`7` low, `6` moderate, `0` high, `0` critical) despues de `FE-H1-L1`, `FE-H1-L2` y `FE-H1-L3`.
- La deprecacion directa de `axios` ya no aparece en `npm ci`; permanecen `vue` y varias transitivas legacy.
- Estos hallazgos no bloquearon la reproducibilidad del build ni la validacion browser, pero mantienen abierta la deuda residual de FE-H1.

## 4. Lane legacy Node 8 solo como rollback

La lane `Node 8.17.0` / npm `6.13.4` se conserva unicamente para:

1. comparar snapshots previos a FE-2;
2. documentar el rollback historico del frente legacy;
3. evitar confundir builds pre-migracion con el workspace FE-3 actual.

No debe usarse como lane operativa del workspace `centrodecobros_phase7_fe3_vue27`.

## 5. Pendientes por integraciones externas

Mantener fuera del baseline automatico:

1. generacion real de ligas de pago;
2. domiciliacion y cargos recurrentes contra pasarela real;
3. SPEI extremo a extremo;
4. webhooks `Service/*`;
5. flujo OTP/TeleSign verify-SMS retirado en Fase 21; no reabrir salvo evidencia nueva;
6. callbacks a URLs externas;
7. correo/Postmark;
8. broadcasting Pusher/Echo con credenciales reales.
