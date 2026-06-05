# Modulo: Domiciliacion y cargos recurrentes

Ultima actualizacion: 2026-06-04

## Proposito

Gestionar generacion de ligas de domiciliacion, cargos recurrentes, cancelaciones y reportes asociados.

## Archivos clave

- `app/Http/Controllers/TransaccionController.php`
- `app/Http/Controllers/TransaccionDomController.php`
- `app/TransaccionDom.php`
- `resources/assets/js/components/TransaccionDom.vue`
- `resources/assets/js/components/DomiciliacionActiva.vue`
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
- `GET domiciliacion-activa`

## Rutas API legacy sin prefijo `/api`

- `POST GenerarLigaDomiciliacion`
- `POST CargoDomiciliacion`
- `POST CancelarDomiciliacion`

## Flujo operativo

- Alta de liga de domiciliacion: genera registro en `transacciones` con `tipo=2`.
- La liga domiciliada nace como `transacciones.condicion=0` (`Pendiente`) hasta que llega respuesta aprobada.
- Si llega respuesta aprobada con token (`respuestas.number_tkn`), queda `condicion=1` (`Activo`).
- Si llega respuesta aprobada sin token, queda `condicion=5` (`Error`).
- Si vence sin respuesta aprobada, `TransaccionController@revisarStatus` la marca `condicion=4` (`Vencido`).
- Cargo recurrente: genera/actualiza registros en `transaccionesDom`.
- Cancelacion: registra cancelacion e interactua con Pagadetodo si aplica.
- Callback cliente: usa `users.ligaRecurrente` cuando el flujo lo requiere.
- Scheduler diario ejecuta `TransaccionDomController@ejecutarCron`.
- `Domiciliación Activa` lista domiciliaciones tipo `2`, productivas, con respuesta aprobada y `condicion` `Activo` o `Cancelado`.
- Desde `Domiciliación Activa`, el cargo manual reutiliza `POST transaccionDom/registrar` y la cancelacion reutiliza `PUT transaccion/rechazar`.

## Tablas involucradas

- `transacciones`
- `transaccionesDom`
- `cancelacionesDom`
- `respuestas`
- `clientes`
- `users`

Campos de control agregados a `transacciones`:

- `ProximoCargoBase`: fecha base/auditoria de la primera fecha de proximo cargo configurada.
- `intentos`: conteo de cargos recurrentes fallidos asociados a la domiciliacion.

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
- Los campos `ProximoCargoBase` e `intentos` requieren ejecutar migracion antes de desplegar codigo que los consulte.

## Diagnostico de `ejecutarCron`

- Selecciona `transacciones` tipo `2` con `condicion=1`, `productivo=1`, `users.recurrente=1`, respuesta aprobada y `ProximoCargo` igual al dia actual.
- Usa `respuestas.number_tkn`, `cc_expmonth` y `cc_expyear` para llamar `PagarDomiciliacionIndi`.
- Guarda cada intento en `transaccionesDom`.
- Si el cargo responde `code=00` y `status=approved`, calcula el siguiente `ProximoCargo` desde la fecha programada vigente, no desde la fecha real de ejecucion.
- Si falla, reprograma al dia siguiente.
- `intentos` se sincroniza con los `transaccionesDom` fallidos (`code != 00`) y se reinicia a `0` cuando el cargo es aprobado.
- `ProximoCargoBase` se conserva como ancla/auditoria de la primera fecha configurada; no sustituye el avance desde la fecha programada vigente.

## Pruebas recomendadas

- Unit para validaciones y helpers si se tocan.
- Feature SQLite con `PAGADETODO_MOCK=true` si cambia API, ownership o importacion.
- Smoke UI admin/cliente para listados y reportes.
- Scheduler solo en ambiente controlado, nunca contra produccion sin autorizacion.
- Browser QA para `Domiciliación Activa`: filtros, total de registros, cancelar y cargo manual con Pagadetodo mock o sandbox oficial.

## Pendientes y mejoras

- Extraer scheduler a comandos/jobs dedicados con locks.
- Validar contrato con sandbox oficial Pagadetodo.
- Documentar matriz de estados/frecuencias con ejemplos reales.
- Agregar pruebas de concurrencia/idempotencia de cargos.
- Ejecutar migracion controlada para `ProximoCargoBase` e `intentos` antes de usar esta funcionalidad en servidor.
