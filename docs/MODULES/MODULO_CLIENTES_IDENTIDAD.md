# Módulo: Clientes, personas, usuarios y roles

## Propósito
Administrar identidades, cuentas de acceso y datos de cliente para asociarlos a transacciones.

## Archivos clave
- `ClienteController`, `UserController`, `RolController`, `EstadoController`, `CiudadController`
- Modelos `Persona`, `Cliente`, `User`, `Rol`, `Estado`, `Ciudad`
- Componentes Vue homónimos

## Modelo relacional
- `personas` como entidad base.
- `users.id` y `clientes.id` apuntan a `personas.id`.
- `users.idrol -> roles.id`.

## Riesgos
- Múltiples relaciones solo inferidas (sin FK).
- Validaciones de negocio inconsistentes entre módulos.
## Criterio de duplicidad de clientes desde API
- La deduplicación para altas desde `storeAPI` se aplica dentro del controlador, sin almacenar columnas normalizadas en la tabla `clientes`.
- El matching usa `idusuario` + (`Nombre`, `Email`, `Telefono`) normalizados en memoria para búsqueda.
- Regla de negocio vigente:
  - Mismo `idusuario` + mismos `Email` y `Telefono` + mismo `Nombre` (normalizados) => mismo cliente.
  - Si cambia `Nombre`, se considera un cliente diferente.
