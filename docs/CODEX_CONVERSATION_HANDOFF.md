# Codex Conversation Handoff - Centro de Cobros Fase 34

Fecha de handoff: 2026-06-07  
Zona horaria de trabajo: America/Hermosillo  
Workspace local actual: `/mnt/c/temp/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`  
Ruta Windows equivalente: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`  
Ruta de despliegue vista en servidor: `/var/www/centrodecobros2`

Este documento transfiere el contexto acumulado de la conversacion de Codex para que otra conversacion pueda continuar sin perder decisiones, hallazgos, implementaciones, pruebas, restricciones y pendientes. No contiene secretos, tokens, passwords, API keys ni credenciales reales. Si una tarea futura necesita revisar `.env`, logs productivos o credenciales, debe hacerlo con cuidado y redactar cualquier valor sensible como `[secreto omitido]`.

---

## 1. Resumen ejecutivo

El proyecto es **Centro de Cobros Masivo**, una plataforma Laravel/Vue para generar ligas de pago, domiciliaciones/recurrentes, SPEI, pagos en caja/terminal, respuestas de pago, cargos recurrentes, reportes, clientes, usuarios y control de pagos recibidos.

Durante esta conversacion se trabajo principalmente sobre:

- Preparacion del proyecto Fase 34 para GitHub/sandbox y despliegue paralelo.
- Diagnostico de archivos accidentales en raiz.
- Correcciones de validaciones Feature con mock Pagadetodo.
- Diagnostico de branch `main` vs `sandbox-phase34-v1.0.1`.
- Runbook de despliegue en servidor productivo Docker sin afectar PHP 7.4 existente.
- Actualizacion documental general del proyecto y modulos.
- Plan UX/UI con baseline visual, matriz QA, etapas 0 a 4.
- Correccion de Mixed Content/logout detras de proxy.
- Correccion de error HTTP 500 por filtro `Status` que usaba columna inexistente `transacciones.status`.
- Migracion progresiva de listados financieros a patrones UX/UI compartidos.
- Nuevos modulos:
  - `Domiciliacion Activa`.
  - `Pagos Recibidos`.
- Reglas nuevas de domiciliacion:
  - `ProximoCargoBase`.
  - `intentos`.
  - estados `Pendiente`, `Activo`, `Cancelado`, `Pagado`, `Vencido`, `Error`.
- Ajustes de acceso para rol cliente a `Domiciliacion Activa` y `Pagos Recibidos`.
- Ajuste funcional de `Pagos Recibidos` para incluir cargos recurrentes aprobados, reglas correctas de monto y filtro por rango de fechas.
- Diagnostico de `PAGADETODO_MOCK`.
- Diagnostico de `npm audit` en Docker.

Regla operativa actual confirmada por el usuario:

- **No crear carpetas nuevas para cambios futuros.**
- Trabajar sobre la carpeta actual local: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`.
- En servidor la carpeta usada es `/var/www/centrodecobros2`.
- El usuario subira cambios al repositorio cuando lo considere necesario.

---

## 2. Stack y arquitectura actual

### Backend

- PHP requerido por `composer.json`: `^8.2`.
- Framework: Laravel `^12.0`.
- Paquetes relevantes:
  - `guzzlehttp/guzzle` para llamadas HTTP a Pagadetodo.
  - `maatwebsite/excel` para exportaciones.
  - `barryvdh/laravel-dompdf`.
  - `laravel/ui`.
  - `pusher/pusher-php-server`.
- Autenticacion: Laravel auth legacy con usuario y rol.
- Controladores principales:
  - `TransaccionController`.
  - `TransaccionDomController`.
  - `RespuestaController`.
  - `PagoRecibidoController`.
  - Controladores SPEI: `ConsultaSpeiController`, `PagoSpeiController`, `CancelaSpeiController`.
  - `ClienteController`, `ArchivoController`, `UserController`, `RolController`, `EstadoController`, `CiudadController`.

### Frontend

- Vue 3.
- Vite 7.
- Build hibrido:
  - Vite para app autenticada.
  - Lane legacy para `plantilla.css` y `plantilla.js`.
  - Lane `guest-public.js`.
  - Bridge `public/js/app.js` hacia el bundle Vite.
- `package.json` mantiene:
  - `vue` 3.5.30.
  - `axios` 1.13.6.
  - `lodash` 4.17.23.
  - `laravel-mix` 6.0.49 por compatibilidad legacy.
  - `vite` `^7.3.1`.
- Entrada principal frontend:
  - `resources/assets/js/app.js`.
- Bootstrap frontend:
  - `resources/assets/js/bootstrap.js`.
- Componentes:
  - `resources/assets/js/components/*.vue`.
- Shell Blade:
  - `resources/views/principal.blade.php`.
  - `resources/views/contenido/contenido.blade.php`.
  - `resources/views/plantilla/sidebaradministrador.blade.php`.
  - `resources/views/plantilla/sidebarcliente.blade.php`.

### Base de datos

Tablas relevantes al trabajo:

- `users`
- `roles`
- `clientes`
- `transacciones`
- `respuestas`
- `transaccionesDom`
- `pagospei`
- `consultaspei`
- `cancelaspei`
- `cancelacionesDom`
- `pagos_recibidos`

Migrations agregadas durante la fase:

- `database/migrations/2026_06_04_120000_add_domiciliacion_control_fields_to_transacciones_table.php`
  - Agrega `transacciones.ProximoCargoBase`.
  - Agrega `transacciones.intentos`.
- `database/migrations/2026_06_04_120100_create_pagos_recibidos_table.php`
  - Crea `pagos_recibidos`.
  - Guarda overrides heredados de status para pagos recibidos.

