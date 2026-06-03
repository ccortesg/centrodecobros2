# Módulo: Depurar clientes

## Propósito
Permitir a un administrador eliminar físicamente clientes/personas que no tienen relaciones activas que comprometan la integridad operativa.

## Flujo funcional
1. Entrar a **Catálogos > Depurar** (visible solo para admin).
2. Elegir usuario.
3. Buscar clientes por nombre, email o celular.
4. Definir cantidad de registros por página (`10`, `25`, `50`, `100`).
5. Seleccionar uno o más clientes elegibles.
6. Confirmar depuración.
7. Backend revalida elegibilidad y elimina en transacción (`clientes` + `personas`).

## Elegibilidad de clientes
Solo se listan clientes del usuario seleccionado que **no** tengan relaciones en:
- `transacciones.idcliente`
- `transaccionesDom.idcliente`
- `archivos.idpersona`
- `users.id` (misma identidad `personas.id`)

## Seguridad e integridad
- Endpoint protegido por validación explícita de admin (`idrol=1`) en controlador.
- Validación de entrada (`idusuario`, `cliente_ids`).
- Revalidación de pertenencia/elegibilidad dentro de `DB::transaction`.
- Uso de `lockForUpdate` para reducir condiciones de carrera.
- Estrategia **todo-o-nada**: si un registro deja de ser elegible, se cancela toda la operación.

## Consideración importante sobre `archivos`
La aplicación usa `archivos.idpersona` como vínculo con `personas/clientes`, aunque el dump histórico muestra una FK inconsistente hacia `transacciones.id`. La elegibilidad del módulo sigue el uso real del sistema en código.
