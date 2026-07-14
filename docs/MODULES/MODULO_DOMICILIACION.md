# Modulo: Domiciliacion y cargos recurrentes

Ultima actualizacion: 2026-07-10

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
- `GET domiciliacion-activa/exportar`

## Rutas API legacy sin prefijo `/api`

- `POST GenerarLigaDomiciliacion`
- `POST CargoDomiciliacion`
- `POST CancelarDomiciliacion`

## Flujo operativo

- Alta de liga de domiciliacion: genera registro en `transacciones` con `tipo=2`.
- La liga domiciliada nace como `transacciones.condicion=0` (`Pendiente`) hasta que llega respuesta aprobada.
- Si llega respuesta aprobada con token (`respuestas.number_tkn`), queda `condicion=1` (`Activo`).
- Si llega respuesta aprobada sin token, queda `condicion=5` (`Error`).
- Si vence sin respuesta aprobada, el comando diario `transacciones:sincronizar-status` la marca `condicion=4` (`Vencido`) a partir de las 00:05 de Hermosillo.
- Cargo recurrente: genera/actualiza registros en `transaccionesDom`.
- Cancelacion: registra cancelacion e interactua con Pagadetodo si aplica.
- Callback cliente: en `legacy/shadow`, solo el cargo automatico usa `users.ligaRecurrente`; en `active`, cargos manuales/API/automaticos pueden notificarse por suscripcion.
- Scheduler diario ejecuta `TransaccionDomController@ejecutarCron`.
- El comando diario de reconciliacion registra duracion y filas afectadas; no realiza llamadas a Pagadetodo ni cargos.
- `Domiciliación Activa` lista domiciliaciones tipo `2`, productivas, con respuesta aprobada y `condicion` `Activo` o `Cancelado`.
- Desde `Domiciliación Activa`, el cargo manual reutiliza `POST transaccionDom/registrar`; cuando el cargo manual queda aprobado, avanza `ProximoCargo` con la frecuencia configurada de la domiciliacion.
- Desde `Domiciliación Activa`, la accion de calendario permite actualizar manualmente `ProximoCargo` con `PUT transaccion/proximo-cargo`.
- La cancelacion desde `Domiciliación Activa` reutiliza `PUT transaccion/rechazar`; si la cancelacion queda persistida, el endpoint interno devuelve `error=""` aunque Pagadetodo haya entregado un mensaje tecnico en una excepcion controlada.
- Las acciones directas `Cancelar domiciliación` y `Realizar cargo recurrente manual` muestran confirmacion y no ejecutan Axios si el usuario cancela. El calendario no agrega un segundo dialogo porque ya abre un modal de captura y validacion.
- La exportacion de `Domiciliación Activa` descarga `domiciliaciones_activas.csv` y reutiliza los filtros del listado: texto, criterio, status, ownership, productivo y existencia de respuesta aprobada.

## Cargos Recurrentes listado

- La búsqueda por texto incluye `Folio Operación` (`transaccionesDom.foliocpagos`) y `Núm. Autorización` (`transaccionesDom.auth`); los mismos criterios se respetan al exportar.
- La columna `Status` conserva los valores recibidos y aplica el contrato visual financiero: `approved` usa el badge verde de Activo, `denied` el badge rojo con texto negro de Vencido y `error` el badge amarillo de Pendiente.

- `TransaccionDom.vue` lista intentos/cargos recurrentes desde `transaccionesDom`.
- `Desde` y `Hasta` filtran `transaccionesDom.fecha`.
- Al entrar al modulo, el frontend usa el rango de los ultimos 30 dias, con la fecha actual como `Hasta`.
- `Limpiar` borra `Texto a buscar`, `Desde` y `Hasta`.
- El selector de cantidad inicia en `50` registros.
- Usuarios no admin no ven las columnas administrativas `Code`, `Message` ni `Productivo`; el backend conserva ownership/productivo.

## Tablas involucradas

