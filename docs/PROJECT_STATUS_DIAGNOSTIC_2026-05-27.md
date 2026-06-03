# Diagnostico integral de estado del proyecto

Fecha de revalidacion: 2026-05-27  
Workspace analizado: `/mnt/c/temp/centrodecobros_phase33_entorno_sandbox_e2e`  
Servidor objetivo informado: Ubuntu 20.04, Apache 2.4.41, PHP 7.4.3, MySQL 8.0.42, sin Node/npm instalados.

## Dictamen ejecutivo

`GO tecnico para sandbox paralelo controlado; NO-GO para reemplazo directo de produccion`.

La version actual ya opera sobre Laravel 12.54.1, PHP 8.2+ requerido por Composer, Vue 3.5.30 y Vite 7.3.1. Las rutas, build y pruebas aisladas estan en buen estado. Lo que todavia impide liberar sin condiciones es operativo: falta validar Pagadetodo sandbox oficial, endurecer webhooks/idempotencia, tratar secretos hardcoded, resolver `npm audit` completo y ejecutar UAT contra datos reales sin disparar cargos, callbacks o scheduler productivos.

Punto critico para el servidor actual: Ubuntu 20.04 ya esta fuera de soporte estandar desde el 31 de mayo de 2025, y el PPA actual de Ondrej PHP publicado en Launchpad lista series `Noble (24.04)` y `Jammy (22.04)`, no `Focal (20.04)`. Por tanto, instalar PHP 8.3 nativo en ese mismo Ubuntu 20.04 via PPA no debe asumirse como camino soportado. Para coexistencia real PHP 7.4/8.3 hay dos rutas razonables: contenedor PHP 8.3 para la nueva app dejando PHP 7.4 intacto, o actualizar/migrar el host a 22.04/24.04 y usar PHP-FPM por vhost.

Fuentes externas consultadas para el plan de servidor:
- Ubuntu 20.04 fin de soporte estandar: https://ubuntu.com/blog/ubuntu-20-04-lts-end-of-life-standard-support-is-coming-to-an-end-heres-how-to-prepare
- PPA Ondrej PHP series publicadas: https://launchpad.net/~ondrej/+archive/ubuntu/php
- Apache `mod_proxy_fcgi`/PHP-FPM por socket: https://httpd.apache.org/docs/2.4/mod/mod_proxy_fcgi.html
- NodeSource distribuciones Node.js: https://deb.nodesource.com/

## Evidencia ejecutada en esta revalidacion

| Area | Resultado |
| --- | --- |
| PHP Linux local | `PHP 8.3.27`; extensiones presentes: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `intl`; no tiene `pdo_sqlite`. |
| PHP WAMP local | `PHP 8.3.0`; tiene `pdo_mysql`, `pdo_sqlite`, `sqlite3`. |
| Laravel | `php artisan --version` -> `Laravel Framework 12.54.1`. |
| Rutas | `php artisan route:list` -> OK, 97 rutas. |
| Scheduler | `php artisan schedule:list` -> 2 tareas: `TransaccionDomController@ejecutarCron` diario 07:00 y `TransaccionController@revisarStatus` cada 5 minutos. |
| Composer | `composer validate --no-check-publish --no-interaction` -> OK; Composer instalado es 2.2.6 y no soporta `composer audit`. |
| Build frontend | `npm run production` -> OK; genera/verifica `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css`, `public/js/guest-public.js`, `public/build/manifest.json`. |
| npm runtime audit | `npm audit --omit=dev --audit-level=low` -> 0 vulnerabilidades. |
| npm audit completo | `npm audit --audit-level=low` -> falla con 29 vulnerabilidades dev/tooling: 5 low, 16 moderate, 8 high. |
| Unit tests | `php vendor/bin/phpunit --testsuite Unit` -> OK, 13 tests, 72 assertions. |
| Feature tests con MySQL `.env` | Falla con 13 errores por `Access denied for user 'centro_user'@'localhost'`. |
| Feature tests aislados | Con PHP WAMP + SQLite por `cmd.exe` -> OK, 44 tests, 199 assertions. |

