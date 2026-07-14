# Modulo: Transacciones, ligas, caja, terminal y SPEI

Ultima actualizacion: 2026-07-01

## Proposito

Generar referencias/ligas y registrar estado operativo de cobros por distintos tipos de transaccion.

## Archivos clave

- `app/Http/Controllers/TransaccionController.php`
- `app/Transaccion.php`
- `resources/assets/js/components/Transaccion.vue`
- `resources/assets/js/components/ReporteLigas.vue`
- `resources/assets/js/components/ReporteLigasDom.vue`
- `resources/assets/js/components/ReporteSpei.vue`
- `resources/assets/js/components/ReporteCargosRecurrentes.vue`
- `config/services.php`

## Tipos funcionales

- `tipo=1`: Liga de Pago Unica.
- `tipo=2`: Liga de Pago Domiciliacion/Recurrente.
- `tipo=3`: Referencia SPEI.
- `tipo=4`: Liga de Pago Terminal.

`ReporteLigas.vue` soporta `tipo=4` con el titulo Reporte Ingresos Pago con Terminal.

## Rutas web

- `GET transaccion`
- `POST transaccion/registrar`
- `POST transaccion/registrarDom`
- `POST transaccion/registrarSpei`
- `POST transaccion/registrarLector`
- `PUT transaccion/actualizar`
- `PUT transaccion/eliminar`
- `PUT transaccion/activar`
- `PUT transaccion/desactivar`
- `PUT transaccion/rechazar`
- `GET transaccion/exportar`
- `GET transaccion/reporteTransacciones`
- `GET transaccion/exportarTransacciones`
- `GET transaccion/selectDomiciliacion`
- `GET domiciliacion-activa`
- `GET domiciliacion-activa/exportar`

## Importacion masiva

- `POST transaccion/importar/iniciar`
- `POST transaccion/importar/procesar`
- `POST transaccion/importar/cancelar`
- `GET transaccion/importar/estatus`
- `GET transaccion/importar/log`

## Rutas API legacy sin prefijo `/api`

- `POST GenerarLigaPago`
- `POST GenerarLigaDomiciliacion`
- `POST GenerarSpei`
- `POST GenerarLigaLector`

## Tablas involucradas

- `transacciones`
- `respuestas`
- `transaccionesDom`
- `pagos_recibidos`
- `consultaspei`
- `pagospei`
- `cancelaspei`
- `clientes`
- `personas`
- `users`

## Flujo resumido

1. Front captura datos y llama ruta web o API legacy.
2. Backend valida entrada, usuario/rol y datos minimos.
3. Si aplica, identifica o crea cliente por datos normalizados.
4. Calcula folio/referencia.
5. Invoca Pagadetodo via Guzzle o mock.
6. Persiste `transacciones`.
7. Reportes/webhooks actualizan estado y respuestas posteriores.

## Estados operativos

La columna real de estado operativo en `transacciones` es `condicion`. El UI puede mostrarla como `Status`, pero el backend no debe consultar `transacciones.status` para este flujo.

Valores usados:

- `0`: Pendiente.
- `1`: Activo.
- `2`: Cancelado.
- `3`: Pagado.
- `4`: Vencido.
- `5`: Error.

Reglas especificas para `tipo=2` domiciliacion:

- Al generar la liga, inicia `Pendiente=0`.
- Al recibir respuesta aprobada con token, cambia a `Activo=1`.
- Al recibir respuesta aprobada sin token, cambia a `Error=5`.
- Si vence sin respuesta aprobada, el comando diario `transacciones:sincronizar-status` cambia a `Vencido=4`.
- `intentos` cuenta cargos recurrentes fallidos y se reinicia a `0` con cargo aprobado.
- `ProximoCargoBase` conserva la primera fecha de proximo cargo como ancla/auditoria.

Reglas de error/pago vigentes desde 2026-07-01:

- `tipo=1` y `tipo=2`: si la generacion Pagadetodo responde `code='error'` o no entrega `url`, la transaccion se guarda con `condicion=5`.
- `tipo=4`: si la generacion terminal responde `code='error'`, no entrega `codeQR` o no entrega `responseReference`, la transaccion se guarda con `condicion=5`.
- `tipo=1`: al recibir `Service/EntregarPagoLiga` con `response='approved'`, la transaccion cambia a `Pagado=3`.
- `tipo=4`: al recibir `Service/EntregarPagoLector` con `response='approved'`, la transaccion cambia a `Pagado=3`.
- El comando diario `transacciones:sincronizar-status`, programado a las 00:05 de Hermosillo, marca vencidas las transacciones `tipo=1`, `tipo=3` y `tipo=4` con `condicion=1` cuando `ExpirationDate` ya paso; para `tipo=2` conserva la regla de `condicion=0`.
- `revisarStatus()` queda reservado al reenvio de pagos SPEI legacy/shadow cada cinco minutos.
- Webhooks Pagadetodo y altas/ediciones manuales de respuestas sincronizan inmediatamente el estado mediante `TransaccionStatusSynchronizer`.

