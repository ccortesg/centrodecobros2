# Modulo: Pagos Recibidos

Ultima actualizacion: 2026-06-18

## Proposito

Concentrar en una sola bitacora los pagos recibidos por los canales principales de la plataforma, con etiqueta de canal, monto normalizado y filtro por fecha de pago.

## Archivos clave

- `app/Http/Controllers/PagoRecibidoController.php`
- `app/PagoRecibido.php`
- `resources/assets/js/components/PagoRecibido.vue`
- `resources/views/contenido/contenido.blade.php`
- `resources/views/plantilla/sidebaradministrador.blade.php`
- `resources/views/plantilla/sidebarcliente.blade.php`

## Rutas web

- `GET pagos-recibidos`
- `GET pagos-recibidos/exportar`
- `PUT pagos-recibidos/status` existe por compatibilidad, pero la pantalla principal ya no muestra ni opera status.

## Menu

- Target Vue: `menu==30`.
- Administrador: acceso despues de `Pago con Terminal`.
- Cliente: acceso antes de `Reportes`, porque ese sidebar no tiene seccion `Pago con Terminal`.

## Fuentes de datos

El listado no duplica pagos. Construye una vista unificada desde:

- `respuestas` con `status='approved'`.
- `pagospei` exitosos con `condicion=1` y mensaje/codigo de operacion exitosa.
- `transaccionesDom` con `status='approved'`, etiquetados como `Cargo Recurrente`.

La vista puede retornar `source_type` como `respuesta`, `pagospei` o `transaccionDom`.

La tabla `pagos_recibidos` guarda solo overrides heredados:

- `source_type`: `respuesta` o `pagospei`.
- `source_id`: id del registro fuente.
- `status`: `activo` o `cancelado`.
- `idusuario`: usuario que aplico el ajuste.

Si no existe override, el status por defecto es `activo`; actualmente no se muestra en el listado principal.

## Campos visibles

- Folio, con selector de cantidad de registros integrado debajo del encabezado (`10`, `25`, `50`, `100`).
- Fecha, mostrada como `dd-mm-yyyy` y hora debajo `hh:mm:ss`.
- Cliente.
- Referencia.
- Monto.
- Canal.

## Filtros UI

- El primer renglon contiene criterio y `Texto a buscar`.
- El segundo renglon contiene `Desde`, `Hasta` y `Buscar`.
- Ambos renglones usan media pantalla en desktop/tablet y ancho completo en pantallas chicas.

## Exportacion

- El boton `Exportar` descarga `pagos_recibidos.csv`.
- El endpoint reutiliza la misma consulta unificada del listado y respeta filtros de texto, criterio, rango de fechas, status heredado y ownership.
- La exportacion incluye las tres fuentes actuales: `respuestas`, `pagospei` y `transaccionesDom`.

## Reglas de monto

El backend entrega el campo `monto` ya normalizado para presentacion:

- `respuestas.amount`: se usa directo, porque ya se guarda en formato con decimales.
- `pagospei.monto`: se divide entre 100.
- `transacciones.Amount`: se divide entre 100 cuando se usa como fallback.
- `transaccionesDom.Amount`: se divide entre 100.

La UI no debe volver a dividir `monto` entre 100.

## Etiquetas de canal

- `tipo=1` desde respuestas: `Liga de pago`.
- `tipo=2` desde respuestas: `Domiciliacion` en backend y `Domiciliación` en UI.
- `tipo=3` desde respuestas: `Caja`.
- `tipo=4` desde respuestas: `Terminal`.
- `pagospei`: `SPEI`.
- `transaccionesDom.status='approved'`: `Cargo Recurrente`.

Nota: la plataforma comparte `tipo=3` para pantallas de referencia SPEI/Pago en Caja. La separacion exacta `Caja` vs `SPEI` debe revisarse con negocio si se requiere mas precision historica.

## Acceso por rol

- Admin: puede listar registros visibles.
- Cliente: solo registros propios, usando ownership del registro fuente.
- Otros roles: `403` por middleware existente.

## Riesgos

- La vista unificada combina fuentes con contratos distintos.
- El canal `Caja` no tiene campo tecnico independiente si comparte `tipo=3`.
- `pagos_recibidos/status` permanece disponible por compatibilidad aunque la UI ya no expone actualizacion de status.

## Pruebas recomendadas

- Feature SQLite para listado, cargos recurrentes, montos por fuente y rango de fechas.
- Feature SQLite para exportacion filtrada y ownership.
- Smoke browser autenticado de filtros, paginacion y rango de fechas.
- Validar que el listado no altere `respuestas`, `pagospei` ni `transaccionesDom`.

## Pendientes y mejoras

- Confirmar regla final para diferenciar `Caja` y `SPEI` cuando ambos nacen desde referencias tipo `3`.
- Agregar filtros por canal si negocio lo requiere.

## Corte diagnostico 2026-06-07

- `GET pagos-recibidos`, `GET pagos-recibidos/exportar` y `PUT pagos-recibidos/status` cargan en el inventario vigente de 103 rutas.
- El endpoint de status sigue disponible por compatibilidad, aunque la pantalla principal no lo expone.
- La fuente unificada y ownership de admin/cliente estan cubiertos por el carril Feature aislado WAMP/SQLite verde.
- La brecha funcional principal sigue siendo de negocio: diferenciar con precision historica `Caja` vs `SPEI` cuando ambos comparten `tipo=3`.
