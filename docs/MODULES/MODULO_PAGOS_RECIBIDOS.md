# Modulo: Pagos Recibidos

Ultima actualizacion: 2026-06-04

## Proposito

Concentrar en una sola bitacora los pagos recibidos por los canales principales de la plataforma, con una etiqueta de canal y un status operativo ajustable sin modificar los registros fuente.

## Archivos clave

- `app/Http/Controllers/PagoRecibidoController.php`
- `app/PagoRecibido.php`
- `resources/assets/js/components/PagoRecibido.vue`
- `resources/views/contenido/contenido.blade.php`
- `resources/views/plantilla/sidebaradministrador.blade.php`
- `resources/views/plantilla/sidebarcliente.blade.php`

## Rutas web

- `GET pagos-recibidos`
- `PUT pagos-recibidos/status`

## Menu

- Target Vue: `menu==30`.
- Administrador: acceso despues de `Pago con Terminal`.
- Cliente: acceso antes de `Reportes`, porque ese sidebar no tiene seccion `Pago con Terminal`.

## Fuentes de datos

El listado no duplica pagos. Construye una vista unificada desde:

- `respuestas` con `status='approved'`.
- `pagospei` exitosos con `condicion=1` y mensaje/codigo de operacion exitosa.

La tabla `pagos_recibidos` guarda solo overrides:

- `source_type`: `respuesta` o `pagospei`.
- `source_id`: id del registro fuente.
- `status`: `activo` o `cancelado`.
- `idusuario`: usuario que aplico el ajuste.

Si no existe override, el status visible por defecto es `activo`.

## Campos visibles

- Folio.
- Fecha, mostrada como `dd-mm-yyyy` y hora debajo `hh:mm:ss`.
- Cliente.
- Referencia.
- Monto.
- Canal.
- Status.

## Etiquetas de canal

- `tipo=1` desde respuestas: `Liga de pago`.
- `tipo=2` desde respuestas: `Domiciliacion` en backend y `Domiciliación` en UI.
- `tipo=3` desde respuestas: `Caja`.
- `tipo=4` desde respuestas: `Terminal`.
- `pagospei`: `SPEI`.

Nota: la plataforma comparte `tipo=3` para pantallas de referencia SPEI/Pago en Caja. La separacion exacta `Caja` vs `SPEI` debe revisarse con negocio si se requiere mas precision historica.

## Acceso por rol

- Admin: puede listar y ajustar status de registros visibles.
- Cliente: solo registros propios, usando ownership del registro fuente.
- Otros roles: `403` por middleware existente.

## Riesgos

- La vista unificada combina fuentes con contratos distintos.
- El canal `Caja` no tiene campo tecnico independiente si comparte `tipo=3`.
- Requiere migracion `pagos_recibidos` antes de permitir cambio de status en servidor.

## Pruebas recomendadas

- Feature SQLite para listado y cambio de status.
- Smoke browser autenticado de filtros, paginacion y guardado de status.
- Validar que el ajuste de status no altere `respuestas` ni `pagospei`.

## Pendientes y mejoras

- Confirmar regla final para diferenciar `Caja` y `SPEI` cuando ambos nacen desde referencias tipo `3`.
- Agregar exportacion si negocio la requiere.
- Agregar filtros por canal y rango de fechas en una fase posterior.
