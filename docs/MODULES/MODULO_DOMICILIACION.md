# Modulo: Domiciliacion y cargos recurrentes

Ultima actualizacion: 2026-06-03

## Proposito

Gestionar generacion de ligas de domiciliacion, cargos recurrentes, cancelaciones y reportes asociados.

## Archivos clave

- `app/Http/Controllers/TransaccionController.php`
- `app/Http/Controllers/TransaccionDomController.php`
- `app/TransaccionDom.php`
- `resources/assets/js/components/TransaccionDom.vue`
- `resources/assets/js/components/ReporteLigasDom.vue`
- `resources/assets/js/components/ReporteCargosRecurrentes.vue`

## Rutas web

- `GET transaccionDom`
- `POST transaccionDom/registrar`
- `PUT transaccionDom/actualizar`
- `PUT transaccionDom/eliminar`
- `GET transaccionDom/exportar`
- `GET transaccionDom/reporteTransaccionesDom`
- `GET transaccionDom/exportarTransacciones`
- `POST transaccion/registrarDom`
- `GET transaccion/selectDomiciliacion`

## Rutas API legacy sin prefijo `/api`

- `POST GenerarLigaDomiciliacion`
- `POST CargoDomiciliacion`
- `POST CancelarDomiciliacion`

## Flujo operativo

- Alta de liga de domiciliacion: genera registro en `transacciones` con `tipo=2`.
- Cargo recurrente: genera/actualiza registros en `transaccionesDom`.
- Cancelacion: registra cancelacion e interactua con Pagadetodo si aplica.
- Callback cliente: usa `users.ligaRecurrente` cuando el flujo lo requiere.
- Scheduler diario ejecuta `TransaccionDomController@ejecutarCron`.

## Tablas involucradas

- `transacciones`
- `transaccionesDom`
- `cancelacionesDom`
- `respuestas`
- `clientes`
- `users`

## Acceso por rol

- Admin: operacion completa.
- Cliente: acceso acotado a sus registros permitidos por allowlist y ownership.
- Otros roles: `403`.

## Integracion Pagadetodo

- Configuracion en `config/services.php` bajo `services.pagadetodo`.
- Variables relevantes:
  - `PAGADETODO_URL_GENERAR_DOMICILIACION`
  - `PAGADETODO_URL_CARGO_DOMICILIACION`
  - `PAGADETODO_URL_CANCELAR_DOMICILIACION`
  - credenciales e IDs `PAGADETODO_DOM_*` y `PAGADETODO_DOM_BA_*`
- En validacion sin sandbox oficial usar `PAGADETODO_MOCK=true`.

## Importacion masiva de ligas de domiciliacion

- Disponible desde Generacion de Ligas (`tipo=2`).
- Columnas: `Cliente`, `Forma de pago`, `Descripcion`, `Monto`, `Fecha Expiracion`, `Referencia`, `Frecuencia`.
- `Frecuencia` acepta numero o texto del catalogo: `Semanal`, `Mensual`, `Bimestral`, `Semestral`, `Anual`.
- Proceso secuencial con progreso, cancelacion y bitacora descargable `xlsx`.

## Riesgos

- Scheduler financiero con efectos reales.
- Metodos de controlador usados como jobs.
- Reglas temporales y de estado embebidas.
- Riesgo de duplicar cargos si dos instancias ejecutan scheduler contra la misma DB.

## Pruebas recomendadas

- Unit para validaciones y helpers si se tocan.
- Feature SQLite con `PAGADETODO_MOCK=true` si cambia API, ownership o importacion.
- Smoke UI admin/cliente para listados y reportes.
- Scheduler solo en ambiente controlado, nunca contra produccion sin autorizacion.

## Pendientes y mejoras

- Extraer scheduler a comandos/jobs dedicados con locks.
- Validar contrato con sandbox oficial Pagadetodo.
- Documentar matriz de estados/frecuencias con ejemplos reales.
- Agregar pruebas de concurrencia/idempotencia de cargos.