Nota: no se deben ejecutar migraciones sobre DB productiva sin backup y ventana controlada. Durante la conversacion se dieron comandos Docker especificos para ejecutar solo estas dos migrations si el codigo ya estaba desplegado.

---

## 3. Entornos y comandos de operacion

### Entorno local Codex/Windows/WAMP

Workspace activo:

```bash
/mnt/c/temp/centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia
```

PHP CLI Linux del entorno Codex no siempre tiene `pdo_sqlite`. Para Feature tests aislados con SQLite, el runner confiable es:

```bash
/mnt/c/wamp64/bin/php/php8.3.0/php.exe
```

Comando usado para Feature tests puntuales:

```bash
/mnt/c/wamp64/bin/php/php8.3.0/php.exe vendor/bin/phpunit tests/Feature/UX/DomiciliacionAndPaymentsFeatureTest.php
```

Build productivo local:

```bash
npm run production -- --no-progress
```

### Entorno servidor Docker visto en produccion

Ruta:

```bash
/var/www/centrodecobros2
```

`docker-compose.yml` visto en captura:

```yaml
services:
  app:
    build: .
    container_name: centrodecobros2_php83
    restart: unless-stopped
    ports:
      - "127.0.0.1:8083:80"
    volumes:
      - ./:/var/www/html
      - /var/run/mysqld:/var/run/mysqld
```

Servicio Docker Compose:

```bash
app
```

Contenedor:

```bash
centrodecobros2_php83
```

Comandos seguros de cache despues de desplegar cambios de PHP/config/rutas:

```bash
cd /var/www/centrodecobros2
sudo docker compose exec app php artisan optimize:clear
sudo docker compose exec app php artisan config:cache
```

Validar rutas:

```bash
sudo docker compose exec app php artisan route:list
sudo docker compose exec app php artisan route:list --path=domiciliacion-activa
sudo docker compose exec app php artisan route:list --path=pagos-recibidos
```

Compilar assets dentro de contenedor temporal Node:

```bash
cd /var/www/centrodecobros2

sudo docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e npm_config_cache=/app/.npm-cache \
  -v "$PWD":/app \
  -w /app \
  node:22-bookworm \
  sh -lc 'npm ci --include=dev && npm run production'
```

Secuencia usada/recomendada en servidor:

```bash
cd /var/www/centrodecobros2
sudo docker compose exec app php artisan optimize:clear

sudo docker run --rm \
  --user "$(id -u):$(id -g)" \
  -e npm_config_cache=/app/.npm-cache \
  -v "$PWD":/app \
  -w /app \
  node:22-bookworm \
  sh -lc 'npm ci --include=dev && npm run production'

sudo docker compose exec app php artisan config:cache
sudo docker compose restart app
```

### Ejecutar las dos migrations especificas con Docker

Validar existencia:

```bash
cd /var/www/centrodecobros2
sudo docker exec -it centrodecobros2_php83 sh -lc 'cd /var/www/html && ls -l database/migrations/2026_06_04_120000_add_domiciliacion_control_fields_to_transacciones_table.php database/migrations/2026_06_04_120100_create_pagos_recibidos_table.php'
```

Ejecutar solo la primera:

```bash
sudo docker exec -it centrodecobros2_php83 sh -lc 'cd /var/www/html && php artisan migrate --path=database/migrations/2026_06_04_120000_add_domiciliacion_control_fields_to_transacciones_table.php --force'
```

Ejecutar solo la segunda:

```bash
sudo docker exec -it centrodecobros2_php83 sh -lc 'cd /var/www/html && php artisan migrate --path=database/migrations/2026_06_04_120100_create_pagos_recibidos_table.php --force'
```

Validar:

```bash
sudo docker exec -it centrodecobros2_php83 sh -lc 'cd /var/www/html && php artisan migrate:status | grep 2026_06_04'
```

---

## 4. Reglas de seguridad y restricciones confirmadas

Estas restricciones ya fueron establecidas por el usuario y deben respetarse:

1. No cambiar funcionalidad de negocio salvo solicitud explicita.
2. No ejecutar migraciones sobre DB productiva sin instruccion explicita, backup y ventana controlada.
3. No publicar secretos ni archivos locales.
4. No activar scheduler en version paralela/sandbox.
5. No usar credenciales productivas de Pagadetodo en sandbox.
6. No tocar `principal.blade.php` salvo necesidad tecnica justificada y verificada.
7. No versionar assets compilados salvo decision explicita.
8. No crear una nueva carpeta para cambios futuros del repo local; trabajar en la carpeta actual.
9. No hacer `npm audit fix --force` directo en produccion.
10. No ocultar errores solo en frontend cuando la causa es backend.
11. Mantener rutas publicas y contratos externos Pagadetodo salvo solicitud explicita.

Fronteras estables:

- `resources/views/principal.blade.php` es frontera sensible.
- El contrato publico de assets es sensible:
  - `public/css/plantilla.css`
  - `public/js/plantilla.js`
  - `public/js/guest-public.js`
  - `public/build/manifest.json`
  - `public/js/app.js`
- `routes/api.php` es legacy-style y no usa prefijo `/api`; los endpoints publicos aparecen por path directo.

---

## 5. Variables de entorno y Pagadetodo

### `PAGADETODO_MOCK`

Se lee en:

```php
// config/services.php
'mock' => env('PAGADETODO_MOCK', false)
```

Y se usa en:

```php
// app/Http/Controllers/Controller.php
postJsonControlado(...)
```

Significado:

- `PAGADETODO_MOCK=true`
  - No llama a Pagadetodo real.
  - Usa respuestas simuladas controladas por el sistema.
  - Recomendado para local, pruebas, sandbox sin credenciales oficiales y Feature tests.
