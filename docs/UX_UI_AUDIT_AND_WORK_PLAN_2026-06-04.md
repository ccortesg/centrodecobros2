# Auditoria UX/UI y plan de trabajo

Fecha: 2026-06-04
Proyecto: Centro de Cobros
Ambiente observado: produccion Docker publicada en `https://cc.soportetech.com.mx`
Repositorio de trabajo: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

## Objetivo

Identificar errores de diseno web, diseno grafico, usabilidad y responsividad observados en produccion, contrastarlos contra codigo fuente y documentacion, y definir una ruta critica para corregirlos sin cambiar reglas de negocio ni contratos externos.

Nota de revalidacion 2026-06-07: este documento conserva evidencias UX/UI del 2026-06-04. Para el estado vigente de rutas, pruebas y porcentajes por modulo/rol, usar `docs/PROJECT_STATUS_DIAGNOSTIC_2026-06-07.md`; `php artisan route:list` registra ahora 100 rutas en el checkout actual.

## Evidencia revisada

Capturas:

- `C:\Users\carlo\Downloads\cc 1.png`: warnings de consola en Escritorio.
- `C:\Users\carlo\Downloads\cc 2.png`: filtro `Status` en Estados no filtra.
- `C:\Users\carlo\Downloads\cc 3.png`: paginacion desbordada en viewport responsive.
- `C:\Users\carlo\Downloads\cc 4.png`: tabla de Ciudades no se adapta a movil.
- `C:\Users\carlo\Downloads\cc 5.png`: tabla ancha con scroll dificil y paginacion desbordada.
- `C:\Users\carlo\Downloads\cc 6.png`: Pagos SPEI con fechas largas, sin filtros por `Status`/`Enviado`.
- `C:\Users\carlo\Downloads\cc 7.png`: modal con fecha ISO sin formato mexicano.
- Capturas adicionales 2026-06-04: modulo `Generar Liga Domiciliacion/Recurrente` con modal `AxiosError: Request failed with status code 500` al filtrar `Status`.
- Texto pegado 2026-06-04: `laravel.log` con `Unknown column 'transacciones.status' in 'where clause'` al llamar `/transaccion?page=1&buscar=&criterio=Reference&offset=10&tipo=2&status=2`.

Codigo/documentacion revisados:

- `resources/views/principal.blade.php`
- `app/Http/Middleware/TrustProxies.php`
- `resources/assets/js/app.js`
- `resources/assets/js/components/Estado.vue`
- `resources/assets/js/components/Ciudad.vue`
- `resources/assets/js/components/PagoSpei.vue`
- `resources/assets/js/components/Respuesta.vue`
- `resources/assets/js/components/*.vue`
- `app/Http/Controllers/EstadoController.php`
- `app/Http/Controllers/CiudadController.php`
- `app/Http/Controllers/PagoSpeiController.php`
- `app/Http/Controllers/RespuestaController.php`
- `app/Http/Controllers/ConsultaSpeiController.php`
- `app/Http/Controllers/CancelaSpeiController.php`
- `app/Http/Controllers/TransaccionDomController.php`
- `docs/FRONTEND_ANALYSIS.md`
- `docs/MODULES/*.md`

## Matriz baseline visual y QA inicial

La Etapa 0 queda establecida con las capturas productivas aportadas el 2026-06-04. No se tomaron nuevas capturas autenticadas desde Codex porque no se usaron credenciales productivas y el servidor local temporal no pudo abrir puerto con WAMP/PHP desde WSL; el bloqueo queda documentado en la seccion de ejecucion.

| Pantalla/modulo | Viewport/evidencia | Estado baseline | Warnings/errores observados | Riesgo UX/UI |
| --- | --- | --- | --- | --- |
| Escritorio `/main` | `cc 1` escritorio productivo | Shell carga, pero consola muestra warning critico | Mixed Content por `http://cc.soportetech.com.mx/logout` dentro de `https://cc.soportetech.com.mx/main` | Critico |
| Catalogo Estados | `cc 2` escritorio productivo | Tabla funcional, filtro `Status` visible | Seleccion `Desactivados` no filtra; se ven registros `Activo` | Urgente |
| Paginacion listados | `cc 3` responsive productivo | Paginacion renderiza demasiados links | Links exceden ancho disponible | Urgente |
| Catalogo Ciudades movil | `cc 4` movil productivo | Tabla conserva formato desktop | Columnas cortadas; sin patron card ni wrapper correcto | Urgente |
| Tablas anchas | `cc 5` responsive productivo | Tabla y paginacion exceden contenedor | Scroll horizontal dificil y paginacion fuera de rango visual | Urgente |
| Pagos SPEI | `cc 6` escritorio productivo | Listado operativo, pero denso | Fechas crudas, sin filtros por `Status`/`Enviado` en encabezado | Importante |
| Modal/detalle financiero | `cc 7` productivo | Modal abre, pero muestra datos crudos | Fecha ISO larga y poco legible | Importante |

Viewports objetivo para QA visual por cada fase posterior:

| Viewport | Uso esperado | Criterio de aceptacion UX/UI |
| --- | --- | --- |
| `1440px+` | Escritorio amplio | Tabla legible, toolbar alineada, paginacion compacta sin overflow |
| `1366px` | Laptop comun | Sin scroll horizontal global; solo scroll interno de tabla si aplica |
| `768px` | Tablet | Filtros con wrap, tabla shell visible o cards cuando corresponda |
| `390px` | Movil moderno | Sin contenido cortado; acciones tactiles; paginacion usable |
| `360px` | Movil estrecho | Sin overflow incoherente; fechas en dos lineas; tablas convertidas o contenidas |

