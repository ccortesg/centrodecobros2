# Entorno y operacion

Ultima actualizacion: 2026-03-14

## Entorno validado de esta copia

| Capa | Valor actual |
| --- | --- |
| Workspace | `C:\temp\centrodecobros_phase8_feh1_batch1` |
| Laravel | `11.48.0` |
| PHP | `8.2.24` |
| Composer | `2.7.3` |
| Node | `22.22.1` |
| npm | `10.9.4` |
| DB local | `centrodecobros` |

## Lanes soportadas

### Lane operativa del workspace actual

Usar siempre:

```powershell
nvm use 22.22.1
node -v
npm -v
```

### Lane legacy solo para rollback documental

Aplicar solo si se trabaja sobre snapshots previos a FE-2:

```powershell
nvm use 8.17.0
node -v
npm -v
```

## Comandos de verificacion tecnica

### Baseline y backend

```powershell
powershell -ExecutionPolicy Bypass -File scripts/local/check_baseline.ps1
powershell -ExecutionPolicy Bypass -File scripts/local/check_route_alignment.ps1
php artisan route:list
php artisan schedule:list
php vendor\bin\phpunit tests\Feature\Smoke tests\Feature\ExampleTest.php
```

### Frontend reproducible

```powershell
npm cache verify
npm ci
npm run development
npm run production
```

## Build frontend

Los scripts publicados en `package.json` usan `scripts/local/run_mix_build.js` para cerrar el compilador al terminar:

- `npm run development`
- `npm run production`

Artefactos esperados:

- `public/js/app.js`
- `public/js/plantilla.js`
- `public/css/plantilla.css`

## Operacion local segura

1. Levantar la app local con `php artisan serve` o el servidor ya configurado en el host.
2. Validar login local existente.
3. Navegar `/main`, `/dashboard` y modulos criticos en modo lectura.
4. Validar exportaciones seguras:
   - `/cliente/exportar`
   - `/transaccion/exportar`
   - `/respuesta/exportar`
   - `/consultaspei/exportar`
   - `/pagospei/exportar`
   - `/cancelaspei/exportar`

## Scheduler operativo

- `TransaccionDomController@ejecutarCron` diario a las `07:00`
- `TransaccionController@revisarStatus` cada `5` minutos

## Restricciones y riesgos operativos

1. El dataset funcional no esta versionado en el repo.
2. No hay `.git` en este directorio; la trazabilidad depende de las copias aisladas y de `docs/`.
3. Existen integraciones externas sensibles que siguen fuera del baseline automatico:
   - ligas reales
   - domiciliacion
   - SPEI extremo a extremo
   - TeleSign OTP/SMS
   - callbacks y webhooks
   - correo/Postmark
   - Pusher/Echo con credenciales reales
4. `npm ci` y `npm audit` siguen reportando deprecations y advisories residuales; tras `FE-H1-L3` ya no aparece la deprecacion directa de `axios`, pero no deben mezclarse con tareas de build/reproductibilidad ya cerradas.
5. La lane local vigente valida polling de notificaciones, no websocket realtime end-to-end; cualquier reapertura de `laravel-echo` / `pusher-js` pasa primero por `VAL-A1`.