- `PAGADETODO_MOCK=false`
  - Llama por HTTP/Guzzle a endpoints reales configurados.
  - Debe usarse solo en ambiente autorizado con URLs/credenciales correctas.

Flujos afectados:

- Generar liga de pago.
- Generar liga de domiciliacion.
- Generar SPEI.
- Generar liga lector/terminal.
- Cargo de domiciliacion.
- Cancelacion de domiciliacion.

Decision confirmada:

- Produccion real normalmente debe usar `PAGADETODO_MOCK=false`.
- Sandbox paralelo sin sandbox oficial de Pagadetodo debe usar `PAGADETODO_MOCK=true`.
- No usar credenciales productivas Pagadetodo en sandbox.

---

## 6. Roles, accesos y permisos

### Roles observados

- `idrol = 1`: Administrador.
- `idrol = 2`: Cliente.
- Otros roles: no deben acceder a rutas protegidas de admin/cliente salvo que se agregue whitelist explicita.

### Middleware relevante

Archivo:

```text
app/Http/Middleware/Administrador.php
```

Comportamiento actual:

- Rol 1: acceso total al grupo protegido por middleware `Administrador`.
- Rol 2: acceso solo a rutas incluidas en whitelist por metodo HTTP.
- Otros roles: `403`.

Rutas nuevas permitidas para rol cliente durante la conversacion:

```text
GET domiciliacion-activa
GET pagos-recibidos
PUT pagos-recibidos/status
```

Nota importante:

- `PUT pagos-recibidos/status` permanece autorizado por compatibilidad, pero la UI actual ya no muestra columna status ni boton de actualizacion en `Pagos Recibidos`.
- `PUT transaccion/rechazar` y `POST transaccionDom/registrar` ya estaban permitidas para cliente y conservan validacion por propiedad del registro.

### Ownership

Helpers compartidos en `app/Http/Controllers/Controller.php`:

- `usuarioEsAdministrador()`
- `aplicarScopePropietario($query, $table)`
- `usuarioPuedeOperarRegistro(...)`
- `respuestaNoAutorizado(...)`
- `criterioPermitido(...)`
- `offsetPaginacion(...)`
- `postJsonControlado(...)`

Regla:

- Admin ve todo.
- Cliente ve/opera solo registros propios y con `productivo` compatible si aplica.
- Si el registro no pertenece al usuario, debe responder `403`.

---

## 7. Modulos y funcionalidades relevantes

### 7.1 Domiciliacion Activa

Archivos:

- `app/Http/Controllers/TransaccionController.php`
- `resources/assets/js/components/DomiciliacionActiva.vue`
- `resources/views/contenido/contenido.blade.php`
- `resources/views/plantilla/sidebaradministrador.blade.php`
- `resources/views/plantilla/sidebarcliente.blade.php`

Ruta:

```text
GET /domiciliacion-activa
```

Menu:

- Target Vue: `menu == 29`.
- Visible para administrador y cliente.

Fuente:

- `transacciones.tipo = 2`
- `transacciones.productivo = 1`
- `transacciones.condicion in (1, 2)`
- Debe existir una `respuesta` asociada con `respuestas.status = 'approved'`.

Filtros:

- `buscar`
- `criterio`
  - `ClientReference`
  - `Reference`
  - `Description`
  - `cliente_nombre`
- `status`
  - `99`: todos
  - `1`: activo
  - `2`: cancelado
- `offset`

Acciones:

- Cancelar domiciliacion:
  - `PUT /transaccion/rechazar`
- Realizar cargo recurrente manual:
  - `POST /transaccionDom/registrar`

Bug corregido:

- El cliente veia el menu pero recibia `403` al abrir el modulo.
- Causa: `GET domiciliacion-activa` no estaba en la whitelist del middleware `Administrador` para rol 2.
- Solucion: agregar la ruta a la whitelist y cubrir con pruebas.

Validaciones:

- Feature test cubre que cliente acceda y vea solo registros propios.
- Rol no admin/no cliente sigue recibiendo `403`.

### 7.2 Pagos Recibidos

Archivos:

- `app/Http/Controllers/PagoRecibidoController.php`
- `app/PagoRecibido.php`
- `resources/assets/js/components/PagoRecibido.vue`
- `docs/MODULES/MODULO_PAGOS_RECIBIDOS.md`
- `docs/FRONTEND_ANALYSIS.md`

Rutas:

```text
GET /pagos-recibidos
PUT /pagos-recibidos/status
```

Menu:

- Target Vue: `menu == 30`.
- Administrador: despues de `Pago con Terminal`.
- Cliente: antes de `Reportes`.

Fuente unificada actual:

1. `respuestas`
   - Condicion: `respuestas.status = 'approved'`.
   - `source_type = 'respuesta'`.
   - Canal segun `transacciones.tipo`:
     - `1`: `Liga de pago`
     - `2`: `Domiciliacion`
     - `3`: `Caja`
     - `4`: `Terminal`
2. `pagospei`
   - Condicion:
     - `pagospei.condicion = 1`
     - `pagospei.mensaje like 'Operaci%exitosa'` o `pagospei.codigo in ('0', '00')`
   - `source_type = 'pagospei'`.
   - Canal: `SPEI`.
3. `transaccionesDom`
   - Condicion: `transaccionesDom.status = 'approved'`.
   - `source_type = 'transaccionDom'`.
   - Canal: `Cargo Recurrente`.

Campos visibles en UI:

- Folio.
- Fecha, usando formato Mexico:
  - `dd-mm-yyyy`
  - hora debajo `hh:mm:ss`
- Cliente.
- Referencia.
- Monto.
- Canal.

Columnas removidas por requerimiento:

- `Status`.
- `Opciones`.
- Boton `Actualizar status`.

Filtro de fechas:

- `fechaInicio`
- `fechaFin`
- Se filtra por fecha del pago (`fuente.fecha`), no por fecha de transaccion base.
- Si ambas fechas se proporcionan, se usa `whereBetween`.
- Si solo hay inicio, se usa `>= inicio`.
- Si solo hay fin, se usa `<= fin`.
- Si inicio > fin, responde `422`.

Reglas confirmadas de monto:

- `respuestas.amount`: se usa directo, porque ya se guarda con el formato correcto.
- `pagospei.monto`: se divide entre 100.
- `transacciones.Amount`: se divide entre 100 cuando se usa como fallback.
- `transaccionesDom.Amount`: se divide entre 100.
- El backend entrega `monto` ya normalizado.
- La UI no debe volver a dividir `monto` entre 100.

Compatibilidad:

- `PUT /pagos-recibidos/status` sigue existiendo.
- `pagos_recibidos` sigue existiendo como tabla de overrides heredados.
- La UI actual no usa ese endpoint.
- `actualizarStatus` actualmente valida `source_type` solo en `respuesta,pagospei`; no contempla `transaccionDom`. Esto es aceptable mientras la UI no permita actualizar status. Si en el futuro se reintroduce status editable para cargos recurrentes, habra que ampliar validacion y ownership.

Bug corregido:

- El cliente veia el menu pero recibia `403` al abrir el modulo.
- Causa: `GET pagos-recibidos` y `PUT pagos-recibidos/status` no estaban en whitelist para rol 2.
- Solucion: agregar rutas a whitelist y pruebas.

Pruebas agregadas:

- Admin lista fuentes.
- Cliente lista solo registros propios.
- Cliente puede acceder a rutas.
- Cliente no puede actualizar status de pago ajeno.
- Rol distinto de admin/cliente recibe `403`.
- Pagos Recibidos incluye cargos recurrentes aprobados.
- Montos se normalizan segun fuente.
- Rango de fechas filtra correctamente.
- Rango invalido responde `422`.

### 7.3 Transacciones y filtros Status

Bug reportado:

- En produccion, en `Generar Liga Domiciliacion/Recurrente`, al cambiar filtro `Status`, navegador llamaba:

```text
/transaccion?page=1&buscar=&criterio=Reference&offset=10&tipo=2&status=2
```

- Respuesta: HTTP 500.
- Log:

```text
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'transacciones.status' in 'where clause'
```

Causa:

- La UI mostraba `Status`, pero el estado operativo real de `transacciones` es `condicion`, no `status`.

Decision:

- `status` puede seguir llegando desde frontend por compatibilidad.
- Backend debe traducirlo a la columna real `transacciones.condicion`.
- Solo usar `status` cuando la tabla tenga columna real `status`, por ejemplo:
  - `respuestas.status`
  - `transaccionesDom.status`
- Usar `enviada` cuando la tabla tenga columna real `enviada`.
- Todo filtro dinamico debe estar whitelisteado.
- Criterio invalido debe responder `422`, nunca SQL dinamico/HTTP 500.

### 7.4 Domiciliacion y cargos recurrentes

Archivos:

- `app/Http/Controllers/TransaccionController.php`
- `app/Http/Controllers/RespuestaController.php`
- `app/Http/Controllers/TransaccionDomController.php`
- `app/Transaccion.php`
- `app/TransaccionDom.php`
- `resources/assets/js/components/DomiciliacionActiva.vue`
- `resources/assets/js/components/TransaccionDom.vue`

Campos agregados:

- `transacciones.ProximoCargoBase`
- `transacciones.intentos`

Estados de `transacciones.condicion` confirmados:

- `0`: Pendiente.
- `1`: Activo.
- `2`: Cancelado.
- `3`: Pagado.
- `4`: Vencido.
- `5`: Error.

Reglas de domiciliacion:

- Una liga nueva tipo `2` nace como `Pendiente=0`.
- Una respuesta aprobada con token marca `Activo=1`.
- Una respuesta aprobada sin token marca `Error=5`.
- Si vence sin respuesta aprobada, `revisarStatus()` marca `Vencido=4`.
- `transacciones.intentos` cuenta cargos recurrentes fallidos (`transaccionesDom.code != '00'`).
- `intentos` se reinicia a `0` con cargo aprobado.
- `ProximoCargoBase` queda como ancla/auditoria inicial.

Diagnostico de `ejecutarCron`:

- Selecciona `transacciones.tipo=2`, `condicion=1`, `productivo=1`, usuario con `recurrente=1`, respuesta aprobada y `ProximoCargo` del dia.
- El codigo revisado ya calculaba el siguiente cargo aprobado desde la fecha programada (`ProximoCargo`) y no desde la fecha real de ejecucion.
- Decision: mantener el avance desde fecha programada vigente para evitar repetir siempre la misma base.
- Precaucion futura: si se mueve a comando/job, conservar filtros y agregar locks si hay multiples instancias para evitar cargos duplicados.

### 7.5 UX/UI general

Documento principal:

```text
docs/UX_UI_AUDIT_AND_WORK_PLAN_2026-06-04.md
```

Patrones UX/UI creados:

- `.cdc-list-toolbar`
- `.cdc-table-shell`
- `.cdc-responsive-table`
- `.cdc-pagination`
- `.cdc-date-stack`
- `.cdc-date-stack__time`
- `.cdc-action-button`
- `.cdc-table-footer`
- `.cdc-table-total`
- `.cdc-status-filter-heading`
- `.cdc-column-description`
- `.cdc-sticky-col`

Helpers globales Vue:

- `$formatDateMx(value)` para `dd-mm-yyyy`.
- `$formatTimeMx(value)` para `hh:mm:ss`.
- `$formatDateTimeMx(value)` para `{ date, time }`.
- `$paginationPages(pagination, radius = 2)` para paginacion compacta.