## Escala de severidad

| Severidad | Criterio |
| --- | --- |
| Critico | Afecta seguridad, confianza operativa o impide una tarea principal. Debe corregirse antes de cambios visuales amplios. |
| Urgente | Error visible o funcional de alto impacto que degrada produccion, pero tiene workaround parcial. |
| Importante | Afecta eficiencia, claridad o consistencia en varios modulos. |
| Relevante | Mejora necesaria para profesionalizar experiencia y reducir friccion. |
| Mejora | Ajuste estetico, accesibilidad secundaria o refinamiento. |

## Escala de riesgo tecnico

| Riesgo | Criterio |
| --- | --- |
| Bajo | Cambio localizado en CSS/Vue sin cambiar contrato backend. |
| Medio | Cambia patron compartido o parametros Vue/backend con pruebas acotadas. |
| Alto | Toca muchos componentes, rutas, queries, permisos o datos financieros. Requiere despliegue por etapas. |

## Hallazgos catalogados por pantalla/modulo

| ID | Pantalla/modulo | Evidencia | Hallazgo | Severidad | Riesgo tecnico | Causa probable en codigo | Tarea recomendada |
| --- | --- | --- | --- | --- | --- | --- | --- |
| UX-01 | Shell autenticado / Escritorio | `cc 1` | Consola muestra Mixed Content: `https://.../main` genera formulario hacia `http://.../logout`. | Critico | Medio | `principal.blade.php` usa `route('logout')`; si `APP_URL` o proxy Docker no reportan HTTPS, Laravel genera URL insegura. `TrustProxies` existe, pero debe validarse compose/proxy/env. | Corregir `APP_URL=https://cc.soportetech.com.mx`, headers `X-Forwarded-Proto`, trusted proxies o `forceScheme` controlado por env. Validar consola limpia. |
| UX-02 | Catalogos / Estados | `cc 2` | Selector `Status=Desactivados` no filtra; se siguen mostrando registros `Activo`. | Urgente | Medio | `Estado.vue` tiene `status`, pero `listarEstado()` no lo envia; `EstadoController@index` no filtra `condicion`. | Enviar `status` desde Vue y filtrar `condicion` en backend con whitelist. |
| UX-03 | Catalogos / Ciudades | `cc 3`, `cc 4` | Mismo patron de `Status` no aplicado y paginacion/tabla se desbordan. | Urgente | Medio | `Ciudad.vue` tiene `status`, pero `listarCiudad()` no lo envia; `CiudadController@index` ignora status y en listado sin busqueda usa `paginate(10)` en vez de `$offset`. | Corregir status/offset backend y Vue; agregar prueba o smoke. |
| UX-04 | Todos los listados con paginacion | `cc 3`, `cc 5` | La paginacion muestra demasiadas paginas y excede el ancho disponible. | Urgente | Medio | `pagesNumber` esta duplicado y usa `offset` como radio de paginas; con `offset=10` renderiza hasta 21 links. | Crear helper/componente de paginacion compacto con ventana fija: primera, anterior, pagina actual +-2, siguiente, ultima. |
| UX-05 | Listados responsivos | `cc 4` | En movil las tablas siguen siendo tablas completas y no cambian a cards o filas escaneables. | Urgente | Alto | Todos los componentes usan `<table class="... table-responsive">`; Bootstrap espera wrapper `.table-responsive`, no clase directa en tabla. No existe patron card mobile. | Definir patron responsive: desktop tabla, movil cards para listados operativos. Migrar por prioridad de modulo. |
| UX-06 | Listados anchos financieros | `cc 5`, `cc 6` | Tablas de mas de 4 columnas se salen del contenedor; scroll horizontal delgado y dificil. | Urgente | Medio-Alto | Columnas con fechas/IDs/textos largos, tabla sin wrapper de scroll accesible y sin columnas prioritarias. | Crear `.cdc-table-shell` con scroll visible, sombra/indicador, sticky acciones y columnas prioritarias; despues convertir a cards en movil. |
| UX-07 | Pagos SPEI | `cc 6` | Fechas se muestran como `yyyy-mm-dd hh:mm:ss` o ISO largo; ocupan ancho excesivo. | Importante | Medio | `PagoSpei.vue` renderiza `pagospei.fecha` y `pagospei.fecha_peticion` crudos. No hay helper global de fecha. | Agregar helpers globales `$formatDateMx`, `$formatTimeMx`, `$formatDateTimeMx` en `app.js`, similar a `$formatCurrency`. |
| UX-08 | Respuesta / modal SPEI | `cc 7` | Modal muestra fecha ISO `2025-04-24T11:21:13.8760587-06:00`; no usa formato mexicano ni separa hora. | Importante | Medio | Modales asignan datos crudos (`fecha_peticion`, `fecha`, `date`, etc.) a inputs readonly. | En modales de solo lectura usar presentacion de detalle, no inputs; formatear fechas como `dd-mm-yyyy` y debajo `hh:mm:ss`. |
| UX-09 | Pagos SPEI / Respuestas / SPEI / Domiciliacion | `cc 6` | Columnas `Status` y `Enviado` no tienen filtros en encabezado. | Importante | Medio-Alto | Controllers solo aceptan busqueda textual por `criterio`; no existen parametros dedicados `status`, `enviada`, `condicion`. | Disenar filtros por columna con parametros explicitamente whitelisteados y mantener compatibilidad con busqueda actual. |
| UX-10 | Componentes Vue de listados | Codigo | Paginacion, tablas, filtros y modales estan duplicados en casi todos los `.vue`. | Critico | Alto | No existe componente compartido de `DataTable`, `Paginator`, `DateCell` o `StatusBadge`. | Primero introducir utilidades compartidas; despues migrar modulos por lotes para reducir regresiones. |
| UX-11 | Formularios de busqueda | `cc 3`, `cc 4` | Select + input + boton se apretan en movil y cortan texto. | Importante | Bajo-Medio | `input-group` con columnas fijas `col-md-3`, `col-lg-3`, etc. | Crear layout de filtros responsive: grid/flex wrap, controles full-width en movil, boton alineado. |
| UX-12 | Modales de detalle financiero | `cc 7`, codigo `Respuesta.vue`, `PagoSpei.vue` | Modales usan formularios con inputs readonly para informacion de consulta; lectura densa y poco profesional. | Relevante | Medio | Reutilizacion de modal CRUD para modo ver (`tipoAccion==3`). | Crear patron `detail-modal`: pares etiqueta/valor, secciones, copy buttons para referencias largas, fechas formateadas. |
| UX-13 | Lenguaje UI | `Respuesta.vue`, tablas financieras | Mezcla Espanol/Ingles: `Amount`, `NB Error`, `Date`, `Payment Type`, `Status`. | Relevante | Bajo | Labels heredados del proveedor expuestos directamente. | Definir diccionario UI en Espanol, conservando nombres tecnicos solo en tooltip/detalle. |
| UX-14 | Accesibilidad de acciones | Capturas varias | Botones icon-only sin tooltip visible; color comunica significado sin texto. | Relevante | Bajo | Botones con `<i class="icon-eye">`, `<i class="icon-pencil">`, `<i class="icon-trash">` sin `title`/`aria-label`. | Agregar `title`, `aria-label`, tooltips y tamanos tactiles minimos. |
| UX-15 | Badges de estado | Capturas varias | Badges pequenos, inconsistentes (`Si` sin acento, `Activo`, `Exitoso`, `Inválido`) y sin filtros uniformes. | Relevante | Bajo-Medio | Cada componente renderiza badges manualmente. | Crear helper/componente `StatusBadge` con catalogos por modulo. |
| UX-16 | Dashboard / Escritorio | `cc 1` | Graficas y contenedores se ven rigidos, con mucho espacio gris y version vieja `0.0.1`. | Mejora | Medio | Layout heredado de plantilla CoreUI; dashboard no tiene tarjetas KPI ni estado operacional claro. | Redisenar dashboard despues de estabilizar listados; agregar KPIs, tarjetas y responsive chart sizing. |
| UX-17 | Scroll global y panel DevTools/responsive | `cc 3`-`cc 5` | En viewports reducidos aparecen scrolls anidados y zonas grises que dificultan navegar. | Importante | Medio | `.app-body`, `.main`, `.card-body`, tabla y DevTools generan scrolls independientes. | Definir politica de overflow: pagina vertical unica, scroll horizontal solo dentro de tabla shell visible. |
| UX-18 | Domiciliacion / Generar Liga Domiciliacion/Recurrente | Capturas adicionales + `laravel.log` | Al cambiar filtro `Status`, el endpoint `/transaccion?...tipo=2&status=2` responde 500. | Critico | Medio | `TransaccionController@index` filtraba `transacciones.status`, pero el listado y la tabla real usan `transacciones.condicion`. | Cambiar filtro backend a `transacciones.condicion`, validar valores permitidos y cubrir con Feature SQLite. |

