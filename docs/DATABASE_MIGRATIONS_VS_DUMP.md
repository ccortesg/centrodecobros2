# Comparativa: migrations vs esquema operativo

Ultima actualizacion: 2026-06-03

## Hallazgo central
El set de migrations del repositorio **no representa el esquema real operativo**. La evidencia historica se tomo de `database/centrodecobros.sql`, pero ese dump no debe publicarse ni asumirse como artefacto versionado. Para decisiones actuales usar MySQL productivo o dump autorizado fuera de Git, mas uso real en codigo.

## Tablas en dump sin migration correspondiente
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
- `transacciones`
- `transaccionesDom`

## Migrations presentes que no corresponden al dump actual
- `categorias`, `articulos`, `proveedores`, `ingresos`, `detalle_ingresos`, `ventas`, `detalle_ventas`.
- Estas estructuras reflejan un dominio distinto (inventario/ventas), heredado de otro sistema o etapa previa.

## Coincidencias parciales
- `personas`, `users`, `roles`, `notifications`, `password_resets` sí existen en ambos, pero con variaciones de columnas/tamaños en algunos casos.

## Divergencias relevantes
1. **`archivos`**
   - Modelo/controlador usan `idpersona`.
   - Relación Eloquent declara `idtransaccion` en fillable y `hasOne(Transaccion)`.
   - Dump define FK `idpersona -> transacciones.id` (inconsistente semánticamente).
2. **`users`**
   - Migration base crea estructura mínima.
   - Dump añade campos de integración (`token`, `IntegrationID`, `BusinessID`, `productivo`, etc.) críticos para operación.
3. **`transacciones`/`respuestas`/`transaccionesDom`**
   - Críticas para negocio, pero sin migraciones en repo.

## Riesgos derivados
- Imposible reconstruir entorno confiable solo con migrations.
- Alto riesgo en CI/CD, onboarding y ambientes nuevos.
- Cambios de schema futuros sin baseline confiable.

## Fuente de verdad probable
Para estado actual productivo, la fuente de verdad mas fuerte es MySQL real o dump autorizado fuera de Git + uso real en controladores/modelos.

## Recomendación documental (sin implementar cambios)
- Tratar migrations actuales como legado histórico.
- Definir estrategia de “schema reconciliation” en fase futura antes de refactor.