Comando validado para Feature aislados:

```bat
cd /D C:\temp\centrodecobros_phase33_entorno_sandbox_e2e
set APP_ENV=testing&& set DB_CONNECTION=sqlite&& set DB_DATABASE=C:\temp\centrodecobros_phase33_entorno_sandbox_e2e\storage\phase33_browser.sqlite&& set PAGADETODO_MOCK=true&& C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit --testsuite Feature
```

## Estado global por capa

| Capa | Avance | Diagnostico |
| --- | ---: | --- |
| Backend Laravel 12 | 87% | Rutas, controladores y scheduler cargan. Hay ownership/whitelists en modulos sensibles. Persisten controladores monoliticos y secretos hardcoded. |
| Frontend Vue 3 + Vite | 85% | Build productivo verde y contrato de assets preservado. `plantilla.*` sigue como lane legacy fuera de Vite. |
| Base de datos | 66% | La app apunta a schema historico real; migrations no son fuente canonica. Pruebas aisladas usan SQLite, pero UAT debe validar MySQL real sin migrar schema. |
| Seguridad/accesos | 76% | Middleware por rol y ownership por recurso ya existen. Falta matriz formal si aparecen roles nuevos, firma/idempotencia de webhooks y externalizacion de secretos. |
| Integraciones Pagadetodo | 72% | Mock controlado cubre contratos principales. Falta sandbox oficial no productivo y comparativa de payload real. |
| Pruebas automatizadas | 86% en aislado / 55% contra MySQL real | Unit y Feature aislados pasan; la DB MySQL local/prod-like no esta validada desde esta copia. |
| Documentacion viva | 78% | Fases 31-33 estan bien documentadas; algunos docs generales seguian describiendo Fase 30/FE-3 y se actualizan en esta pasada. |
| Readiness para paralelo productivo | 72% | Viable como sandbox paralelo con subdominio/vhost y scheduler deshabilitado. No listo para reemplazar produccion ni para cobrar en vivo. |

## Diagnostico por modulo, pantalla y funcionalidad

Los porcentajes estiman avance funcional para operar con confianza en sandbox paralelo. No son aceptacion final de negocio hasta UAT con datos reales y sandbox oficial Pagadetodo.

