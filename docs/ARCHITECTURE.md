# Arquitectura real del sistema

Ultima actualizacion: 2026-06-03

## 1. Vista general

- Patron predominante: Laravel MVC clasico con shell autenticado Blade y componentes Vue 3 montados en el DOM legacy.
- Backend: controladores con validacion, reglas de negocio, integraciones HTTP externas y persistencia Eloquent/Query Builder.
- Frontend: Vue 3 + Vite para la aplicacion autenticada; lane `plantilla.*` y guest assets conservadas como contrato publico.
- Base de datos: MySQL operativo en produccion; migrations historicas no representan todo el esquema real.
- Produccion: Docker en servidor, sin archivos Docker versionados en este repositorio.

## 2. Capas reales

### Presentacion

- `resources/views/principal.blade.php`: shell autenticado y frontera estable.
- `resources/views/contenido/contenido.blade.php`: switchboard que decide que componente Vue renderizar segun el menu.
- `resources/assets/js/app.js`: registro de componentes Vue 3.
- `resources/assets/js/bootstrap.js`: axios, Echo/Pusher y globals requeridos por componentes legacy.
- `public/js/plantilla.js`, `public/css/plantilla.css`, `public/js/guest-public.js`: contrato publico generado por build.

### Aplicacion

- Los controladores HTTP contienen la mayor parte de la logica de negocio.
- No existe una capa de servicios o dominio consistente.
- El scheduler llama metodos de controlador, lo que mantiene acoplamiento entre infraestructura y dominio.

### Persistencia

- Modelos Eloquent por tabla principal.
- Query Builder para reportes, joins y filtros complejos.
- Relaciones con FK incompletas o historicas; varias relaciones deben inferirse desde codigo.

### Integraciones

- Pagadetodo via Guzzle y endpoints configurados en `config/services.php`.
- Pusher/Echo para notificaciones si existen variables `VITE_PUSHER_*` y broadcasting configurado.
- SMTP/Postmark para correo.
- TeleSign queda como dependencia historica residual; el flujo publico OTP/SMS fue retirado.

## 3. Modulos funcionales

- Identidad y catalogos: clientes, personas, usuarios, roles, estados, ciudades, archivos.
- Consolidacion/depuracion de clientes.
- Transacciones: ligas, caja, terminal y SPEI.
- Domiciliacion y cargos recurrentes.
- Respuestas/webhooks de proveedor.
- SPEI: consulta, pago y cancelacion.
- Dashboard y notificaciones.
- Reportes y exportaciones.

## 4. Acoplamientos criticos

- `app/Http/Controllers/TransaccionController.php` concentra generacion de ligas, SPEI, lector, callbacks, webhooks SPEI y exportaciones.
- `app/Http/Controllers/TransaccionDomController.php` concentra cargos recurrentes y scheduler diario.
- `app/Http/Controllers/RespuestaController.php` recibe respuestas de ligas y lector.
- Los payloads externos usan nombres legacy como `User`, `Password`, `BusinessID`, `IntegrationID`, `reference`, `transaccion` y `autorizacion`.
- El frontend y backend dependen de nombres exactos de campos historicos.

## 5. Flujo principal

1. Usuario autenticado entra a `/main`.
2. El menu Vue cambia el valor de `menu` y `contenido.blade.php` renderiza el componente.
3. El componente consume rutas web protegidas o API legacy sin prefijo `/api`.
4. El controlador valida, aplica rol/ownership, arma payload, invoca proveedor si aplica y persiste.
5. Reportes/exportaciones consultan datos acotados por rol.
6. Webhooks `Service/*` actualizan respuestas, transacciones SPEI o callbacks segun contrato.

## 6. Diagnostico arquitectonico

- El sistema es funcional y ya opera en produccion Docker.
- La deuda principal esta en controladores monoliticos, schema historico, scheduler acoplado y contratos externos directos.
- La estrategia mas segura es evolucion incremental con pruebas por modulo, no refactors amplios.
- Cualquier cambio de integracion debe conservar rutas/payloads publicos hasta tener sandbox oficial y evidencia.
