# Rutas y flujo de navegacion

Ultima actualizacion: 2026-06-03

## Contexto operativo vigente

- Repositorio activo: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`.
- Rama vigente: `main`.
- Produccion actual: Docker en servidor, confirmado por el propietario el 2026-06-03.
- No crear copias nuevas para cambios futuros; trabajar sobre el repo actual.

## 1) Mapa de rutas web

`php artisan route:list` registra 97 rutas.

### Guest

- `GET /`: entrada publica historica; hoy no renderiza formulario funcional propio y termina redirigiendo hacia flujo autenticado/login.
- `POST /`: registro publico legacy de transaccion.
- `GET /url`: pantalla guest para abrir URL segura en iframe sandbox.
- `POST /url`: valida URL `http/https` y redirige de vuelta con mensaje.
- `GET /login` y `POST /login`.

### Auth

- `POST /logout`
- `GET /dashboard`
- `POST /notification/get`
- `GET /main`

### Grupo `Administrador`

El middleware `Administrador` esta activo desde Fase 31:

- `idrol=1`: acceso total al grupo protegido.
- `idrol=2`: acceso limitado a clientes, archivos, ligas visibles, domiciliacion visible, respuestas de lectura/export, importacion y reportes operativos.
- otros roles: `403`.

Fase 32 agrego ownership por recurso dentro de controladores para que el rol cliente opere solo registros propios en clientes, archivos, transacciones, respuestas, SPEI, domiciliacion y exportaciones criticas.

Superficies del grupo protegido:

- estados y ciudades;
- clientes, archivos, consolidar y depurar;
- transacciones, importacion masiva y reportes;
- respuestas;
- transacciones domiciliadas y cargos recurrentes;
- consultas, pagos y cancelaciones SPEI;
- roles y usuarios.

## 2) Rutas API sin prefijo `/api`

`RouteServiceProvider::mapApiRoutes()` conserva intencionalmente el comportamiento legacy sin `prefix('api')`.

Webhooks/proveedor:

- `POST Service/EntregarPagoLiga`
- `POST Service/EntregarPagoLigaToken`
- `POST Service/EntregarPagoLector`

SPEI:

- `GET Service/ConsultaClabe`
- `POST Service/PagoClabe`
- `POST Service/CancelaClabe`

API para clientes:

- `POST GenerarLigaPago`
- `POST GenerarLigaDomiciliacion`
- `POST CargoDomiciliacion`
- `POST CancelarDomiciliacion`
- `POST GenerarSpei`
- `POST GenerarLigaLector`

Fases 31-33 agregaron validaciones tempranas y mock controlado Pagadetodo para estos contratos, pero la autenticacion sigue basada en `User`/`Password` enviados en payload.

Fase 34 no cambia nombres de rutas ni payloads externos de exito. Endurece internamente:

- `Service/EntregarPagoLiga` y `Service/EntregarPagoLigaToken`: validacion minima e idempotencia por `idtransaccion + reference`.
- `Service/EntregarPagoLector`: validacion minima e idempotencia por `idtransaccion + reference`.
- `Service/ConsultaClabe`: errores nulos corregidos en referencias vacias/no encontradas.
- `Service/PagoClabe`: validacion minima e idempotencia por `transaccion`.
- `Service/CancelaClabe`: validacion minima e idempotencia por `transaccion + autorizacion`.

## 3) Navegacion frontend

- El layout principal usa `resources/views/principal.blade.php`.
- `resources/views/contenido/contenido.blade.php` renderiza componentes Vue segun `menu`.
- El admin ve menus de catalogos, ligas, domiciliacion, SPEI, caja, terminal, reportes y acceso.
- El cliente ve clientes, ligas, domiciliacion y reportes acotados.
- Vue 3 monta en `resources/assets/js/app.js` y registra componentes por tag legacy.

## 4) Endpoints criticos

- Generacion/cancelacion de transacciones y domiciliaciones.
- Scheduler de cargos recurrentes y revision de status.
- Webhooks `Service/*`.
- Reportes/exportaciones financieras.
- Usuarios/roles.

## 5) Observaciones vigentes

- No hay ruta actual hacia `ArticuloController`; la observacion historica de ruta rota ya no aplica al `route:list` vigente.
- `RoleController` legacy si permanece en codigo, pero las rutas `/rol` y `/role` apuntan a `RolController`.
- Publicar bajo subcarpeta en Apache puede romper o mezclar assets porque el HTML usa rutas como `js/app.js` y `css/plantilla.css`; para un sandbox futuro usar subdominio/vhost separado o configuracion Docker equivalente.
- En ambientes paralelos con la misma DB, no habilitar scheduler duplicado.
- En produccion Docker, validar el compose real antes de documentar comandos de servicios o reinicios.
