# Módulo: Transacciones (ligas/caja/terminal/SPEI)

## Propósito
Generar referencias/ligas y registrar estado operativo de cobros de distintos tipos (`tipo`).

## Archivos clave
- `app/Http/Controllers/TransaccionController.php`
- `app/Transaccion.php`
- `resources/assets/js/components/Transaccion.vue`
- `resources/assets/js/components/ReporteLigas.vue`

## Etiquetas funcionales en encabezados y reportes
- El componente `Transaccion.vue` define encabezados dinámicos por `tipo` para mantener consistencia operativa:
  - `tipo=1`: **Liga de Pago Única**.
  - `tipo=2`: **Liga de Pago Domiciliación/Recurrente**.
  - `tipo=3`: **Referencia SPEI**.
  - `tipo=4`: **Liga de Pago Terminal**.
- El componente `ReporteLigas.vue` soporta `tipo=4` con el título **Reporte Ingresos Pago con Terminal**.

## Flujo resumido
1. Front captura datos y llama endpoint (`/transaccion/registrar`, API equivalents).
2. Backend valida y calcula folio/referencia.
3. Invoca servicio externo vía Guzzle.
4. Persiste `transacciones` y, según flujo, posteriores registros en `respuestas`/SPEI.

## Tablas involucradas
- `transacciones` (principal)
- `respuestas` (resultado)
- `consultaspei`, `pagospei`, `cancelaspei` (SPEI)
- `clientes`, `users` (contexto)

## Riesgos
- Controlador monolítico, alto acoplamiento.
- Credenciales y endpoints hardcoded.
- Validación/auth API heterogénea.

## Exportacion actual del modulo

- `GET /transaccion/exportar` descarga el listado general como `CSV` (`transacciones.csv`) por streaming.
- Este cambio evita el agotamiento de memoria observado cuando el listado general intentaba materializar grandes volumenes de registros en un archivo Excel en memoria.
- `GET /transaccion/exportarTransacciones` se mantiene como exportacion filtrada de reportes.
- La bitacora de importacion masiva sigue descargandose en `xlsx`, porque su uso y volumen son distintos del listado general.

## Detección de cliente duplicado en APIs (`storeAPI`, `storeDomAPI`, `storeSpeiAPI`)
- En `TransaccionController`, la comparación de cliente duplicado en los métodos API de ligas, domiciliación y SPEI se realiza por `idusuario`, `Nombre`, `Email` y `Telefono`.
- Para comparar, los valores se normalizan en tiempo de ejecución:
  - `Nombre`: `trim` + minúsculas.
  - `Email`: `trim` + minúsculas.
  - `Telefono`: solo dígitos y últimos 10.
- Si coincide la comparación normalizada, se reutiliza el cliente existente.
- Si alguno de los tres campos no coincide, se registra un cliente nuevo.
- Si no vienen los tres campos (`Nombre`, `Email`, `Telefono`), no se intenta identificar/crear cliente y la transacción continúa sin `idcliente`.
- Si vienen los datos pero ocurre un error al guardar `Persona/Cliente`, la transacción principal continúa sin `idcliente` (no se bloquea la operación).
- En `storeSpeiAPI`, el correo sí es obligatorio (`Email` o `email`) para continuar con la transacción SPEI.
- Los datos almacenados en `clientes` (`razon_social`, `email_contacto`, `telefono_contacto`) se guardan con su formato de origen (con saneo mínimo de `trim` en email/teléfono); la normalización se usa únicamente para la búsqueda y no se persiste.

## Importación masiva de Excel (Ligas y Domiciliación)

### Alcance
- Disponible en **Generación de Ligas** para `tipo=1` (Ligas) y `tipo=2` (Domiciliación).
- No disponible para `tipo=3` (SPEI).

### Columnas requeridas
- Siempre: `Cliente`, `Forma de pago`, `Descripción`, `Monto`, `Fecha Expiración`, `Referencia`.
- Solo Domiciliación (`tipo=2`): `Frecuencia`.

### Reglas de validación por renglón
- `Cliente`: coincidencia exacta en `clientes.razon_social`; si no existe, coincidencia exacta en `personas.nombre`.
- `Forma de pago`: solo `41` (Visa/Mastercard) o `102` (Amex).
- `Monto`: numérico con o sin símbolo `$`, mínimo **50.00**.
- `Fecha Expiración`: válida y no menor al día actual.
- `Frecuencia` (`tipo=2`): acepta número (`1..5`) y texto (`Semanal`, `Mensual`, `Bimestral`, `Semestral`, `Anual`).

### Proceso operativo
1. Se carga archivo Excel y se valida encabezado obligatorio.
2. Se procesa secuencialmente una fila a la vez.
3. Cada fila válida invoca el flujo existente:
   - `tipo=1` -> `/transaccion/registrar`
   - `tipo=2` -> `/transaccion/registrarDom`
4. Se actualiza progreso y bitácora por fila.
5. Si se cancela, los pendientes quedan en bitácora como `Omitida por cancelación`.

### Resumen y bitácora
- Se muestra resumen de: total, generadas, errores por categoría y omitidas por cancelación.
- Se puede descargar un `xlsx` basado en el archivo original con una columna adicional `Resultado` (URL generada o motivo del error/omisión).

### Endpoints de importación
- `POST /transaccion/importar/iniciar`
- `POST /transaccion/importar/procesar`
- `POST /transaccion/importar/cancelar`
- `GET /transaccion/importar/estatus`
- `GET /transaccion/importar/log`
