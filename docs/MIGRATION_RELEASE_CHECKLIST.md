# Checklist de liberacion paralela

Ultima actualizacion: 2026-06-02  
Baseline: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`  
Dictamen: `GO para sandbox paralelo controlado; NO-GO para reemplazo directo`

## Predeploy obligatorio

- Confirmar branch/tag GitHub limpio para Fase 34.
- Branch definido: `main`.
- Tag recomendado tras commit validado: `sandbox-phase34-v1.0.2`.
- Confirmar que el workspace usado para publicar si es checkout Git activo; la carpeta de diagnostico 2026-06-02 no lo es.
- Confirmar que `.env`, `vendor/`, `node_modules/`, logs, SQLite local y archivos accidentales de raiz no se publican como codigo fuente.
- Confirmar que dumps SQL locales (`database/*.sql`) no se publican; usar respaldos administrados fuera de Git.
- Confirmar que `public/build/`, `public/js/app.js`, `public/js/plantilla.js`, `public/js/guest-public.js`, `public/css/plantilla.css` y `public/mix-manifest.json` no se versionan; se generan por CI/deploy.
- Confirmar respaldo de `/var/www/centro` y respaldo MySQL antes de montar la nueva version.
- Confirmar estrategia PHP 8.3:
  - contenedor PHP 8.3 si se conserva Ubuntu 20.04 sin cambios; o
  - host 22.04/24.04 con PHP-FPM por vhost si se decide migrar/actualizar.
- Confirmar que la version actual PHP 7.4 queda intacta.
- Confirmar que la nueva version se publica en ruta separada, por ejemplo `/var/www/centro-v12-sandbox`.
- Confirmar subdominio/vhost separado; evitar subcarpeta si no se ajustan assets/base URL.
- Confirmar `.env` sandbox:
  - `APP_ENV=staging`
  - `APP_DEBUG=false`
  - `APP_URL=https://...`
  - `SESSION_COOKIE=centro_v12_session`
  - `CACHE_PREFIX=centro_v12_`
  - `PAGADETODO_MOCK=true` hasta sandbox oficial
  - `PAGADETODO_*` reales solo desde el ambiente, nunca desde Git
  - `BROADCAST_DRIVER=log` hasta validar Pusher
  - `VITE_PUSHER_*` solo si existe sandbox realtime dedicado
  - `DB_*` apuntando a la DB acordada
- Confirmar que no se ejecutara `php artisan migrate` sobre la DB actual.
- Confirmar que el scheduler de la nueva version queda deshabilitado.
- Confirmar permisos de `storage/` y `bootstrap/cache/`.
- Confirmar assets:
  - `public/js/app.js`
  - `public/js/plantilla.js`
  - `public/css/plantilla.css`
  - `public/js/guest-public.js`
  - `public/build/manifest.json`
  - origen: generados con `npm ci && npm run production`, no committeados.
- Confirmar validaciones locales:
  - `php artisan route:list`
  - `php artisan schedule:list`
  - `composer validate --no-check-publish --no-interaction`
  - `php vendor/bin/phpunit --testsuite Unit`
  - `php scripts/local/prepare_phase33_browser_sqlite.php "$DB_DATABASE"` antes de Feature en CI.
  - Feature aislados con PHP 8.3 + SQLite persistente si el ambiente lo permite.
  - `npm run production`
  - `npm audit --omit=dev --audit-level=low`
- Confirmar aceptacion formal de riesgos residuales:
  - secretos reales pendientes de provisionamiento/rotacion segura;
  - `npm audit` completo con 29 hallazgos dev/tooling;
  - webhooks sin firma/idempotencia final;
  - Pagadetodo sandbox oficial pendiente.

## Precondicion recomendada antes del primer push

- Crear repo/copia limpia y retirar archivos accidentales detectados en raiz:
  - `(window.CentroDeCobrosVueRoot.menu`
  - `JSON.stringify({accountExpanded`
  - `document.body.classList.contains('sidebar-minimized')`
  - `document.body.className`
  - `el.click()`
  - `{document.querySelector('[data-menu-target`
- Confirmar `.gitignore` para `vendor/`, `node_modules/`, `storage/*.sqlite`, `storage/logs/`, `test-results/`, `output/`, dumps SQL y `.env`.
- Confirmar workflow `.github/workflows/sandbox-release-validation.yml` verde antes de desplegar desde GitHub.

## Postdeploy inmediato

- Abrir `/login` en el subdominio nuevo.
- Abrir `/url`.
- Entrar como admin y cliente de prueba.
- Confirmar `/main`, sidebar y topbar.
- Confirmar requests:
  - `POST /notification/get`
  - `GET /dashboard`
  - `GET /cliente?offset=10&buscar=&criterio=nombre`
  - `GET /transaccion?tipo=1&offset=10&buscar=&criterio=folio&status=99`
  - `GET /respuesta?tipo=1&offset=10&buscar=&criterio=reference`
  - `GET /pagospei/reportePagoSpei?idcliente=0&fechaInicio=null&fechaFin=null`
  - `GET /transaccionDom/reporteTransaccionesDom?idcliente=0&fechaInicio=null&fechaFin=null`
- Confirmar modulos visuales:
  - Clientes
  - Generar Liga de pago
  - Generar Liga Domiciliacion
  - Cargos Recurrentes
  - Reporte Ingresos SPEI
  - Reporte Ingresos Cargos Recurrentes
  - Usuarios y Roles para admin
- Confirmar consola browser sin errores.
- Confirmar logs separados sin 500 nuevos.
- Confirmar que no se generaron cargos reales ni llamadas productivas a Pagadetodo.
- Confirmar que cron/scheduler del sandbox no esta instalado.

## Criterio de salida

La version paralela puede quedar habilitada para pruebas internas si:

1. la version actual sigue operando;
2. la nueva version responde por vhost/subdominio separado;
3. PHP 8.3 ejecuta Laravel 12 sin errores;
4. login admin/cliente funciona contra la DB acordada;
5. no hay colision de sesiones/caches;
6. no hay scheduler duplicado;
7. Pagadetodo esta en mock o sandbox oficial no productivo;
8. existe rollback simple: deshabilitar vhost nuevo y conservar `/var/www/centro`.