| Modulo / pantalla | Rutas / menu | Avance | Estado actual | Pendientes para cierre |
| --- | --- | ---: | --- | --- |
| Login | `/login` | 88% | Login por `usuario`/password funciona en browser controlado. | Rate limit, bloqueo por intentos y UAT con usuarios reales. |
| Logout / sesion | `/logout` | 86% | Logout POST y sesion web normales. | Cookie de sesion distinta para ambiente paralelo (`SESSION_COOKIE`) para no chocar con produccion. |
| Public `/url` | `GET/POST /url` | 86% | Render guest y validacion http/https existentes; iframe usa sandbox. | Definir politicas CSP/frame y prueba con URLs reales permitidas. |
| Public `/` | `GET /`, `POST /` | 45% | `POST /` sigue vivo; `GET /` no muestra una pantalla publica real y termina redirigiendo al flujo autenticado/login. | Decidir si se retira o se restaura como formulario publico soportado. |
| Shell autenticado | `/main`, sidebars | 86% | Vue monta dashboard y menus por rol 1/2; browser admin/cliente fue validado con DB SQLite. | UAT con MySQL real; definir pantalla para roles no 1/2. |
| Dashboard | `/dashboard`, menu 0 | 82% | Devuelve estructura de sumatorias y soporta MySQL/SQLite. | Validar significado de metricas por rol y dataset real. |
| Notificaciones | `/notification/get`, Echo/Pusher | 62% | Polling HTTP funciona; canal privado definido. | Realtime websocket no validado; externalizar Pusher y probar con sandbox. |
| Roles | `/rol`, `/role`, menu 4 | 62% | Listado y alias `/role` vivos. | No hay CRUD completo de roles; `RoleController` legacy referencia `App\Role` ausente y debe eliminarse o corregirse. |
| Usuarios | `/user/*`, menu 3 | 78% | CRUD basico endurecido: no expone hash, password update condicional, validacion de usuario unico. | Whitelist de criterio en busqueda, manejo visible de errores y pruebas con DB real. |
| Estados | `/estado/*`, menu 7 | 70% | CRUD activar/desactivar y selector activo. | Whitelist de criterio, validaciones backend y offset acotado. |
| Ciudades | `/ciudad/*`, menu 8 | 70% | CRUD, selector y `listarCiudad` vivos. | Whitelist de criterio; cuando no hay busqueda ignora `offset`. |
| Clientes | `/cliente/*`, menu 9 | 86% | Listado, selector, alta, edicion y export aplican ownership para cliente. | Validaciones backend mas estrictas; confirmar semantica de `archivos.idpersona`. |
| Archivos de cliente | `/archivo/*` | 72% | Alta, listado, descarga y borrado validan propietario. | Descarga devuelve bytes sin headers/nombre; mimes `xlx/xlxs` tienen typos; mejorar errores de storage. |
| Consolidar clientes | `/cliente/consolidar`, menu 10 | 87% | Flujo admin con transaccion DB, locks y reasignacion de transacciones/cargos/archivos. | Prueba con volumen real y bitacora/auditoria operativa. |
| Depurar clientes | `/cliente/depurar`, menu 28 | 87% | Flujo admin todo-o-nada, valida elegibilidad y locks. | UAT con datos reales y reporte de no elegibles. |
| Ligas de pago unica | `/transaccion`, tipo 1, menu 1 | 83% | Listado, creacion web/API, export CSV streaming e importacion masiva existen. | Sandbox oficial; folios por `max()+1` requieren control de concurrencia. |
| Importacion masiva | `/transaccion/importar/*` | 78% | Proceso secuencial con progreso, cancelacion y bitacora descargable. | Prueba con archivos reales grandes y limpieza de estados/logs temporales. |
| Respuestas ligas | `/respuesta`, tipo 1, menu 2, `Service/EntregarPagoLiga*` | 72% | Persistencia y callback a cliente existen; ownership en vistas/export. | Firma/origen, idempotencia y reintentos de webhook. |
| Domiciliacion / liga recurrente | `/transaccion`, tipo 2, menu 11 | 82% | Generacion, frecuencia, proximo cargo e importacion masiva. | Sandbox oficial y reglas de cancelacion/reintento documentadas. |
| Respuestas domiciliacion | `/respuesta`, tipo 2, menu 12 | 72% | Reusa modulo de respuestas. | Mismos pendientes de webhooks/callback/idempotencia. |
| Cargos recurrentes | `/transaccionDom`, menu 13 | 79% | CRUD/listado, API de cargo, reportes y scheduler diario. | No habilitar scheduler en sandbox con misma DB; probar job solo con mock/sandbox. |
| Cancelar domiciliacion API | `POST CancelarDomiciliacion` | 76% | Ya no usa `$e` indefinido; permite `ClientReference` o `Token`; usa mock controlado. | Validar contrato real `CancelarDomiciliacionIndi`, idempotencia y estados. |
| SPEI generacion | `GenerarSpei`, tipo 3, menu 14 | 82% | Validacion temprana, mock y persistencia de CLABE/referencia. | Sandbox oficial para generacion/consulta/pago/cancelacion. |
| Consulta SPEI | `/consultaspei`, menu 22 | 80% | Listado/export, whitelist y filtro `ClientReference` corregido. | Validar eventos reales y export con volumen. |
| Pago SPEI | `/pagospei`, menu 23 | 80% | Listado/export/reporte, ownership y whitelist. | Validar callbacks y conciliacion con datos reales. |
| Cancelaciones SPEI | `/cancelaspei`, menu 24 | 78% | Listado/export con ownership. | Definir si cliente rol 2 debe tener acceso visible; validar cancelacion real. |
| Pago en caja | menu 14/15 | 52% | UI lo presenta, pero comparte tipo/ruta con SPEI y respuestas tipo 3. | Aclarar contrato funcional o separar nomenclatura/rutas para no confundir operacion. |
| Pago con terminal / lector | tipo 4, menu 26/27, `GenerarLigaLector` | 75% | Generacion mock y respuestas lector existen. | Validar contrato externo, cancelacion lector y QR real. |
| Reportes ligas | menu 18/19/21 | 83% | Reportes filtrados y export XLSX vivos para ligas, domiciliacion y terminal. | UAT con rangos, fechas nulas y datos reales. |
| Reporte SPEI | menu 20 | 84% | Reporte y export `reporteSpei.xlsx`. | UAT con conciliacion real. |
| Reporte cargos recurrentes | menu 25 | 84% | Reporte y export `reporteTransaccionesDom.xlsx`. | UAT con volumen y rangos reales. |
| API clientes externos | `Generar*`, `CargoDomiciliacion`, `CancelarDomiciliacion` | 72% | Autenticacion por `User`/`Password` payload, validaciones tempranas y mock. | Versionado, rate limit, idempotencia, errores consistentes, sandbox oficial. |
| Webhooks proveedor | `Service/*` | 50% | Rutas vivas y persistencia basica. | Firma/origen, esquema, deduplicacion, reintentos y observabilidad. |
| Scheduler | `ejecutarCron`, `revisarStatus` | 70% | Registrado y visible. | Deshabilitar en paralelo; extraer a comandos/jobs antes de produccion definitiva. |
| Exportaciones generales | varios `/exportar` | 81% | Exportaciones criticas filtran por propietario; transaccion general usa CSV streaming. | Revalidar memoria/tiempo con dataset real. |