## Modulos afectados por patrones transversales

| Patron | Componentes afectados detectados |
| --- | --- |
| Tabla + paginacion duplicada | `CancelaSpei`, `Ciudad`, `Cliente`, `ClienteConsolidar`, `ClienteDepurar`, `ConsultaSpei`, `Estado`, `PagoSpei`, `Respuesta`, `Rol`, `Transaccion`, `TransaccionDom`, `User` |
| Fechas crudas en listado | `CancelaSpei`, `ConsultaSpei`, `PagoSpei`, `ReporteCargosRecurrentes`, `ReporteLigas`, `ReporteLigasDom`, `ReporteSpei`, `Respuesta`, `Transaccion`, `TransaccionDom` |
| Status/Enviado manual | `Estado`, `Ciudad`, `PagoSpei`, `Respuesta`, `ReporteLigasDom`, `Transaccion`, `TransaccionDom`, `CancelaSpei` |
| Modales densos/read-only | `Respuesta`, `PagoSpei`, `CancelaSpei`, `TransaccionDom`, `Transaccion`, `Cliente`, `User` |

## Ruta critica recomendada

La ruta critica debe resolver primero seguridad/consola, luego componentes transversales y despues migrar pantallas por prioridad. No conviene corregir cada pantalla de forma aislada sin antes definir utilidades comunes, porque la duplicacion actual haria que cada bug reaparezca en otros modulos.

### Etapa 0 - Baseline visual y matriz de QA

Objetivo: dejar una medicion reproducible antes de tocar UI.

Actividades:

1. Crear checklist de QA visual por viewport:
   - desktop ancho: `1440px+`;
   - laptop: `1366px`;
   - tablet: `768px`;
   - movil: `390px` y `360px`.
2. Capturar estado actual de:
   - `/main` dashboard;
   - Estados;
   - Ciudades;
   - Transacciones tipo 1;
   - Respuestas;
   - Pagos SPEI;
   - Cancelaciones SPEI;
   - Cargos recurrentes;
   - Usuarios;
   - Clientes.
3. Documentar consola inicial y network warnings.
4. Definir datos de prueba no productivos o usuario de prueba seguro.

Criterio de salida:

- Matriz de pantallas/viewport con evidencia.
- Lista de warnings actual.
- Sin cambios funcionales todavia.

### Etapa 1 - Seguridad visible y consola limpia