Listados financieros migrados o parcialmente migrados:

- `Transaccion.vue`
- `TransaccionDom.vue`
- `PagoSpei.vue`
- `ConsultaSpei.vue`
- `CancelaSpei.vue`
- `Respuesta.vue`
- `DomiciliacionActiva.vue`
- `PagoRecibido.vue`

Iconografia:

- Migracion visible a FontAwesome (`fa fa-*`) en sidebars/componentes tocados.
- Evitar depender de glifos privados de Simple Line Icons.
- Mantener fonts FontAwesome servidos correctamente por vhost.

### 7.6 Sesion expirada

`resources/assets/js/app.js` tiene interceptor global Axios:

- Detecta `401/419`.
- Detecta HTML redirigido a `/login` en peticiones AJAX.
- Muestra modal `Tu sesion caduco por inactividad`.
- Al confirmar, limpia SweetAlert y redirige a `/login`.

Los componentes no deben duplicar esta logica. Los `catch` locales deben enfocarse en errores funcionales del modulo.

### 7.7 Proxy HTTPS / Mixed Content

Problema observado:

- Warnings Mixed Content al entrar a `/main`, relacionados con generacion HTTP de logout en pagina HTTPS.

Diagnostico:

- Revisar `APP_URL`, proxy Docker, headers `X-Forwarded-*`, `TrustProxies` y `route('logout')`.

Decision:

- No tocar `principal.blade.php`.
- Mantener `route('logout')`.
- La solucion debe estar en configuracion de proxy/TrustProxies y headers correctos para que Laravel genere URLs HTTPS detras de Docker/reverse proxy.

---

## 8. Documentacion tocada o relevante

Documentos creados/actualizados durante la conversacion:

- `docs/UX_UI_AUDIT_AND_WORK_PLAN_2026-06-04.md`
  - Plan UX/UI.
  - Hallazgos visuales.
  - Etapas 0 a 4.
  - Evidencias y validaciones.
- `docs/FRONTEND_ANALYSIS.md`
  - Convenciones frontend, filtros, nuevos modulos, helpers.
- `docs/MODULES/MODULO_DOMICILIACION.md`
  - Domiciliacion activa, `ejecutarCron`, intentos, estados.
- `docs/MODULES/MODULO_TRANSACCIONES.md`
  - Transacciones, filtros, estados.
- `docs/MODULES/MODULO_PAGOS_RECIBIDOS.md`
  - Fuentes, montos, filtros por fecha, canal `Cargo Recurrente`.
- `docs/AI_AGENT_GUIDE.md`
- `docs/ENVIRONMENT_AND_OPERATION.md`
- `docs/DEVELOPER_ONBOARDING.md`
- `docs/SECURITY_AND_RISKS.md`
- `docs/INTEGRATIONS.md`
- `docs/MIGRATION_*`
- `docs/PROJECT_STATUS_DIAGNOSTIC_*`

Este handoff complementa esos documentos y debe mantenerse actualizado al cerrar nuevas fases.

---

## 9. Validaciones y evidencias ejecutadas

### Validaciones recientes despues del ajuste de Pagos Recibidos

```bash
php -l app/Http/Controllers/PagoRecibidoController.php
```

Resultado:

```text
No syntax errors detected
```

```bash
/mnt/c/wamp64/bin/php/php8.3.0/php.exe vendor/bin/phpunit tests/Feature/UX/DomiciliacionAndPaymentsFeatureTest.php
```

Resultado:

```text
OK (14 tests, 44 assertions)
```

```bash
npm run production -- --no-progress
```

Resultado:

```text
OK. Verifico artefactos:
public/css/plantilla.css
public/js/plantilla.js
public/js/guest-public.js
public/build/manifest.json
public/js/app.js
```

```bash
php artisan route:list --path=pagos-recibidos
```

Resultado:

```text
GET|HEAD  pagos-recibidos
PUT       pagos-recibidos/status
```

### Validaciones despues de acceso cliente a Domiciliacion Activa/Pagos Recibidos

```bash
php -l app/Http/Middleware/Administrador.php
php artisan route:list --path=domiciliacion-activa
php artisan route:list --path=pagos-recibidos
/mnt/c/wamp64/bin/php/php8.3.0/php.exe vendor/bin/phpunit tests/Feature/UX/DomiciliacionAndPaymentsFeatureTest.php
```

Resultado reportado:

```text
OK (12 tests, 32 assertions)
```

### Validaciones de fase UX/UI y filtros financieros

Ejecutadas durante la conversacion:

- `php -l` sobre PHP modificado.
- `php artisan route:list`.
- Feature tests especificos:
  - `DomiciliacionAndPaymentsFeatureTest.php`
  - `FinancialFiltersFeatureTest.php`
  - `tests/Feature/UX`
  - subsets Phase32/Phase34/Smoke.
- Unit tests.
- `npm run production`.
- `npm audit --omit=dev`.

Resultados historicos reportados:

- `DomiciliacionAndPaymentsFeatureTest.php`: inicialmente `7 tests, 21 assertions`; luego ampliado a `12 tests, 32 assertions`; actualmente `14 tests, 44 assertions`.
- `FinancialFiltersFeatureTest.php`: `7 tests, 28 assertions`.
- `tests/Feature/UX`: `18 tests, 63 assertions`.
- Subset Phase32/Phase34/Smoke: `36 tests, 107 assertions`.
- Unit: `13 tests, 72 assertions`.
- `npm audit --omit=dev`: `found 0 vulnerabilities`.

Limitacion:

- Suite completo `tests/Feature` llego a fallar por credenciales MySQL locales (`Access denied` a usuario DB local), no por las pruebas nuevas. Usar SQLite aislado/WAMP PHP 8.3 para validaciones controladas.

