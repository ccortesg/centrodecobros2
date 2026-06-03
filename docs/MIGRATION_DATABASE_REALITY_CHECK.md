# Reality Check de Base de Datos

Fecha de corte: 2026-03-11

## Conclusión principal

Para fases futuras, la referencia operativa de base de datos debe ser:

1. `database/centrodecobros.sql`,
2. el uso real del esquema dentro del código,
3. y después las migrations históricas solo como artefacto legado parcial.

No es seguro asumir que `database/migrations` representa producción.

## Evidencia revisada

- Dump `database/centrodecobros.sql`
- Carpeta `database/migrations`
- Modelos y controladores que interactúan con tablas financieras y de identidad

## Estado del dump real

| Atributo | Observación |
| --- | --- |
| Tipo de dump | Solo esquema, sin `INSERT INTO` |
| Fecha del dump | 2026-03-06 |
| Motor declarado | MySQL `8.0.42` |
| PHP del generador | `7.4.3` |
| Triggers/procedimientos/vistas/eventos | No detectados en el dump |

## Cobertura de schema: dump vs migrations

### Tablas del dump con cobertura clara en migrations

- `password_resets`
- `roles`
- `personas`
- `users`
- `notifications`

### Tablas del dump sin migration equivalente en el repositorio

- `archivos`
- `cancelacionesDom`
- `cancelacionesLector`
- `cancelaspei`
- `ciudades`
- `clientes`
- `codigos`
- `consultaspei`
- `estados`
- `pagospei`
- `respuestas`
- `tmp_personas_merge`
- `transacciones`
- `transaccionesDom`

### Migrations históricas sin reflejo como núcleo operativo actual

- `categorias`
- `articulos`
- `proveedores`
- `ingresos`
- `detalle_ingresos`
- `ventas`
- `detalle_ventas`

Estas tablas corresponden a un dominio heredado que no aparece como núcleo funcional de cobros, rutas activas o flujos financieros actuales.

## Discrepancias críticas detectadas

### 1. `users` real no coincide con su migration histórica

El esquema real incluye columnas operativas no presentes en la migration legacy, entre ellas:

- `token`
- `IntegrationID`
- `BusinessID`
- `productivo`
- `notificaPago`
- `ligaPago`
- `recurrente`
- `ligaRecurrente`

Implicación:

- cualquier recreación de BD con migrations produciría un `users` insuficiente para la aplicación real.

### 2. Tablas financieras no están reconstruibles con migrations

Las tablas que sostienen ligas, domiciliación, SPEI, respuestas y cancelaciones no existen como migrations reales.

Implicación:

- una estrategia basada en `php artisan migrate` no recrea el sistema.

### 3. `archivos.idpersona` tiene conflicto entre FK y uso operativo

En el dump:

- la FK de `archivos.idpersona` apunta a `transacciones.id`.

En el código:

- se usa como si apuntara a `personas.id` / `clientes.id`.
- módulos de consolidación y depuración de clientes actualizan o validan `archivos.idpersona` bajo esa semántica.

Implicación:

- existe divergencia semántica de alto riesgo;
- no debe corregirse durante una fase de upgrade sin antes estudiar data real.

### 4. `tmp_personas_merge` existe y sí forma parte de la operación

El dump contiene `tmp_personas_merge`, y `ClienteController@consolidarCombinar` la utiliza cuando está disponible.

Implicación:

- esta tabla no puede tratarse como accesorio inexistente.

### 5. La configuración de queue espera tablas ausentes

`config/queue.php` declara conexión `database` y tabla `failed_jobs`, pero estas tablas no aparecen en el dump.

Implicación:

- hoy el sistema opera en `sync`; cualquier cambio futuro de queue requerirá trabajo explícito de infraestructura y schema.

## Claves foráneas observadas en el dump

### Claves coherentes con el uso del sistema

- `ciudades.idestado -> estados.id`
- `clientes.id -> personas.id`
- `users.id -> personas.id`
- `users.idrol -> roles.id`

### Relaciones críticas sin FK explícita

- `transacciones.idcliente`
- `transaccionesDom.idcliente`
- `respuestas.idtransaccion`
- relaciones SPEI hacia `transacciones`

Implicación:

- el sistema depende en gran medida de integridad lógica impuesta por código y no por el motor.

## Referencia operativa recomendada para fases futuras

| Capa | Trato recomendado |
| --- | --- |
| Dump SQL | Referencia primaria de schema |
| Código real | Referencia primaria de semántica de relaciones |
| Migrations históricas | Referencia secundaria para legado, no para recrear producción |

## Riesgo operativo por tipo de discrepancia

| Discrepancia | Riesgo | Tratamiento recomendado |
| --- | --- | --- |
| Tabla operativa sin migration | Crítico | No usar `migrate` como baseline; importar dump aislado |
| Columna real ausente en migration | Crítico | Tomar dump como contrato de trabajo |
| FK contradictoria con el código | Crítico | Analizar data real en Fase 1 antes de cualquier cambio estructural |
| Tabla usada por código pero omitida en docs previas | Alto | Consolidar reality check y mantenerlo actualizado |
| Dump sin datos | Medio-alto | Complementar con dataset sanitizado o evidencia operativa futura |

## Qué debe hacerse en fases posteriores

### Fase 1

1. Importar el dump en una base local aislada.
2. Verificar apertura de la app contra ese schema.
3. Confirmar con datos de prueba o dataset sanitizado las semánticas dudosas:
   - `archivos.idpersona`
   - dependencias reales de consolidación/depuración
   - tablas que participan en callbacks y SPEI

### Fases 2-4

1. No ejecutar migraciones estructurales como parte del upgrade Laravel.
2. Mantener el schema operativo estable.
3. Si aparece necesidad de DDL, tratarla como subproyecto explícito y no como efecto colateral del upgrade.

## Pendientes por confirmar

| Tema | Motivo |
| --- | --- |
| Cardinalidad y calidad de datos reales | El dump no contiene datos |
| Si `archivos.idpersona` refleja deuda histórica o un uso híbrido real | Solo puede confirmarse con data real |
| Si existen esquemas productivos adicionales no incluidos en el dump | No hay evidencia de otros dumps ni snapshots |
