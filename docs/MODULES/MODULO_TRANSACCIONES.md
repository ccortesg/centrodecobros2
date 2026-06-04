# Modulo: Transacciones, ligas, caja, terminal y SPEI

Ultima actualizacion: 2026-06-03

## Proposito

Generar referencias/ligas y registrar estado operativo de cobros por distintos tipos de transaccion.

## Archivos clave

- `app/Http/Controllers/TransaccionController.php`
- `app/Transaccion.php`
- `resources/assets/js/components/Transaccion.vue`
- `resources/assets/js/components/ReporteLigas.vue`
- `resources/assets/js/components/ReporteTransacciones.vue`
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

## Integracion Pagadetodo

- Credenciales, IDs y endpoints estan externalizados en `config/services.php`.
- Valores reales deben vivir en `.env` de servidor.
- En validacion local/sandbox sin credenciales oficiales usar `PAGADETODO_MOCK=true`.
- No cambiar payloads `User`, `Password`, `BusinessID`, `IntegrationID` o nombres legacy sin evidencia de sandbox.

## Acceso por rol

- Admin: operacion completa.
- Cliente: acceso a registros propios por allowlist y ownership.
- API externa: autenticacion legacy por payload.

## Exportacion actual

- `GET transaccion/exportar` descarga CSV (`transacciones.csv`) por streaming para evitar agotamiento de memoria.
- `GET transaccion/exportarTransacciones` se mantiene para exportacion filtrada de reportes.
- Bitacoras de importacion masiva siguen en `xlsx` por volumen y uso diferente.

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
- Sandbox oficial Pagadetodo con fixtures sanitizados.