## Estado por rol y tipo de acceso

| Rol / acceso | Avance | Lo que funciona | Pendientes |
| --- | ---: | --- | --- |
| Administrador `idrol=1` | 88% | Acceso total al grupo protegido, browser OK en dataset controlado, puede operar catalogos, transacciones, reportes, usuarios y roles. | UAT con DB real, politicas formales por accion y no solo por ruta. |
| Cliente operativo `idrol=2` | 87% | Sidebar acotado; middleware limita rutas; controladores y exports restringen recursos propios. | Validar con usuarios reales y revisar si SPEI/cancelaciones deben exponerse o no. |
| Otros roles | 58% | Bloqueo por defecto `403`. | Definir si existen roles funcionales adicionales; hoy no tienen experiencia operativa. |
| Guest publico | 76% | `/login`, `/url` y redirecciones basicas funcionan. | Decidir destino real de `/`; hardening publico. |
| Cliente API externo | 72% | Contratos principales cubiertos por mock y validaciones tempranas. | Auth robusta, rate limit, idempotencia, versionado y sandbox oficial. |
| Proveedor/webhook externo | 50% | `Service/*` recibe payloads. | Firma/origen, deduplicacion, esquema y reintentos. |
| Scheduler / sistema | 70% | Tareas registradas. | No ejecutar doble contra la misma DB; agregar locks y comando dedicado. |

## Errores, riesgos y mejoras prioritarias

### Criticos antes de pruebas en servidor compartiendo DB

1. No activar scheduler de la nueva version en paralelo; podria duplicar cargos o revisar estados contra la misma DB.
2. No usar credenciales productivas en `PAGADETODO_MOCK=false` hasta tener sandbox oficial separado.
3. Definir subdominio/vhost separado; publicar bajo subcarpeta es riesgoso porque los assets son relativos a raiz (`js/app.js`, `css/plantilla.css`).
4. Crear `SESSION_COOKIE`, `CACHE_PREFIX` y logs separados para que las sesiones/caches de ambas versiones no colisionen.
5. Confirmar que no se ejecutaran migraciones sobre la DB productiva; el schema real viene del dump historico y el codigo actual no requiere migracion estructural.