### Validaciones de servidor Docker/npm

El usuario ejecuto:

```bash
php artisan route:list > /tmp/centrodecobros2-routes.txt
```

Resultado:

- 100 rutas.
- Incluye:
  - `GET /domiciliacion-activa`
  - `GET /pagos-recibidos`
  - `PUT /pagos-recibidos/status`

`npm audit --omit=dev` dentro de Node Docker:

```text
found 0 vulnerabilities
```

`npm audit` completo:

```text
16 vulnerabilities (5 low, 9 moderate, 2 high)
```

Hallazgos npm:

- `axios@1.13.6` con advisories altos.
- `lodash@4.17.23` con advisories altos.
- `follow-redirects`, `elliptic`, `uuid` por cadenas de build legacy (`laravel-mix`, `webpack-dev-server`, etc.).

Decision:

- No ejecutar `npm audit fix --force` directo en produccion.
- Tratar `axios` y `lodash` como frontend runtime aunque esten en `devDependencies`, porque se empaquetan en el bundle servido al navegador.
- Resolver en rama/controlado:
  - actualizar `axios` y `lodash`.
  - validar `npm ci`.
  - validar `npm run production`.
  - validar browser.
  - luego desplegar.

---

## 10. Bugs diagnosticados/corregidos

### 10.1 Archivos accidentales en raiz

Se observo en Explorer una serie de archivos con nombres de comandos JS, por ejemplo:

- `(window.CentroDeCobrosVueRoot.menu`
- `.nav-link').click()`
- `{document.querySelector('[data-menu-target`
- `document.body.classList.contains('sidebar-minimized')`
- `document.body.className`
- `el.click()`
- `JSON.stringify({accountExpanded`

Diagnostico:

- Eran archivos accidentales derivados de comandos pegados/ejecutados en contexto incorrecto.
- No forman parte del proyecto.
- Deben excluirse/eliminarse antes de versionar si vuelven a aparecer.

Nota:

- No incluir `.env`, `vendor/`, `node_modules/`, logs, SQLite local, outputs, test-results ni archivos accidentales de raiz en Git.

### 10.2 Error 500 por `transacciones.status`

Corregido:

- Filtro `Status` de transacciones tipo 2 ya no genera SQL con columna inexistente.
- Backend usa `transacciones.condicion`.
- Se whitelistearon criterios/filtros.

### 10.3 Mixed Content en `/main`

Diagnostico:

- Relacionado con URL/proxy/logout detras de HTTPS.
- Solucion conceptual: confiar en headers proxy, no cambiar Blade.

### 10.4 Cliente no podia abrir `Domiciliacion Activa`

Sintoma:

```json
{"status":"error","msg":"No tienes permisos para realizar esta accion."}
```

Causa:

- Menu visible para cliente, pero `GET domiciliacion-activa` no estaba en whitelist del middleware.

Solucion:

- Agregar `GET domiciliacion-activa` a whitelist de rol cliente.

### 10.5 Cliente no podia abrir `Pagos Recibidos`

Causa:

- Igual que anterior, ruta visible pero no autorizada.

Solucion:

- Agregar `GET pagos-recibidos` y `PUT pagos-recibidos/status` a whitelist.

### 10.6 Pagos Recibidos no incluia cargos recurrentes

Requerimiento del usuario:

- Considerar `transaccionesDom.status='approved'` como pago recibido.
- Canal: `Cargo Recurrente`.

Implementado.

### 10.7 Montos incorrectos en Pagos Recibidos

Requerimiento del usuario:

- `respuestas.amount` no dividir entre 100.
- `pagospei.monto`, `transacciones.Amount`, `transaccionesDom.Amount` si dividir entre 100.

Implementado con campo backend `monto` ya normalizado.

### 10.8 UI de Pagos Recibidos no debia mostrar status

Requerimiento:

- Quitar columna `Status`.
- Quitar boton `Actualizar status`.
- Agregar busqueda por rango de fechas.

Implementado.

---

## 11. Archivos/modulos tocados durante la conversacion

Lista no exhaustiva pero operativamente relevante:

### Backend

- `app/Http/Controllers/Controller.php`
  - Helpers de ownership, whitelists, mock Pagadetodo.
- `app/Http/Controllers/TransaccionController.php`
  - Filtros status/condicion.
  - `domiciliacionActiva`.
  - estados de domiciliacion.
  - sincronizacion con respuestas aprobadas.
- `app/Http/Controllers/RespuestaController.php`
  - Sincronizacion de status de domiciliacion al recibir respuesta aprobada.
- `app/Http/Controllers/TransaccionDomController.php`
  - `intentos`.
  - `ProximoCargoBase`.
  - control de cron/cargo aprobado/fallido.
- `app/Http/Controllers/PagoRecibidoController.php`
  - Vista unificada de pagos recibidos.
  - Fuentes `respuestas`, `pagospei`, `transaccionesDom`.
  - monto normalizado.
  - filtros por fecha.
- `app/Http/Middleware/Administrador.php`
  - Whitelist cliente para `domiciliacion-activa`, `pagos-recibidos`, `pagos-recibidos/status`.
- `app/PagoRecibido.php`
- `app/Transaccion.php`

### Rutas

- `routes/web.php`
  - `GET /domiciliacion-activa`
  - `GET /pagos-recibidos`
  - `PUT /pagos-recibidos/status`

### Frontend

- `resources/assets/js/app.js`
  - Registro de componentes nuevos.
  - Helpers UX.
  - Interceptor sesion expirada.
