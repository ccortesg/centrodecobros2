# Modulo: Domiciliacion y cargos recurrentes

Ultima actualizacion: 2026-06-17

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
- Desde `Domiciliación Activa`, el cargo manual reutiliza `POST transaccionDom/registrar`; cuando el cargo manual queda aprobado, avanza `ProximoCargo` con la frecuencia configurada de la domiciliacion.
- Desde `Domiciliación Activa`, la accion de calendario permite actualizar manualmente `ProximoCargo` con `PUT transaccion/proximo-cargo`.
- La cancelacion desde `Domiciliación Activa` reutiliza `PUT transaccion/rechazar`; si la cancelacion queda persistida, el endpoint interno devuelve `error=""` aunque Pagadetodo haya entregado un mensaje tecnico en una excepcion controlada.

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

## Pendientes y mejoras

- Extraer scheduler a comandos/jobs dedicados con locks.
- Conservar evidencia sanitizada de pruebas Pagadetodo servidor sandbox/productivo.
- Documentar matriz de estados/frecuencias con ejemplos reales.
- Agregar pruebas de concurrencia/idempotencia de cargos.
- Ejecutar migracion controlada para `ProximoCargoBase` e `intentos` antes de usar esta funcionalidad en servidor.

## Corte diagnostico 2026-06-07

- `domiciliacion-activa`, `transaccionDom/*`, `GenerarLigaDomiciliacion`, `CargoDomiciliacion` y `CancelarDomiciliacion` cargan en `route:list`.
- `DomiciliacionAndPaymentsFeatureTest` esta cubierto dentro del carril Feature aislado WAMP/SQLite verde.
- `schedule:list` muestra `TransaccionDomController@ejecutarCron` diario 07:00; no se activo ni se modifico.
- Addendum 2026-06-08: Pagadetodo fue probado exitosamente desde servidor en sandbox y productivo, confirmado por el propietario; local no puede reproducir llamadas reales por restriccion de IP de origen.
- Mantener scheduler apagado en cualquier sandbox que comparta DB hasta tener locks y una sola instancia garantizada.