### Altos

1. Externalizar credenciales/endpoints Pagadetodo y Pusher; hoy siguen hardcoded en controladores/frontend legacy.
2. Resolver la estrategia PHP 8.3 en Ubuntu 20.04: contenedor o migracion controlada de SO. No asumir PPA Focal.
3. Actualizar Composer a version moderna para poder ejecutar `composer audit`.
4. Tratar `npm audit` completo en carril separado; runtime omit-dev esta limpio, pero tooling tiene 29 hallazgos.
5. Endurecer webhooks con firma/origen/idempotencia.
6. Limpiar archivos accidentales en raiz: `(window.CentroDeCobrosVueRoot.menu`, `.nav-link').click()`, `document.body.className`, etc.

### Medios

1. Agregar whitelists a `UserController`, `EstadoController` y `CiudadController`.
2. Corregir headers/nombre en descarga de archivos.
3. Retirar `RoleController` legacy o crear `App\Role` si se decide conservarlo.
4. Separar servicios Pagadetodo de controladores grandes.
5. Agregar locks transaccionales para generacion de folios por `max()+1`.

## Plan optimo para subir version paralela via GitHub

### 1. Versionar y preparar release

Esta carpeta no es un checkout Git activo. Antes de servidor:

1. Crear repositorio remoto privado en GitHub o usar el repo existente.
2. Inicializar/clonar en una carpeta limpia.
3. Excluir `.env`, `vendor/`, `node_modules/`, `storage/logs/`, `storage/phase33_browser.sqlite` y archivos accidentales de raiz.
4. Subir branch/tag de sandbox, por ejemplo:
   - branch: `phase33-sandbox-paralelo`
   - tag: `phase33-sandbox-2026-05-27`
5. Confirmar que el release contiene assets compilados o definir build en CI.

### 2. Estrategia de runtime en servidor

Estado informado del servidor: Ubuntu 20.04 + PHP 7.4 + Apache + MySQL 8, sin Node/npm.

Opcion recomendada si se mantiene Ubuntu 20.04 sin tocar la app actual:
- Desplegar la nueva version en contenedor PHP 8.3/FPM o en un servicio aislado, y publicar por Apache reverse proxy/vhost.
- Ventaja: no altera PHP 7.4 actual ni el vhost existente.
- Pendiente: instalar y operar Docker/Compose o un runtime equivalente.

Opcion recomendada si se permite mantenimiento mayor:
- Migrar/actualizar a Ubuntu 22.04/24.04 o nuevo host, instalar PHP 7.4-FPM y PHP 8.3-FPM desde repos soportados/PPA, y asignar cada vhost a su socket FPM.
- Ventaja: modelo limpio de coexistencia PHP por vhost.
- Riesgo: requiere plan de rollback de sistema y prueba de la version actual con PHP 7.4-FPM.

No recomendado:
- Instalar `libapache2-mod-php8.3` global junto a `libapache2-mod-php7.4`. Para coexistencia por sitio, usar PHP-FPM y `SetHandler` por vhost.

### 3. Estructura en servidor

Mantener la version actual intacta:

```text
/var/www/centro              # version actual PHP 7.4
/var/www/centro-v12-sandbox  # nueva version Laravel 12/PHP 8.3
```

Usar subdominio o vhost separado:

```text
centro.actual.example.com     -> /var/www/centro/public       -> PHP 7.4
centro-v12.example.com        -> /var/www/centro-v12-sandbox/public -> PHP 8.3
```

Ejemplo de vhost Apache para PHP 8.3-FPM:

```apache
<VirtualHost *:80>
    ServerName centro-v12.example.com
    DocumentRoot /var/www/centro-v12-sandbox/public

    <Directory /var/www/centro-v12-sandbox/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost/"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/centro-v12-error.log
    CustomLog ${APACHE_LOG_DIR}/centro-v12-access.log combined
</VirtualHost>
```

Apache requiere `proxy`, `proxy_fcgi`, `setenvif`, `rewrite`, `headers` y, si aplica, `ssl`.

### 4. Variables `.env` para sandbox paralelo

Usar la misma DB solo con controles:

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://centro-v12.example.com
SESSION_COOKIE=centro_v12_session
CACHE_PREFIX=centro_v12_
QUEUE_DRIVER=sync
BROADCAST_DRIVER=log
PAGADETODO_MOCK=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=centrodecobros
DB_USERNAME=usuario_controlado
DB_PASSWORD=***
```

Reglas:
- No correr `php artisan migrate` contra esta DB.
- No habilitar cron/scheduler de la nueva version.
- Usar usuarios de prueba con `productivo=0` cuando sea posible.
- Si se necesita probar escritura real, registrar casos y hacer respaldo/restore puntual.

### 5. Instalacion de herramientas

En servidor productivo no es obligatorio tener Node si los assets ya llegan compilados desde CI/GitHub. Si se va a compilar en el host, instalar Node 20 y npm desde NodeSource y ejecutar `npm ci && npm run production`.

PHP 8.3 debe tener extensiones equivalentes a las usadas localmente:

```text
php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml
php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
php8.3-sqlite3 unzip git curl
```

Si el host se mantiene en Ubuntu 20.04, validar primero disponibilidad real de esos paquetes. Si no existen para Focal, usar contenedor o migrar host.

### 6. Deploy minimo por GitHub

1. Respaldar DB y carpeta actual.
2. Clonar branch/tag en `/var/www/centro-v12-sandbox`.
3. Crear `.env` de sandbox y `APP_KEY` propio.
4. Instalar dependencias con PHP 8.3:

```bash
php8.3 /usr/local/bin/composer install --no-dev --optimize-autoloader
php8.3 artisan key:generate --force
php8.3 artisan config:clear
php8.3 artisan route:clear
php8.3 artisan view:clear
php8.3 artisan storage:link
```

5. Si no llegan assets compilados:

```bash
npm ci
npm run production
```

6. Ajustar permisos:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

7. Activar vhost y recargar Apache.
8. Ejecutar smoke de `/login`, `/url`, `/main`, `/dashboard`, `/cliente`, `/transaccion`, reportes y exports.

### 7. Criterio de avance a siguiente fase

La version paralela puede abrirse a usuarios internos si:

1. vhost PHP 8.3 responde sin afectar `/var/www/centro`;
2. `php8.3 artisan route:list` y `schedule:list` estan OK;
3. Unit tests pasan en servidor;
4. build/assets estan presentes;
5. login admin/cliente funciona con misma DB;
6. scheduler nuevo esta deshabilitado;
7. `PAGADETODO_MOCK=true` o sandbox oficial no productivo confirmado;
8. logs separados no muestran errores 500;
9. existe rollback documentado: deshabilitar vhost nuevo y dejar intacta la version actual.

## Siguientes pasos optimos

1. Decidir runtime PHP 8.3 en el servidor: contenedor en Ubuntu 20.04 o migracion/control de host a 22.04/24.04.
2. Publicar el proyecto en GitHub desde una copia limpia y etiquetada.
3. Desplegar `/var/www/centro-v12-sandbox` con subdominio propio, misma DB, `SESSION_COOKIE` separado, `PAGADETODO_MOCK=true` y scheduler deshabilitado.
4. Ejecutar UAT interno por rol con datos reales controlados.
5. Obtener credenciales/URL de Pagadetodo sandbox oficial y comparar contra mocks.
6. Endurecer webhooks/idempotencia y externalizar secretos antes de cualquier reemplazo de produccion.
