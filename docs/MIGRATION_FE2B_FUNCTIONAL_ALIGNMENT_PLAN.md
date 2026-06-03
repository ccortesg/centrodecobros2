# FE-2B Functional Alignment Plan

Ultima actualizacion: 2026-03-12  
Estado: `Discovery completado; fase definida y no iniciada`

## Inventario de funcionalidad viva confirmada

| Superficie | Invocacion real | Montaje / vista actual | Soporte backend actual | Estado clasificado |
| --- | --- | --- | --- | --- |
| Roles (`Role.vue` / `Rol.vue`) | Menu `Roles` | `resources/views/contenido/contenido.blade.php` con `<rol></rol>` cuando `menu==4` | `GET /rol` en `RolController@index` | `Funcionalidad viva con wiring roto` |
| `ReporteSpei.vue` | Menu `Ingresos SPEI` | `<reportespei :tipo="3"></reportespei>` cuando `menu==20` | `GET /pagospei/reportePagoSpei` y `GET /pagospei/exportar` en `PagoSpeiController` | `Funcionalidad viva con backend incompleto` |
| `ReporteCargosRecurrentes.vue` | Menu `Ingresos Cargos Recurrentes` | `<reportecargosrecurrentes></reportecargosrecurrentes>` cuando `menu==25` | `GET /transaccionDom/reporteTransaccionesDom` y `GET /transaccionDom/exportar` en `TransaccionDomController` | `Funcionalidad viva con backend incompleto` |
| `/url` | Flujo guest publico | `routes/web.php` define `GET/POST /url`; vista `resources/views/transaccion/url.blade.php` existe | `TransaccionController` no implementa `showURL` ni `openPublic` | `Funcionalidad viva lista para corregirse en FE-2B` |

## Role.vue

### Wiring confirmado

1. La funcionalidad viva de Roles se monta hoy por `menu==4` con `<rol></rol>`.
2. `resources/assets/js/app.js` registra `rol` apuntando a `Rol.vue`.
3. `Role.vue` sigue existiendo como variante divergente y consume `/role?page=...`.

### Backend esperado y backend real

| Tema | Estado |
| --- | --- |
| Ruta esperada por `Role.vue` | `GET /role?page=...` |
| Ruta soportada hoy | `GET /rol?page=...` |
| Controlador real | `RolController@index` |

### Causa exacta de la falla actual

Existe drift entre dos componentes que representan la misma funcionalidad viva:

1. `Rol.vue` es el componente hoy montado y cableado al backend real.
2. `Role.vue` quedo desalineado, no esta registrado en `app.js` y apunta a una ruta inexistente.

### Tipo de correccion requerida en FE-2B

1. Definir un componente canonico para Roles.
2. Alinear registro Vue, naming del tag y ruta backend sin retirar la funcionalidad.
3. Resolver la compatibilidad entre `/rol` y la expectativa legacy `/role` con una estrategia explicita de alias o convergencia.

## ReporteSpei.vue

### Wiring confirmado

1. La vista se monta con `<reportespei :tipo="3"></reportespei>` cuando `menu==20`.
2. El componente consume `vue-select` y obtiene clientes desde `/cliente/selectCliente`.
3. La consulta principal usa `GET /pagospei/reportePagoSpei`.

### Backend esperado y backend real

| Tema | Estado |
| --- | --- |
| Ruta esperada para listado | `GET /pagospei/reportePagoSpei` |
| Ruta esperada para exportacion | `GET /pagospei/exportarReporteSpei` |
| Ruta soportada hoy para exportacion | `GET /pagospei/exportar` |
| Controlador real | `PagoSpeiController@reportePagoSpei` y `PagoSpeiController@exportar` |

### Causa exacta de la falla actual

1. El reporte filtrado si tiene backend para listado.
2. La exportacion que espera el componente no existe.
3. El backend actual solo expone una exportacion generica y no el contrato filtrado que la pantalla ya asume.