- `transacciones`
- `transaccionesDom`
- `cancelacionesDom`
- `respuestas`
- `clientes`
- `users`
- `webhook_events`, `webhook_deliveries` y tablas relacionadas cuando el cliente usa `shadow/active`.

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
- En validacion local usar `PAGADETODO_MOCK=true`; llamadas reales Pagadetodo solo desde servidor/IP autorizado.

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
- El JOIN de `ejecutarCron` puede devolver una domiciliacion mas de una vez si existen varias respuestas aprobadas. No se corrigio en la implementacion webhook por decision del propietario; queda como pendiente financiero separado.
- Los campos `ProximoCargoBase` e `intentos` requieren ejecutar migracion antes de desplegar codigo que los consulte.

## Diagnostico de `ejecutarCron`

- Selecciona `transacciones` tipo `2` con `condicion=1`, `productivo=1`, `users.recurrente=1`, respuesta aprobada y `ProximoCargo` igual al dia actual.
- Usa `respuestas.number_tkn`, `cc_expmonth` y `cc_expyear` para llamar `PagarDomiciliacionIndi`.
- Guarda cada intento en `transaccionesDom`.
- Si el cargo responde `code=00` y `status=approved`, calcula el siguiente `ProximoCargo` desde la fecha programada vigente, no desde la fecha real de ejecucion.
- Si falla, reprograma al dia siguiente.
- `intentos` se sincroniza con los `transaccionesDom` fallidos (`code != 00`) y se reinicia a `0` cuando el cargo es aprobado.
- `ProximoCargoBase` se conserva como ancla/auditoria de la primera fecha configurada; no sustituye el avance desde la fecha programada vigente.
- El cargo manual aprobado desde `Domiciliación Activa` usa la misma regla de avance de frecuencia, pero el endpoint publico `CargoDomiciliacion` conserva su contrato externo y no fue modificado para cambiar `ProximoCargo`.

## Pruebas recomendadas

- Unit para validaciones y helpers si se tocan.
- Feature SQLite con `PAGADETODO_MOCK=true` si cambia API, ownership o importacion.
- Smoke UI admin/cliente para listados y reportes.
- Scheduler solo en ambiente controlado, nunca contra produccion sin autorizacion.
- Browser QA para `Domiciliación Activa`: filtros, total de registros, cancelar y cargo manual con Pagadetodo mock local o servidor/IP autorizado.
- Al probar cancelacion, validar que falta de token aprobado responda 422 controlado y no cambie `transacciones.condicion`.
- Validar exportacion CSV con filtro admin/cliente y confirmar que no incluya domiciliaciones ajenas ni sin respuesta aprobada.

## Pendientes y mejoras

- Extraer scheduler a comandos/jobs dedicados con locks.
- Conservar evidencia sanitizada de pruebas Pagadetodo servidor sandbox/productivo.
- Documentar matriz de estados/frecuencias con ejemplos reales.
- Agregar pruebas de concurrencia/idempotencia de cargos.
- Corregir en una tarea separada la multiplicidad del JOIN y agregar locks antes de ampliar concurrencia del cron.
- Ejecutar migracion controlada para `ProximoCargoBase` e `intentos` antes de usar esta funcionalidad en servidor.

## Corte diagnostico 2026-06-07

- `domiciliacion-activa`, `transaccionDom/*`, `GenerarLigaDomiciliacion`, `CargoDomiciliacion` y `CancelarDomiciliacion` cargan en `route:list`.
- `DomiciliacionAndPaymentsFeatureTest` esta cubierto dentro del carril Feature aislado WAMP/SQLite verde.
- `schedule:list` muestra `TransaccionDomController@ejecutarCron` diario 07:00; no se activo ni se modifico.
- Addendum 2026-06-08: Pagadetodo fue probado exitosamente desde servidor en sandbox y productivo, confirmado por el propietario; local no puede reproducir llamadas reales por restriccion de IP de origen.
- Mantener scheduler apagado en cualquier sandbox que comparta DB hasta tener locks y una sola instancia garantizada.
