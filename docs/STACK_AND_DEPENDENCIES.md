# Stack tecnico y dependencias

Ultima actualizacion: 2026-08-04

## Backend actual

- Framework: `Laravel 12.54.1`
- Requisito Composer: `php ^8.2`
- PHP validado local:
  - WSL CLI: `8.3.31`, con `pdo_mysql` y sin `pdo_sqlite`
  - WAMP CLI: `8.3.0`, con `pdo_mysql` y `pdo_sqlite`
- Composer local observado: `2.2.6`; valida el proyecto, pero no incluye `composer audit`.
- Base de datos operativa: MySQL productivo o dump autorizado fuera de Git; las migrations son historicas y no reconstruyen por si solas todo el sistema.
- Produccion: Docker en servidor, confirmado por el propietario el 2026-06-03. El compose productivo no esta versionado en este repo.

### Dependencias backend bloqueadas relevantes

| Paquete | Version lock |
| --- | --- |
| `laravel/framework` | `v12.54.1` |
| `laravel/ui` | `v4.6.2` |
| `guzzlehttp/guzzle` | `7.10.0` |
| `maatwebsite/excel` | `3.1.67` |
| `barryvdh/laravel-dompdf` | `v3.1.1` |
| `pusher/pusher-php-server` | `7.2.7` |
| `league/commonmark` | `2.8.2` |
| `phpunit/phpunit` | `11.5.55` |

## Frontend actual

El shell WSL expone Node `v22.23.1` y npm `10.9.8`; `.nvmrc` fija `22.22.1`. El build no se ejecuto en la auditoria 2026-08-04 porque no hubo cambios frontend y la tarea solo autorizo documentacion.

| Paquete | Version instalada |
| --- | --- |
| `vue` | `3.5.30` |
| `@vue/compiler-sfc` | `3.5.30` |
| `@vitejs/plugin-vue` | `6.0.7` |
| `vite` | `7.3.5` |
| `laravel-vite-plugin` | `2.1.0` |
| `laravel-mix` | `6.0.49` |
| `axios` | `1.13.6` |
| `jquery` | `3.7.1` |
| `lodash` | `4.17.23` |
| `laravel-echo` | `1.4.0` |
| `pusher-js` | `4.3.1` |

## Build y assets

Runner vigente:

```bash
npm run production
```

El runner hibrido ejecuta:

1. lane legacy para `public/css/plantilla.css` y `public/js/plantilla.js`;
2. lane guest para `public/js/guest-public.js`;
3. Vite para `resources/assets/js/app.js`;
4. bridge estable `public/js/app.js` hacia `public/build/assets/app-CrHxfLHs.js`.

Artefactos revalidados:

| Archivo | Estado |
| --- | --- |
| `public/js/app.js` | Bridge estable, 1859 bytes |
| `public/build/assets/app-CrHxfLHs.js` | Bundle Vite, 739.32 kB |
| `public/build/assets/app-BBf2Dnin.css` | CSS Vite, 0.34 kB |
| `public/js/plantilla.js` | Lane legacy, 403207 bytes |
| `public/css/plantilla.css` | Lane legacy, 246986 bytes |
| `public/js/guest-public.js` | Guest lane, 1141 bytes |

## Auditoria

| Comando | Resultado 2026-06-02 |
| --- | --- |
| `composer validate --no-check-publish --no-interaction` | OK |
| `composer audit` | No disponible con Composer 2.2.6 |
| `npm audit --omit=dev --audit-level=low` | 0 vulnerabilidades |
| `npm audit --audit-level=low` | 29 vulnerabilidades dev/tooling: 5 low, 16 moderate, 8 high |

## Implicaciones operativas

1. El runtime productivo vigente esta aislado por Docker; no cambiar PHP `7.4.3` global del host sin necesidad.
2. El servidor de produccion no necesita Node/npm en el host si CI o un contenedor genera assets.
3. Si el build se ejecuta en servidor, usar Node 22 compatible con `.nvmrc` y `npm ci && npm run production`.
4. La app requiere PHP 8.2+ dentro del contenedor/runtime que ejecute Laravel 12.
5. `plantilla.*` sigue fuera de Vite por decision de alcance y debe preservarse como contrato publico.
6. Para comandos exactos de deploy, inspeccionar el compose productivo real; no asumir nombre de servicio desde este repo.
