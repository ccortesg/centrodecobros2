# Módulo: Consolidar clientes

## Propósito
Permitir a un administrador consolidar manualmente clientes/personas duplicados por usuario operativo, preservando el registro principal más antiguo y manteniendo integridad transaccional.

## Flujo funcional
1. Entrar a **Catálogos > Consolidar** (visible solo para admin).
2. Elegir usuario.
3. Buscar clientes por nombre, email o celular.
4. Definir cantidad de registros por página desde el selector en encabezado de tabla (`10`, `25`, `50`, `100`).
5. Seleccionar manualmente mínimo 2 registros.
6. Confirmar combinación.
7. Backend determina `keep_id` por `personas.created_at ASC`, desempate por `id ASC`.
8. Reasigna referencias y elimina duplicados secundarios.

## Paginación configurable en listado
- El listado de consolidación permite elegir el tamaño de página con un selector en el encabezado de la tabla.
- Valores habilitados: `10`, `25`, `50`, `100`.
- Al cambiar el valor, el frontend solicita de nuevo el listado desde la página 1, conservando filtros (`idusuario`, `buscar`).
- El backend consume el parámetro `offset` y lo aplica en `paginate($offset)`.

## Tablas afectadas
- `clientes` (eliminación de secundarios, preservación del principal)
- `personas` (eliminación de secundarios, preservación y complemento de datos en principal)
- `transacciones.idcliente` (re-asignación a `keep_id`)
- `transaccionesDom.idcliente` (re-asignación a `keep_id`)
- `archivos.idpersona` (re-asignación a `keep_id`, dependencia adicional detectada)
- `tmp_personas_merge` (bitácora técnica `keep_id/merge_id/motivo` cuando existe)

## Consideraciones de consistencia
- Operación envuelta en `DB::transaction`.
- Uso de `lockForUpdate` en clientes/personas seleccionados para reducir carreras.
- Validación de pertenencia por `idusuario` y existencia vigente de IDs.
- Verificación de referencias pendientes antes de eliminar secundarios.
- Rollback automático ante cualquier excepción.

## Riesgos y mitigaciones
- **Riesgo:** selección de IDs alterados en frontend.
  - **Mitigación:** validación backend (`exists`, `distinct`, pertenencia a usuario).
- **Riesgo:** ejecución concurrente doble.
  - **Mitigación:** locks + validación estricta de conteo de seleccionados recuperados.
- **Riesgo:** dependencias no evidentes por falta de FKs.
  - **Mitigación:** actualización explícita de `transacciones`, `transaccionesDom` y `archivos`; validación de residuos previo a delete.

## Mantenimiento futuro
- Si se agregan nuevas tablas con referencia a `clientes.id` o `personas.id`, incluirlas en el bloque transaccional de consolidación.
- Mantener visible únicamente para rol admin (`idrol=1`) en frontend y backend.