### Tipo de correccion requerida en FE-2B

1. Crear o alinear el endpoint de exportacion filtrada que consume la pantalla.
2. Hacer coincidir ruta, controlador y parametros (`idcliente`, `fechaInicio`, `fechaFin`).
3. Verificar que la export class no siga devolviendo `PagoSpei::all()` cuando la UI pide filtros.

## ReporteCargosRecurrentes.vue

### Wiring confirmado

1. La vista se monta con `<reportecargosrecurrentes></reportecargosrecurrentes>` cuando `menu==25`.
2. El mismo menu aparece en carriles admin y cliente.
3. La consulta principal usa `GET /transaccionDom/reporteTransaccionesDom`.

### Backend esperado y backend real

| Tema | Estado |
| --- | --- |
| Ruta esperada para listado | `GET /transaccionDom/reporteTransaccionesDom` |
| Ruta esperada para exportacion | `GET /transaccionDom/exportarTransacciones` |
| Ruta soportada hoy para exportacion | `GET /transaccionDom/exportar` |
| Controlador real | `TransaccionDomController@reporteTransaccionesDom` y `TransaccionDomController@exportar` |

### Causa exacta de la falla actual

1. El reporte filtrado si tiene backend para listado.
2. La exportacion que espera el componente no existe.
3. El endpoint real disponible exporta sin el mismo contrato funcional que la pantalla presupone.

### Tipo de correccion requerida en FE-2B

1. Crear o alinear el endpoint de exportacion filtrada esperado por la pantalla.
2. Hacer coincidir ruta, controlador y parametros de filtro.
3. Validar que la export class no siga devolviendo `TransaccionDom::all()` cuando el reporte trabaja filtrado.

## /url

### Wiring confirmado

1. `routes/web.php` registra:
   - `GET /url` -> `showURL`
   - `POST /url` -> `openPublic`
2. `resources/views/transaccion/url.blade.php` existe y publica el formulario a `route('open')`.
3. Es una superficie guest publica, separada del shell autenticado.

### Backend esperado y backend real

| Tema | Estado |
| --- | --- |
| Vista esperada | `resources/views/transaccion/url.blade.php` |
| Metodos esperados | `TransaccionController@showURL` y `TransaccionController@openPublic` |
| Implementacion actual | Metodos ausentes |

### Causa exacta de la falla actual

La ruta y la vista quedaron declaradas, pero el controlador no implementa los metodos que completan el flujo.

### Tipo de correccion requerida en FE-2B

1. Implementar `showURL` y `openPublic` en `TransaccionController`.
2. Reusar la vista existente y el contrato actual del formulario.
3. Validar seguridad minima de la apertura de URL antes de habilitar el flujo.

## Orden recomendado de correccion en FE-2B

1. `/url`: es una superficie publica con route contract ya declarado y sin backend operativo.
2. `Role.vue` / `Rol.vue`: hay deuda de duplicacion y naming que conviene cerrar antes de tocar runtime Vue.
3. `ReporteSpei.vue`: requiere cerrar el contrato de exportacion filtrada.
4. `ReporteCargosRecurrentes.vue`: mismo patron de exportacion filtrada, despues de SPEI para reutilizar criterio tecnico.

## GO / NO-GO para ejecutar FE-2B

### GO

1. FE-2 implementacion del build ya esta estable o explicitamente congelada sin cambios pendientes de pipeline.
2. Los assets y el wiring Blade siguen intactos.
3. Existe criterio funcional claro para filtros y exportaciones de SPEI y cargos recurrentes.
4. Los componentes se tratan como funcionalidad viva; no como candidatos a retiro.

### NO-GO

1. Mezclar FE-2B con Vue `2.7`, Vue `3` o Vite.
2. Aprovechar FE-2B para reescribir componentes o UX.
3. Cambiar nombres de assets o `resources/views/principal.blade.php`.
4. Corregir solo frontend sin alinear a la vez la ruta y el controlador que la pantalla espera.