- `resources/assets/js/components/DomiciliacionActiva.vue`
- `resources/assets/js/components/PagoRecibido.vue`
- `resources/assets/js/components/Transaccion.vue`
- `resources/assets/js/components/TransaccionDom.vue`
- `resources/assets/js/components/PagoSpei.vue`
- `resources/assets/js/components/ConsultaSpei.vue`
- `resources/assets/js/components/CancelaSpei.vue`
- `resources/assets/js/components/Respuesta.vue`
- `resources/assets/js/styles/ux-ui.css`
- Varios componentes recibieron migracion iconografica a FontAwesome.

### Vistas Blade

- `resources/views/contenido/contenido.blade.php`
  - Switchboard menu->component.
  - `menu==29`: `domiciliacionactiva`.
  - `menu==30`: `pagorecibido`.
- `resources/views/plantilla/sidebaradministrador.blade.php`
  - Menu Domiciliacion Activa.
  - Menu Pagos Recibidos.
  - Iconografia FontAwesome.
- `resources/views/plantilla/sidebarcliente.blade.php`
  - Menu Domiciliacion Activa.
  - Menu Pagos Recibidos.
  - Iconografia FontAwesome.

### Tests

- `tests/Support/UsesIsolatedCentroCobrosDatabase.php`
  - Schema SQLite aislado.
  - Seeds.
  - Tablas `pagos_recibidos`, `ProximoCargoBase`, `intentos`.
- `tests/Feature/UX/DomiciliacionAndPaymentsFeatureTest.php`
  - Domiciliacion Activa.
  - Pagos Recibidos.
  - Acceso cliente.
  - Cargos recurrentes.
  - Montos.
  - Rango de fechas.
- `tests/Feature/UX/FinancialFiltersFeatureTest.php`
- Tests Phase32/Phase34/Smoke relacionados.

### Migrations

- `database/migrations/2026_06_04_120000_add_domiciliacion_control_fields_to_transacciones_table.php`
- `database/migrations/2026_06_04_120100_create_pagos_recibidos_table.php`

---

## 12. Estado de GitHub/branch/deploy

Conversacion incluyo una consulta sobre usar `main` en vez de `sandbox-phase34-v1.0.1`.

Decision recomendada:

- Para servidor productivo/paralelo, es razonable trabajar sobre branch `main` si ese sera el branch que se descargara en servidor.
- Tags pueden representar releases sandbox/productivas.
- No mezclar rama temporal de sandbox como branch principal si la operacion normal del servidor espera `main`.

Preparacion Git/GitHub:

- `.gitignore` debe excluir:
  - `.env`
  - `vendor/`
  - `node_modules/`
  - logs
  - SQLite local
  - `output/`
  - `test-results/`
  - archivos accidentales de raiz
- No versionar assets compilados salvo decision explicita.
- `composer.lock` y `package-lock.json` si deben versionarse.

Nota del estado local al inicio de este handoff:

- Antes de crear `docs/CODEX_CONVERSATION_HANDOFF.md`, `git status --short` no mostro cambios pendientes.
- Despues de esta tarea, el unico cambio esperado debe ser este documento.

---

## 13. Riesgos activos

### R-01: Sandbox oficial Pagadetodo no disponible

Impacto:

- Los mocks pueden desviarse del contrato real.

Mitigacion:

- Mantener `PAGADETODO_MOCK=true` en validacion sin credenciales oficiales.
- Obtener credenciales/URL sandbox no productivas.
- Comparar payloads/respuestas reales contra matriz mock.

### R-02: Scheduler en sandbox/paralelo

Impacto:

- Puede generar cargos recurrentes o procesos duplicados si comparte DB.

Mitigacion:

- No activar scheduler en sandbox.
- Si se prueba `ejecutarCron`, hacerlo con DB aislada o mock/sandbox oficial y locks.

### R-03: Deuda npm audit

Impacto:

- `axios` y `lodash` tienen advisories altos.
- Aunque estan en `devDependencies`, se empaquetan en frontend runtime.

Mitigacion:

- Actualizar en rama controlada.
- No usar `npm audit fix --force` en produccion.
- Validar build/browser.

### R-04: Full Feature suite bloqueado por DB local

Impacto:

- Suite completo puede fallar por credenciales MySQL locales.

Mitigacion:

- Usar Feature SQLite aislado con WAMP PHP 8.3.
- Mantener smoke DB separado.

### R-05: Canal `Caja` vs `SPEI`

Impacto:

- `tipo=3` historicamente se usa para referencias SPEI/Pago en Caja.
- `Pagos Recibidos` etiqueta `pagospei` como `SPEI` y `respuestas` tipo 3 como `Caja`.

Mitigacion:

- Confirmar con negocio si se requiere separacion historica mas fina.

### R-06: Endpoint legacy `pagos-recibidos/status`

Impacto:

- UI ya no lo usa, pero sigue existiendo.
- Soporta `source_type` `respuesta` y `pagospei`; no soporta `transaccionDom`.

Mitigacion:

- Mantener por compatibilidad.
- Si se decide removerlo, hacerlo como breaking change documentado.
- Si se decide reactivar status editable, ampliar soporte a `transaccionDom`.

---

## 14. Pendientes reales

### Alta prioridad

1. QA browser autenticado en servidor/sandbox:
   - Login.
   - `/main`.
   - Menu Domiciliacion Activa con admin y cliente.
   - Menu Pagos Recibidos con admin y cliente.
   - Filtro por rango de fechas.
   - Montos por fuente.
   - Sin errores criticos de consola.
2. Confirmar si las dos migrations 2026-06-04 ya fueron ejecutadas en produccion:
   - `ProximoCargoBase`/`intentos`.
   - `pagos_recibidos`.
3. Revisar en servidor que `php artisan optimize:clear` y `config:cache` se ejecuten tras deploy.
4. Validar con datos reales que `Pagos Recibidos` incluya:
   - respuestas aprobadas.
   - pagos SPEI exitosos.
   - cargos recurrentes aprobados.