Optimizacion de indices pendiente y separada: no se agregaron migraciones. Antes de proponer indices productivos se deben capturar `SHOW INDEX` y planes de solo lectura:

```sql
SHOW INDEX FROM transacciones;
SHOW INDEX FROM respuestas;

EXPLAIN
SELECT t.id
FROM transacciones t
WHERE t.tipo IN (1, 3, 4)
  AND t.condicion = 1
  AND t.ExpirationDate < CURDATE();

EXPLAIN
SELECT t.id
FROM transacciones t
WHERE t.tipo = 2
  AND t.condicion IN (0, 5)
  AND EXISTS (
    SELECT 1
    FROM respuestas r
    WHERE r.idtransaccion = t.id
      AND r.status = 'approved'
      AND r.number_tkn IS NOT NULL
      AND r.number_tkn <> ''
  );
```

No ejecutar `ALTER TABLE` hasta revisar volumen, cardinalidad, plan y ventana de bloqueo MySQL.

Queries de regularizacion historica, para ejecucion controlada por operacion:

```sql
UPDATE transacciones t
SET t.condicion = 3
WHERE t.tipo = 1
  AND EXISTS (
    SELECT 1
    FROM respuestas r
    WHERE r.idtransaccion = t.id
      AND r.status = 'approved'
  );
```

```sql
UPDATE transacciones t
SET t.condicion = 3
WHERE t.tipo = 3
  AND EXISTS (
    SELECT 1
    FROM pagospei p
    WHERE p.idtransaccion = t.id
      AND p.codigo IN ('0', '00')
  );
```

## Integracion Pagadetodo

- Credenciales, IDs y endpoints estan externalizados en `config/services.php`.
- Valores reales deben vivir en `.env` de servidor.
- En validacion local usar `PAGADETODO_MOCK=true`; Pagadetodo real solo puede validarse desde servidor/IP autorizado por restriccion de IP address de origen.
- No cambiar payloads `User`, `Password`, `BusinessID`, `IntegrationID` o nombres legacy sin evidencia servidor/IP autorizado y sanitizada.

## Acceso por rol

- Admin: operacion completa.
- Cliente: acceso a registros propios por allowlist y ownership.
- En la tabla principal compartida `Transaccion.vue`, el rol se recibe desde `contenido.blade.php`; usuarios no admin no ven columnas administrativas `Forma de Pago`, `Usuario` ni `Productivo`.
- API externa: autenticacion legacy por payload.

## Exportacion actual

- `GET transaccion/exportar` descarga CSV (`transacciones.csv`) por streaming para evitar agotamiento de memoria.
- Si recibe `buscar`, `criterio` y `status`, aplica los mismos filtros del listado principal.
- `GET transaccion/exportarTransacciones` se mantiene para exportacion filtrada de reportes.
- Bitacoras de importacion masiva siguen en `xlsx` por volumen y uso diferente.

## Filtros de busqueda

En `Transaccion.vue`, los modulos de generacion de liga usan el mismo select:

- `Ref. Cliente`: `transacciones.ClientReference`.
- `Ref. Transacción`: `transacciones.Reference`.
- `Ref. Respuesta`: busqueda primaria en `transacciones.responseReference`; tambien consulta `respuestas.reference` por relacion `respuestas.idtransaccion = transacciones.id` para cubrir datos historicos o desnormalizados.
- `CLABE`: solo para `tipo=3`, filtra `transacciones.Clabe`.
- `Descripción`: `transacciones.Description`.
- `Cliente`: `clientes.razon_social`.

`Ref. Respuesta` no debe apuntar a `respuestas.reference` como campo principal en transacciones, pero si debe encontrar la transaccion cuando la respuesta relacionada conserva la referencia y el campo denormalizado de transacciones no coincide.

Filtro de fechas:

- `Desde` y `Hasta` filtran `transacciones.fecha`.
- Al entrar al modulo, el frontend inicializa `Desde` con la fecha de hace 30 dias y `Hasta` con la fecha actual.
- `Limpiar` borra `Texto a buscar`, `Desde` y `Hasta`; no cambia criterio, status ni tipo.
- El selector de cantidad inicia en `50` registros.
- El filtro `Status` de `Transaccion.vue` se muestra para los tipos `1`, `2`, `3` y `4`. `tipo=2` permite Pendiente/Activo/Cancelado/Vencido/Error; los demas tipos permiten Activo/Pagado/Vencido/Error.
- En la tabla compartida, `Vencido=4` usa el mismo fondo rojo de `Cancelado` con texto negro; es un cambio exclusivamente visual.
- Las acciones financieras directas de cancelar transaccion/domiciliacion y detener una importacion requieren confirmacion y abortan antes de Axios cuando el usuario cancela el dialogo.

## Deteccion de cliente duplicado en APIs

Aplica en `storeAPI`, `storeDomAPI` y `storeSpeiAPI`:

- Comparacion por `idusuario`, `Nombre`, `Email`, `Telefono`.
- `Nombre`: `trim` + minusculas.
- `Email`: `trim` + minusculas.
- `Telefono`: solo digitos y ultimos 10.
- Si coinciden los tres valores normalizados, reutiliza cliente.
- Si cambia algun valor, registra cliente nuevo.
- Si faltan los tres datos, la transaccion puede continuar sin `idcliente`.
- Si falla guardar `Persona/Cliente`, la transaccion principal puede continuar sin `idcliente`.
- En `storeSpeiAPI`, correo (`Email` o `email`) es obligatorio.

## Importacion masiva de Excel

Disponible para:

- `tipo=1`: Ligas.
- `tipo=2`: Domiciliacion.

No disponible para `tipo=3` SPEI.

Columnas requeridas:

- Siempre: `Cliente`, `Forma de pago`, `Descripcion`, `Monto`, `Fecha Expiracion`, `Referencia`.
- Domiciliacion: `Frecuencia`.

Reglas por renglon:

- `Cliente`: coincidencia exacta en `clientes.razon_social` o `personas.nombre`.
- `Forma de pago`: `41` Visa/Mastercard o `102` Amex.
- `Monto`: numerico, minimo `50.00`.
- `Fecha Expiracion`: valida y no menor al dia actual.
- `Frecuencia`: `1..5` o texto del catalogo.

## Riesgos

- `TransaccionController` es monolitico y de alto impacto.
- Folios por consultas `max()+1` pueden sufrir carreras.
- Contratos externos y callbacks son sensibles a nombres legacy.
- Cambios pueden afectar reportes, importaciones y webhooks aunque parezcan locales.

## Pruebas recomendadas

- Unit si se tocan helpers/guards.
- Feature SQLite con `PAGADETODO_MOCK=true` si cambia API, ownership, importacion o webhooks.
- `npm run production` si cambia componente Vue.
- Smoke admin/cliente:
  - listar transacciones;
  - generar liga;
  - importar archivo pequeno;
  - exportar CSV/reporte;
  - validar que cliente no vea registros ajenos.

## Pendientes y mejoras

- Adapter Pagadetodo separado.
- Tests de concurrencia para folios.
- Matriz de estados/tipos documentada con ejemplos reales.
- Evidencia sanitizada de pruebas Pagadetodo servidor sandbox/productivo.
- Ejecutar migraciones de `ProximoCargoBase`, `intentos` y `pagos_recibidos` en ambiente controlado antes de desplegar funcionalidades dependientes.

## Corte diagnostico 2026-06-07

- `php artisan route:list` registra 103 rutas; las rutas de transaccion, reportes, importacion, domiciliacion activa y pagos recibidos estan presentes.
- `ReporteTransacciones.vue` no existe en el filesystem actual; los reportes de ingreso se soportan por los componentes listados en esta ficha.
- Feature aislado WAMP/SQLite de `Phase32`, `Phase34` y `UX` paso con 52 tests y 170 assertions; el Feature completo fallo por credenciales MySQL locales, no por este modulo aislado.
- Addendum 2026-06-08: el propietario confirmo pruebas exitosas de Pagadetodo desde servidor en sandbox y productivo; no se puede reproducir desde local por restriccion de IP de origen.
- Pendiente de alto riesgo: concurrencia de folios, evidencia sanitizada de servidor y adapter Pagadetodo antes de cambios mayores.
