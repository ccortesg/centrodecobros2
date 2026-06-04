# Modulo: Consolidar clientes

Ultima actualizacion: 2026-06-03

## Proposito

Permitir a un administrador consolidar clientes/personas duplicados por usuario operativo, preservando el registro principal mas antiguo y reasignando relaciones transaccionales.

## Archivos clave

- `app/Http/Controllers/ClienteController.php`
- Componente Vue de consolidacion en `resources/assets/js/components`
- Tablas: `clientes`, `personas`, `transacciones`, `transaccionesDom`, `archivos`, `tmp_personas_merge`

## Rutas

- `GET cliente/consolidar`
- `POST cliente/consolidar/combinar`

## Acceso por rol

- Solo admin (`idrol=1`).
- El frontend oculta el menu a cliente.
- El backend debe seguir validando admin aunque el frontend o request sea manipulado.

## Flujo funcional

1. Entrar a Catalogos > Consolidar.
2. Elegir usuario operativo.
3. Buscar clientes por nombre, email o celular.
4. Elegir tamano de pagina (`10`, `25`, `50`, `100`).
5. Seleccionar manualmente minimo 2 registros.
6. Confirmar combinacion.
7. Backend determina `keep_id` por `personas.created_at ASC`, desempate por `id ASC`.
8. Reasigna relaciones al principal.
9. Elimina duplicados secundarios dentro de transaccion.

## Tablas afectadas

- `clientes`: elimina secundarios y conserva principal.
- `personas`: elimina secundarios y complementa datos del principal cuando aplica.
- `transacciones.idcliente`: reasigna al `keep_id`.
- `transaccionesDom.idcliente`: reasigna al `keep_id`.
- `archivos.idpersona`: reasigna al `keep_id`.
- `tmp_personas_merge`: bitacora tecnica si la tabla existe.

## Controles de consistencia

- `DB::transaction`.
- `lockForUpdate` sobre clientes/personas seleccionados.
- Validacion de pertenencia por `idusuario`.
- Validacion de IDs existentes, distintos y recuperables.
- Verificacion de referencias pendientes antes de eliminar secundarios.
- Rollback automatico ante excepcion.

## Pruebas recomendadas

- Unit/Feature de seleccion alterada y pertenencia incorrecta.
- Feature SQLite si cambia reasignacion o validaciones.
- Smoke manual admin:
  - buscar duplicados;
  - paginar;
  - consolidar 2 registros de prueba;
  - confirmar que transacciones, domiciliaciones y archivos apuntan al principal.

## Pendientes y mejoras

- Agregar nuevas tablas al bloque transaccional si en el futuro referencian `clientes.id` o `personas.id`.
- Crear reporte previo de impacto antes de confirmar merge.
- Agregar auditoria funcional consultable por UI.