Objetivo: eliminar Mixed Content y warnings iniciales que erosionan confianza desde login.

Actividades:

1. Revisar `.env` productivo Docker:
   - `APP_URL=https://cc.soportetech.com.mx`;
   - configuracion de proxy/reverse proxy para `X-Forwarded-Proto=https`.
2. Confirmar `TrustProxies` en Laravel y headers recibidos por contenedor.
3. Si el proxy no puede ajustarse, evaluar `URL::forceScheme('https')` condicionado por `APP_ENV=production` o env dedicado.
4. Validar que `route('logout')` genere `https://.../logout`.
5. Confirmar consola limpia en `/main`.

Severidad: Critico.
Riesgo: Medio.
Validaciones:

- Browser real login/logout.
- DevTools console sin Mixed Content.
- `php artisan route:list`.
- No cambiar rutas ni auth.

### Etapa 2 - Fundacion UI compartida

Objetivo: reducir duplicacion antes de corregir pantallas una por una.

Actividades:

1. Agregar helpers globales en `resources/assets/js/app.js`:
   - `$formatDateMx(value)` -> `dd-mm-yyyy`;
   - `$formatTimeMx(value)` -> `hh:mm:ss`;
   - `$formatDateTimeMx(value)` -> objeto o string para dos lineas;
   - mantener `$formatCurrency`.
2. Crear utilidad de paginacion compacta:
   - no depender de `offset`;
   - mostrar ventana corta;
   - soporte responsive.
3. Crear clases CSS no invasivas:
   - `.cdc-list-toolbar`;
   - `.cdc-table-shell`;
   - `.cdc-responsive-table`;
   - `.cdc-pagination`;
   - `.cdc-date-stack`;
   - `.cdc-action-button`.
4. Definir catalogos de status:
   - activo/inactivo;
   - SPEI condicion `0/1/2`;
   - enviada `0/1`;
   - respuestas `approved/denied/error` y variantes reales.
5. Documentar convenciones en `docs/FRONTEND_ANALYSIS.md`.

Severidad: Critico como habilitador.
Riesgo: Alto si se aplica masivamente; Medio si se introduce sin migrar todas las pantallas.
Validaciones:

- `npm run production`.
- Smoke visual de una pantalla piloto.

### Etapa 3 - Fix piloto: Catalogos Estados/Ciudades

Objetivo: corregir el bug visible de status y validar el patron en pantallas pequenas.

Actividades:

1. `Estado.vue`:
   - enviar `status` en `listarEstado`;
   - no resetear filtros al activar/desactivar sin necesidad.
2. `EstadoController@index`:
   - usar `offsetPaginacion`;
   - aplicar filtro `condicion` solo si `status != 99`;
   - validar criterios permitidos.
3. `Ciudad.vue`:
   - enviar `status`;
   - usar paginacion compacta;
   - toolbar responsive.
4. `CiudadController@index`:
   - aplicar `$offset` tambien cuando no hay busqueda;
   - filtrar `ciudades.condicion`;
   - validar criterios.
5. Cambiar tabla a wrapper `.cdc-table-shell`.

Severidad: Urgente.
Riesgo: Medio.
Validaciones:

- Status Todos/Activos/Desactivados en Estados y Ciudades.
- Paginacion en 1366, 768, 390 y 360 px.
- `php artisan route:list`.
- Feature/Unit si se agregan pruebas de filtro.

### Etapa 4 - Listados financieros prioritarios

Objetivo: resolver las pantallas con mayor densidad, impacto operativo y quejas visuales.

Orden recomendado:

1. Pagos SPEI.
2. Consulta SPEI.
3. Cancelaciones SPEI.
4. Respuestas.
5. Transacciones.
6. Cargos recurrentes / Domiciliacion.

Actividades por pantalla:

1. Reemplazar fechas crudas por `cdc-date-stack`.
2. Agregar filtros de encabezado para `Status` y `Enviado` donde aplique.
3. Agregar parametros backend dedicados:
   - `condicion` o `status`;
   - `enviada`;
   - mantener busqueda textual actual.
4. Aplicar tabla shell con scroll visible y sticky de acciones.
5. Definir columnas prioritarias por viewport:
   - desktop: tabla completa;
   - tablet: tabla con columnas resumidas y detalle;
   - movil: cards.
6. Mantener exportaciones sin cambio salvo que se documente explicitamente.

Severidad: Urgente/Importante.
Riesgo: Medio-Alto.
Validaciones:

- Feature SQLite si cambia backend.
- `npm run production`.
- Browser real con filtros combinados.
- Exportar sigue funcionando.

### Etapa 5 - Modales y detalle profesional

Objetivo: convertir modales read-only densos en vistas de detalle claras.

Actividades:

1. Crear patron `detail-modal`:
   - header claro;
   - secciones;
   - label/value;
   - fechas en formato mexicano;
   - referencias largas con wrap/copy;
   - estado como badge.
2. Migrar:
   - `PagoSpei.vue`;
   - `Respuesta.vue`;
   - `CancelaSpei.vue`;
   - `TransaccionDom.vue`;
   - `Transaccion.vue`.
3. Separar modales de edicion real de modales de consulta.
4. Agregar `aria-label` y foco inicial/cierre consistente.

Severidad: Importante/Relevante.
Riesgo: Medio.
Validaciones:

- Abrir/cerrar modal con mouse y teclado.
- Fechas `dd-mm-yyyy` y hora debajo.
- No hay overflow horizontal en modal movil.

### Etapa 6 - Resto de listados administrativos

Objetivo: completar consistencia visual en pantallas de menor riesgo financiero.

Orden recomendado:

1. Clientes.
2. Consolidar clientes.
3. Depurar clientes.
4. Usuarios.
5. Roles.
6. Reportes.
7. Dashboard.

Actividades:

1. Aplicar toolbar responsive.
2. Paginacion compacta.
3. Tabla shell/cards donde aplique.
4. Tooltips/labels de acciones.
5. Diccionario de labels en Espanol.
6. Validar roles admin/cliente.

Severidad: Importante/Relevante.
Riesgo: Bajo-Medio por pantalla.

### Etapa 7 - Pulido visual y accesibilidad

Objetivo: elevar calidad grafica y usabilidad profesional.

Actividades:

1. Normalizar espaciados, tipografia, botones y badges.
2. Definir ancho maximo y densidad por tipo de pantalla.
3. Mejorar dashboard con tarjetas KPI y charts responsivos.
4. Actualizar version visible si el propietario lo aprueba.
5. Agregar tooltips, `aria-label`, foco visible y tamanos tactiles minimos.
6. Revisar contraste de badges y botones.

Severidad: Relevante/Mejora.
Riesgo: Bajo-Medio.

## Control de avances

Cada agente que ejecute una tarea UX/UI debe actualizar esta tabla o agregar un bloque equivalente al final del documento.

| ID tarea | Etapa | Pantalla/modulo | Estado | Responsable | Evidencia requerida | Validaciones | Fecha |
| --- | --- | --- | --- | --- | --- | --- | --- |
| UX-00 | 0 | Baseline visual y matriz QA | Implementado | Codex | `cc 1` a `cc 7`; matriz documentada | revision de capturas y codigo relacionado | 2026-06-04 |
| UX-01 | 1 | Shell/logout HTTPS | Implementado | Codex | Feature confirma `https://cc.soportetech.com.mx/logout` con `X-Forwarded-Proto=https`; browser real local bloqueado por runner | `php -l`; `php artisan route:list`; `ProxyAndCatalogUxFeatureTest`; Feature completa | 2026-06-04 |
| UX-02 | 3 | Estados status | Validado | Codex | `Estado.vue` envia `status`; `EstadoController@index` filtra `condicion` | `ProxyAndCatalogUxFeatureTest`; Feature completa; build production | 2026-06-04 |
| UX-03 | 3 | Ciudades status/offset | Validado | Codex | `Ciudad.vue` envia `status`; `CiudadController@index` usa `offset` y filtra `ciudades.condicion` | `ProxyAndCatalogUxFeatureTest`; Feature completa; build production | 2026-06-04 |
| UX-04 | 2 | Paginacion compacta | Implementado | Codex | `$paginationPages()` y piloto en Estados/Ciudades | `npm run production`; Feature completa | 2026-06-04 |
| UX-05 | 2-4 | Tabla shell responsive | En progreso | Codex | `.cdc-table-shell` aplicado a Estados/Ciudades; falta migrar listados financieros y cards movil | `npm run production`; browser real pendiente por entorno | 2026-06-04 |
| UX-06 | 4 | Pagos SPEI fechas/filtros | Validado | Codex | `PagoSpei.vue` usa filtros `condicion`/`enviada`, fechas `cdc-date-stack` y tabla shell | `FinancialFiltersFeatureTest`; Feature completa; `npm run production` | 2026-06-04 |
| UX-07 | 5 | Modales detalle | Pendiente | Agente | captura modal responsive | browser teclado/movil | - |
| UX-18 | 4 | Domiciliacion status 500 | Validado | Codex | `TransaccionController@index` filtra `transacciones.condicion`; prueba cubre `tipo=2&status=2` | `FinancialFiltersFeatureTest`; Feature completa; `route:list`; build production | 2026-06-04 |
| UX-19 | 4 | Listados financieros tabla/fechas/filtros | Implementado | Codex | `Transaccion`, `TransaccionDom`, `Respuesta`, `ConsultaSpei`, `CancelaSpei`, `PagoSpei` migrados a tabla shell/paginacion/fechas donde aplica | Feature completa; Unit; `npm run production`; browser local bloqueado por puerto | 2026-06-04 |
| UX-20 | 1 | Sesion expirada por inactividad | Implementado | Codex | Interceptor Axios global muestra modal `Tu sesión caducó por inactividad`, backdrop negro y redireccion a `/login` | `npm run production`; browser real pendiente por falta de runner autenticado | 2026-06-04 |
| UX-21 | 7 | Iconografia UI | Implementado | Codex | Sidebars y botones Vue migrados de `icon-*` a FontAwesome donde se tocaron componentes | `npm run production`; revision `rg class=\"icon-` | 2026-06-04 |
| UX-22 | 4 | Domiciliación Activa | Implementado | Codex | Nuevo submenu y componente `DomiciliacionActiva.vue`; endpoint `/domiciliacion-activa`; acciones reutilizan cancelar/cargo manual existentes | `DomiciliacionAndPaymentsFeatureTest`; `route:list`; `npm run production` | 2026-06-04 |
| UX-23 | 4 | Estados de domiciliación e intentos | Validado | Codex | Domiciliacion nueva inicia `Pendiente`; aprobada sin token pasa a `Error`; `intentos` cuenta fallos y se reinicia con cargo aprobado | `DomiciliacionAndPaymentsFeatureTest`; `php -l`; Unit | 2026-06-04 |
| UX-24 | 4 | Pagos Recibidos | Implementado | Codex | Nuevo modulo consolidado con fuentes `respuestas.approved` y `pagospei` exitosos; status ajustable por override | `DomiciliacionAndPaymentsFeatureTest`; `route:list`; `npm run production` | 2026-06-04 |

Estados permitidos:

- `Pendiente`
- `En progreso`
- `Implementado`
- `Validado`
- `Bloqueado`
- `Descartado`

## Criterios de aceptacion global

1. Consola limpia al entrar a `/main`.
2. Ningun listado desborda el ancho de viewport en `360px`, `390px`, `768px`, `1366px`.
3. Paginacion compacta visible y usable sin scroll horizontal.
4. Fechas visibles como `dd-mm-yyyy` y hora `hh:mm:ss` debajo en listados/modales donde el ancho sea sensible.
5. `Status` y `Enviado` filtrables en listados que los muestren como columnas.
6. Modales de consulta legibles sin inputs readonly innecesarios.
7. Acciones con tooltip/aria-label y area tactil suficiente.
8. Build productivo verde.
9. Sin cambios no solicitados en reglas de negocio, rutas publicas o contratos Pagadetodo.

## Ejecucion 2026-06-04 - Etapas 0 a 3

Cambios aplicados:

- `app/Http/Middleware/TrustProxies.php`: se configuro `protected $proxies = '*'` para confiar en headers `X-Forwarded-*` del reverse proxy Docker y evitar que `route('logout')` genere `http://...` cuando la peticion publica llega por HTTPS.
- `resources/assets/js/app.js`: se agregaron helpers globales de fecha/hora mexicana y paginacion compacta; se importo `resources/assets/js/styles/ux-ui.css`.
- `resources/assets/js/styles/ux-ui.css`: se agregaron clases base no invasivas para toolbar, tabla shell, paginacion, fecha en dos lineas y botones de accion.
- `resources/assets/js/components/Estado.vue`: se envio `status`, se conservo filtro/pagina al refrescar acciones, se aplico tabla shell, paginacion compacta y labels de accesibilidad.
- `resources/assets/js/components/Ciudad.vue`: se envio `status`, se conservo filtro/pagina al refrescar acciones, se aplico tabla shell, paginacion compacta y labels de accesibilidad.
- `app/Http/Controllers/EstadoController.php`: se agrego filtro whitelisteado por `condicion`, `offsetPaginacion()` y validacion de criterio.
- `app/Http/Controllers/CiudadController.php`: se agrego filtro whitelisteado por `ciudades.condicion`, `offsetPaginacion()` tambien sin busqueda, y validacion de criterio.
- `tests/Feature/UX/ProxyAndCatalogUxFeatureTest.php`: se agregaron pruebas de proxy HTTPS/logout, filtros status y criterios invalidos.
- `docs/FRONTEND_ANALYSIS.md`: se documentaron las convenciones UX/UI compartidas.

Causa confirmada de Mixed Content:

- `principal.blade.php` usa correctamente `route('logout')` y no fue modificado.
- La causa era de contexto de request detras de proxy: Laravel no tenia proxies confiables y por eso no tomaba `X-Forwarded-Proto=https` para generar URL segura.
- La prueba `test_logout_route_uses_https_when_reverse_proxy_reports_https` confirma que, con `X-Forwarded-Proto=https` y `X-Forwarded-Host=cc.soportetech.com.mx`, el formulario logout renderiza `https://cc.soportetech.com.mx/logout` y no `http://.../logout`.

Validaciones ejecutadas:

| Validacion | Resultado |
| --- | --- |
| `php -l app/Http/Middleware/TrustProxies.php app/Http/Controllers/EstadoController.php app/Http/Controllers/CiudadController.php tests/Feature/UX/ProxyAndCatalogUxFeatureTest.php` | OK, sin errores de sintaxis |
| `php artisan route:list` | OK, 97 rutas; sin cambios en rutas publicas |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Feature\UX\ProxyAndCatalogUxFeatureTest.php` | OK, 4 tests, 14 assertions |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit --testsuite Unit` | OK, 13 tests, 72 assertions |
| `scripts\local\prepare_phase33_browser_sqlite.php ...\storage\phase34_validation.sqlite` + `vendor\bin\phpunit --testsuite Feature` | OK, 58 tests, 248 assertions |
| `npm run production` | OK, Vite build y bridge `public/js/app.js` generados; assets compilados no se versionan |

Validacion browser real:

- No se usaron credenciales productivas.
- Se intento levantar servidor local con `storage/phase34_browser.sqlite`, `APP_ENV=testing`, `PAGADETODO_MOCK=true`, `DB_CONNECTION=sqlite` y PHP 8.3 de WAMP en `127.0.0.1:8134`.
- El proceso de WAMP/PHP quedo colgado antes de exponer puerto; `curl http://127.0.0.1:8134/login` devolvio `connection refused`.
- El PHP CLI Linux disponible no puede sustituirlo porque no tiene `pdo_sqlite` (`PDO`, `pdo_mysql` solamente).
- Por lo anterior, login/logout y consola limpia en browser real quedan pendientes de repetir en servidor productivo/sandbox con sesion autorizada o en una terminal Windows donde `artisan serve` con WAMP exponga puerto correctamente.

## Ejecucion 2026-06-04 - Hotfix status y Etapa 4 financiera

Cambios aplicados:

- `app/Http/Controllers/TransaccionController.php`: el filtro recibido como `status` en `/transaccion` ahora valida valores `0/1/2/3/4/99` y aplica `where('transacciones.condicion', ...)`. Esto corrige el 500 productivo por columna inexistente `transacciones.status`.
- `app/Http/Controllers/PagoSpeiController.php`: se agregaron filtros whitelisteados `condicion` y `enviada`.
- `app/Http/Controllers/CancelaSpeiController.php`: se agrego filtro whitelisteado `enviada`.
- `app/Http/Controllers/RespuestaController.php`: se agrego filtro whitelisteado `status` sobre la columna real `respuestas.status`.
- `app/Http/Controllers/TransaccionDomController.php`: se agrego filtro whitelisteado `status` sobre la columna real `transaccionesDom.status`.
- `resources/assets/js/components/Transaccion.vue`: se aplico `.cdc-table-shell`, `.cdc-pagination`, `.cdc-date-stack`, `.cdc-action-button`, fechas MX y paginacion compacta.
- `resources/assets/js/components/PagoSpei.vue`: se aplicaron tabla shell, fechas MX, filtros `Status`/`Enviado`, botones accesibles y paginacion compacta.
- `resources/assets/js/components/ConsultaSpei.vue`: se aplicaron tabla shell, fechas MX, botones accesibles y paginacion compacta.
- `resources/assets/js/components/CancelaSpei.vue`: se aplicaron tabla shell, fechas MX, filtro `Enviada`, monto formateado, badges y paginacion compacta.
- `resources/assets/js/components/Respuesta.vue`: se aplicaron tabla shell, fechas MX, filtro `Status`, botones accesibles y paginacion compacta.
- `resources/assets/js/components/TransaccionDom.vue`: se aplicaron tabla shell, fechas MX, filtro `Status`, monto formateado, botones accesibles y paginacion compacta.
- `resources/assets/js/styles/ux-ui.css`: se ajusto la tabla shell para permitir ancho `max-content` dentro del scroll horizontal controlado.
- `tests/Feature/UX/FinancialFiltersFeatureTest.php`: se agrego cobertura para el bug productivo y filtros financieros.
- `docs/FRONTEND_ANALYSIS.md`: se documento la convencion `status` vs `condicion` vs `enviada`.

Causa confirmada del 500 en Domiciliacion:

- El UI de `Transaccion.vue` muestra `Status`, pero el estado operativo de transacciones se almacena y renderiza desde `transacciones.condicion`.
- `TransaccionController@index` usaba `transacciones.status`, columna ausente en produccion.
- La correccion mantiene el parametro frontend `status` por compatibilidad, pero lo traduce a la columna real `transacciones.condicion`.

Validaciones ejecutadas:

| Validacion | Resultado |
| --- | --- |
| `php -l` en controllers modificados y `tests/Feature/UX/FinancialFiltersFeatureTest.php` | OK, sin errores de sintaxis |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Feature\UX\FinancialFiltersFeatureTest.php` | OK, 7 tests, 28 assertions |
| `php artisan route:list` | OK, 97 rutas; sin cambios en rutas publicas |
| `scripts\local\prepare_phase33_browser_sqlite.php ...\storage\phase34_validation.sqlite` + `vendor\bin\phpunit --testsuite Feature` | OK, 65 tests, 276 assertions |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit --testsuite Unit` | OK, 13 tests, 72 assertions |
| `npm run production` | OK, Vite build y bridge `public/js/app.js`; assets compilados no se versionan |
| Browser local `127.0.0.1:8134` con WAMP/PHP 8.3 y SQLite | Bloqueado por entorno: el proceso no expuso puerto; `curl` devolvio `connection refused` |

Pendientes reales:

- Repetir validacion browser autenticada en productivo/sandbox o en terminal Windows donde WAMP/PHP exponga el puerto local.
- Etapa 5: migrar modales financieros de solo lectura a patron `detail-modal`.
- Completar cards moviles reales para listados anchos; esta fase deja scroll horizontal controlado, no conversion completa a cards.

## Ejecucion 2026-06-04 - Domiciliacion activa, pagos recibidos y sesion expirada

Cambios aplicados:

- `resources/assets/js/app.js`: interceptor Axios global para sesion expirada. Detecta 401/419 o redireccion AJAX a `/login`, muestra modal `Tu sesión caducó por inactividad`, usa backdrop negro y al confirmar limpia overlay y redirige a `/login`.
- `resources/views/plantilla/sidebaradministrador.blade.php` y `resources/views/plantilla/sidebarcliente.blade.php`: se cambio `Domiciliacion` a `Domiciliación`, se agrego `Domiciliación Activa` y `Pagos Recibidos`, y se migro iconografia visible a FontAwesome.
- `resources/views/contenido/contenido.blade.php`: se agregaron targets `menu==29` (`domiciliacionactiva`) y `menu==30` (`pagorecibido`) sin tocar `principal.blade.php`.
- `app/Http/Controllers/TransaccionController.php`: nuevo endpoint `GET /domiciliacion-activa`; status `Error=5`; nuevas ligas domiciliadas se guardan como `Pendiente=0`; sincronizacion de domiciliaciones vencidas, aprobadas con token y aprobadas sin token desde `revisarStatus`.
- `app/Http/Controllers/RespuestaController.php`: el webhook aprobado de una domiciliacion activa la transaccion solo si trae token; si el token viene vacio/null marca la transaccion como `Error`.
- `app/Http/Controllers/TransaccionDomController.php`: el cron conserva `ProximoCargoBase` cuando falta, calcula el siguiente cargo desde la fecha programada actual, sincroniza `intentos` con cargos fallidos `code != 00` y reinicia a cero si el cargo es aprobado.
- `database/migrations/2026_06_04_120000_add_domiciliacion_control_fields_to_transacciones_table.php`: agrega `ProximoCargoBase` e `intentos` a `transacciones`. No se ejecuto migracion en esta tarea.
- `app/Http/Controllers/PagoRecibidoController.php`, `app/PagoRecibido.php`, `database/migrations/2026_06_04_120100_create_pagos_recibidos_table.php`: modulo `Pagos Recibidos` como vista unificada con tabla de overrides de status.
- `resources/assets/js/components/DomiciliacionActiva.vue`: listado con filtros, total de registros, paginacion primera/ultima, fechas MX y acciones para cancelar/cargo manual reutilizando rutas existentes.
- `resources/assets/js/components/PagoRecibido.vue`: listado consolidado, status `Activo/Cancelado` ajustable y total de registros.
- `resources/assets/js/components/Transaccion.vue`: total inferior, paginacion con primera/ultima pagina, `Status` compacto, columna `Descripcion` controlada, sticky acciones y nuevo badge `Error`.
- `resources/assets/js/styles/ux-ui.css`: estilos para `cdc-table-footer`, total, columna descripcion, status compacto, sticky actions y overlay de sesion expirada.
- `tests/Feature/UX/DomiciliacionAndPaymentsFeatureTest.php`: cobertura para domiciliacion activa, pagos recibidos, status vencido/error/activo e intentos.

