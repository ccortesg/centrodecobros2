# Modulo: Clientes, personas, usuarios, roles y catalogos

Ultima actualizacion: 2026-06-03

## Proposito

Administrar identidades, datos de cliente, usuarios de acceso, roles y catalogos base para asociarlos a transacciones, archivos, reportes y ownership.

## Archivos clave

- `app/Http/Controllers/ClienteController.php`
- `app/Http/Controllers/UserController.php`
- `app/Http/Controllers/RolController.php`
- `app/Http/Controllers/EstadoController.php`
- `app/Http/Controllers/CiudadController.php`
- `app/Http/Controllers/ArchivoController.php`
- Modelos: `Persona`, `Cliente`, `User`, `Rol`, `Estado`, `Ciudad`, `Archivo`
- Componentes Vue relacionados en `resources/assets/js/components`

## Rutas principales

- `GET cliente`
- `POST cliente/registrar`
- `PUT cliente/actualizar`
- `GET cliente/exportar`
- `GET cliente/selectCliente`
- `GET archivo`
- `POST archivo/registrar`
- `GET archivo/descargar`
- `PUT archivo/eliminar`
- `GET user`
- `POST user/registrar`
- `PUT user/actualizar`
- `PUT user/activar`
- `PUT user/desactivar`
- `GET user/selectUsuario`
- `GET rol`
- `GET role`
- `GET rol/selectRol`
- `GET estado`, `POST estado/registrar`, `PUT estado/actualizar`, `PUT estado/activar`, `PUT estado/desactivar`
- `GET ciudad`, `POST ciudad/registrar`, `PUT ciudad/actualizar`, `PUT ciudad/activar`, `PUT ciudad/desactivar`

## Modelo relacional operativo

- `personas` funciona como entidad base.
- `users.id` y `clientes.id` apuntan a `personas.id`.
- `users.idrol` apunta a `roles.id`.
- `clientes.idusuario` vincula cliente con usuario/propietario operativo.
- `archivos.idpersona` se usa en codigo como vinculo con `personas/clientes`, aunque el dump historico contiene inconsistencias.

## Acceso por rol

- Admin (`idrol=1`): acceso completo al grupo protegido.
- Cliente (`idrol=2`): acceso limitado a su superficie permitida; ownership por registro restringe clientes, archivos, transacciones, respuestas y exportaciones.
- Otros roles: `403` en el middleware `Administrador` salvo rutas publicas/autenticacion.

## Criterio de duplicidad de clientes desde API

- La deduplicacion en altas API se aplica en controlador, sin persistir columnas normalizadas.
- Matching operativo: `idusuario` + `Nombre` + `Email` + `Telefono` normalizados en memoria.
- Regla vigente:
  - Mismo `idusuario`, mismo `Email`, mismo `Telefono` y mismo `Nombre` normalizados => reutiliza cliente.
  - Si cambia `Nombre`, se considera cliente diferente.
  - Si faltan datos suficientes, la transaccion puede continuar sin `idcliente` segun flujo.

## Estado actual

- Funcionalidad base operativa.
- Ownership y whitelists agregados en fases 31-32.
- `UserController` ya no selecciona hash de password en listados y actualiza password de forma condicional.
- Exportaciones criticas estan acotadas por propietario para rol cliente.

## Pruebas recomendadas

- `php artisan route:list`
- `php vendor/bin/phpunit --testsuite Unit`
- Feature SQLite si cambia ownership, exports o altas desde API.
- Smoke UI con admin y cliente:
  - listar clientes;
  - alta/edicion;
  - descarga/exportacion;
  - acceso a archivos;
  - usuarios/roles solo admin.

## Pendientes y mejoras

- Formalizar politicas por accion si aparecen nuevos roles.
- Normalizar relaciones y FKs en una fase de DB controlada.
- Agregar pruebas Feature especificas de archivos y usuarios.
- Documentar mapa completo de columnas cuando exista dump autorizado actualizado.