### Media prioridad

1. Resolver deuda npm:
   - `axios`.
   - `lodash`.
   - revisar `follow-redirects` override.
2. Confirmar negocio:
   - distincion `Caja` vs `SPEI`.
   - si `Pagos Recibidos` requiere exportacion.
   - si `Pagos Recibidos` requiere filtro por canal.
3. Ejecutar smoke visual responsive:
   - desktop.
   - mobile.
   - tablas con scroll horizontal.
   - paginacion.
4. Revisar endpoint legacy `pagos-recibidos/status`:
   - mantener por compatibilidad.
   - remover en futura version.
   - o ampliar a `transaccionDom` si negocio quiere status editable.

### Baja prioridad / mantenimiento

1. Modernizar build legacy gradualmente para reducir `laravel-mix`.
2. Mejorar tipado/contratos frontend.
3. Formalizar matriz de permisos por rol/modulo.
4. Crear pruebas browser automatizadas si hay credenciales sandbox.

---

## 15. Siguiente paso optimo recomendado

El siguiente paso optimo es una fase de **QA funcional autenticado y validacion de despliegue** sobre servidor/sandbox, enfocada en los modulos recientemente tocados.

Objetivo:

- Confirmar que el codigo desplegado y la DB productiva/sandbox tienen las columnas/tablas requeridas.
- Confirmar que rol cliente ya puede entrar a `Domiciliacion Activa` y `Pagos Recibidos`.
- Confirmar que `Pagos Recibidos` muestra montos correctos y cargos recurrentes.
- Confirmar que no hay errores 403/500/SQL/consola.

Comandos servidor recomendados:

```bash
cd /var/www/centrodecobros2

sudo docker compose exec app php artisan optimize:clear
sudo docker compose exec app php artisan route:list --path=domiciliacion-activa
sudo docker compose exec app php artisan route:list --path=pagos-recibidos
sudo docker compose exec app php artisan migrate:status | grep 2026_06_04
sudo docker compose exec app php artisan config:cache
```

Si falta alguna migration, ejecutar solo la faltante con backup previo.

Checklist browser:

- Admin:
  - abre Domiciliacion Activa.
  - abre Pagos Recibidos.
  - valida rango de fechas.
  - valida montos.
- Cliente:
  - abre Domiciliacion Activa sin 403.
  - ve solo registros propios.
  - abre Pagos Recibidos sin 403.
  - ve solo registros propios.
- Consola:
  - sin 500.
  - sin 403 inesperado.
  - sin Mixed Content.
  - sin assets faltantes.

---

## 16. Prompt recomendado para la siguiente conversacion

Usar este prompt si se abre otra conversacion de Codex:

```text
Trabaja sobre:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

Lee primero:
docs/CODEX_CONVERSATION_HANDOFF.md
docs/MODULES/MODULO_PAGOS_RECIBIDOS.md
docs/MODULES/MODULO_DOMICILIACION.md
docs/FRONTEND_ANALYSIS.md
docs/UX_UI_AUDIT_AND_WORK_PLAN_2026-06-04.md

No crees una nueva carpeta. No toques principal.blade.php salvo necesidad tecnica justificada. No ejecutes migraciones productivas. No modifiques credenciales, scheduler ni contratos Pagadetodo.

Objetivo:
Realiza QA funcional y tecnico de los cambios recientes:
1. Domiciliacion Activa para admin y cliente.
2. Pagos Recibidos para admin y cliente.
3. Validar que el rol cliente no reciba 403 en GET /domiciliacion-activa ni GET /pagos-recibidos.
4. Validar que Pagos Recibidos incluya respuestas approved, pagospei exitosos y transaccionesDom status approved como Cargo Recurrente.
5. Validar montos:
   - respuestas.amount no se divide entre 100;
   - pagospei.monto, transacciones.Amount y transaccionesDom.Amount se dividen entre 100.
6. Validar filtro por rango de fechas de pago.
7. Validar que no hay errores 500, SQL de columnas inexistentes, errores de consola ni Mixed Content.

Ejecuta validaciones locales seguras:
- php -l en PHP tocado si aplica.
- php artisan route:list --path=domiciliacion-activa
- php artisan route:list --path=pagos-recibidos
- PHPUnit Feature especifico con PHP 8.3 WAMP y SQLite.
- npm run production -- --no-progress si se toca frontend.

No implementes cambios hasta diagnosticar. Si encuentras bugs, aplica el cambio minimo seguro, agrega o ajusta pruebas y actualiza documentacion cercana.

Entrega:
- diagnostico con evidencia;
- archivos tocados;
- pruebas ejecutadas;
- pendientes reales;
- comandos exactos para deploy/cache en Docker.
```

---

## 17. Notas para agentes futuros

- No leer ni pegar `.env` completo en respuestas.
- No exponer valores de credenciales Pagadetodo, DB, mail, Pusher ni tokens.
- Si necesitas revisar logs, extrae solo errores relevantes y redacta secretos.
- Preferir `rg` para busquedas.
- Para ediciones manuales usar `apply_patch`.
- Antes de tocar filtros backend, confirmar columna real.
- Antes de tocar UI de tablas, revisar helpers en `app.js` y clases en `ux-ui.css`.
- Si se toca un modulo, actualizar `docs/MODULES/...` correspondiente.
- Si se toca comportamiento cross-cutting frontend, actualizar `docs/FRONTEND_ANALYSIS.md`.
- Si se toca deploy/sandbox, actualizar runbook correspondiente.
- Si se toca Pagadetodo, ejecutar Feature con `PAGADETODO_MOCK=true` y no usar credenciales productivas.
- Si se toca scheduler o `ejecutarCron`, tratar como cambio de alto riesgo.

