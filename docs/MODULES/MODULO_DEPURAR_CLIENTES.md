# Modulo: Depurar clientes

Ultima actualizacion: 2026-06-03

## Proposito

Permitir a un administrador eliminar fisicamente clientes/personas que no tienen relaciones activas que comprometan integridad operativa.

## Archivos clave

- `app/Http/Controllers/ClienteController.php`
- Componente Vue de depuracion en `resources/assets/js/components`
- Tablas: `clientes`, `personas`, `transacciones`, `transaccionesDom`, `archivos`, `users`

## Rutas

- `GET cliente/depurar`
- `POST cliente/depurar/eliminar`

## Acceso por rol

- Solo admin (`idrol=1`).
- El backend revalida elegibilidad aunque el frontend filtre opciones.

## Flujo funcional

1. Entrar a Catalogos > Depurar.
2. Elegir usuario.
3. Buscar clientes por nombre, email o celular.
4. Elegir tamano de pagina (`10`, `25`, `50`, `100`).
5. Seleccionar uno o mas clientes elegibles.
6. Confirmar depuracion.
7. Backend revalida elegibilidad y elimina en transaccion.

## Elegibilidad

Solo se listan clientes del usuario seleccionado sin relaciones en:

- `transacciones.idcliente`
- `transaccionesDom.idcliente`
- `archivos.idpersona`
- `users.id` cuando coincide con `personas.id`

## Controles de seguridad e integridad

- Validacion explicita de admin.
- Validacion de `idusuario` y `cliente_ids`.
- Revalidacion de pertenencia/elegibilidad dentro de `DB::transaction`.
- `lockForUpdate` para reducir carreras.
- Estrategia todo-o-nada: si un registro deja de ser elegible, se cancela toda la operacion.

## Consideracion sobre `archivos`

El uso real del sistema vincula `archivos.idpersona` con `personas/clientes`. La elegibilidad sigue el codigo operativo, no la FK historica inconsistente del dump.

## Pruebas recomendadas

- Feature SQLite con cliente elegible, cliente con transacciones y cliente con archivos.
- Smoke admin con seleccion multiple.
- Verificar que cliente (`idrol=2`) no vea ni ejecute depuracion.

## Pendientes y mejoras

- Agregar auditoria funcional de eliminaciones.
- Evaluar soft delete en vez de delete fisico si negocio requiere recuperacion.
- Mantener actualizada la lista de tablas bloqueantes si aparecen nuevas relaciones.