Diagnostico `ejecutarCron`:

- Consulta `transacciones` tipo `2`, `condicion=1`, `productivo=1`, con `respuestas.status='approved'`, `users.recurrente=1` y `ProximoCargo` igual al dia actual.
- Usa datos de `transacciones` y token/expiracion desde la respuesta aprobada para llamar `PagarDomiciliacionIndi`.
- Guarda cada intento en `transaccionesDom`.
- Si el cargo es aprobado (`code=00` y `status=approved`), el siguiente `ProximoCargo` se calcula desde la fecha programada actual, no desde la fecha real de ejecucion; `intentos` queda en `0`.
- Si falla, `ProximoCargo` pasa al dia siguiente e `intentos` se sincroniza con los `transaccionesDom` fallidos de esa domiciliacion.
- `ProximoCargoBase` queda como ancla/auditoria de la primera fecha de proximo cargo, sin reemplazar la regla correcta de avanzar desde la fecha programada vigente.

Validaciones ejecutadas:

| Validacion | Resultado |
| --- | --- |
| `php -l` en controllers/modelos/tests modificados | OK, sin errores de sintaxis |
| `php artisan route:list --path=domiciliacion-activa`, `--path=pagos-recibidos`, `--path=transaccion` | OK, rutas nuevas registradas |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Feature\UX\DomiciliacionAndPaymentsFeatureTest.php` | OK, 7 tests, 21 assertions |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Feature\UX\FinancialFiltersFeatureTest.php` | OK, 7 tests, 28 assertions |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Feature\UX` | OK, 18 tests, 63 assertions |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Feature\Phase32 tests\Feature\Phase34 tests\Feature\Smoke\ApiValidationRegressionTest.php tests\Feature\Smoke\PublicRoutesSmokeTest.php` | OK, 36 tests, 107 assertions |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Unit` | OK, 13 tests, 72 assertions |
| `npm run production -- --no-progress` | OK, build productivo verde; assets compilados no estan versionados |
| `npm audit --omit=dev` | OK, 0 vulnerabilidades |
| `C:\wamp64\bin\php\php8.3.0\php.exe vendor\bin\phpunit tests\Feature` | Bloqueado parcialmente por entorno: 13 smoke tests intentan MySQL local `centrodecobros` con `centro_user` y reciben `Access denied`; las suites aisladas relevantes pasaron |

Pendientes reales:

- Ejecutar migraciones nuevas en ambiente controlado antes de usar `ProximoCargoBase`, `intentos` y overrides de `pagos_recibidos` en servidor.
- QA browser autenticado en sandbox/productivo: sesion expirada, menu `Domiciliación Activa`, menu `Pagos Recibidos`, filtros, carga manual y cancelacion.
- Convertir listados anchos a cards moviles reales; esta fase mejora scroll/tabla, no implementa cards.
- Revisar con negocio si el canal `Caja` debe diferenciarse tecnicamente de `SPEI`, porque ambos comparten `tipo=3` en la pantalla actual; `Pagos Recibidos` etiqueta `pagospei` como `SPEI` y respuestas tipo 3 como `Caja`.

## Prompt recomendado para ejecutar la siguiente etapa

```text
Trabaja sobre:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

No crees una nueva carpeta. Ejecuta QA browser autenticado y la Etapa 5 del plan UX/UI documentado en `docs/UX_UI_AUDIT_AND_WORK_PLAN_2026-06-04.md`.

Objetivo:
- validar en browser real, con usuario autorizado, que no hay consola critica en `/main`, que el modal de sesion expirada funciona y que los nuevos menus `Domiciliación Activa` y `Pagos Recibidos` renderizan correctamente;
- convertir los modales financieros de solo lectura en vistas de detalle profesionales;
- empezar por `PagoSpei.vue`, `Respuesta.vue`, `CancelaSpei.vue`, `TransaccionDom.vue`, `Transaccion.vue`, `DomiciliacionActiva.vue` y `PagoRecibido.vue`;
- separar visualmente secciones, etiquetas/valores, referencias largas, status badges y fechas `dd-mm-yyyy` con hora `hh:mm:ss`;
- revisar responsivo 1366, 768, 390 y 360 px para listados anchos;
- no cambiar reglas de negocio, rutas publicas, exports ni contratos Pagadetodo;
- ejecutar `npm run production`, PHPUnit relevante si cambia backend, y browser real;
- actualizar el control de avances del documento UX/UI con evidencia y validaciones ejecutadas.

Restricciones:
1. No ejecutar migraciones.
2. No tocar credenciales productivas.
3. No modificar scheduler.
4. No tocar `principal.blade.php` salvo necesidad tecnica justificada y verificada.
5. No versionar assets compilados.
6. No cambiar contratos Pagadetodo ni formato de exportaciones salvo que se documente y valide explicitamente.
```
