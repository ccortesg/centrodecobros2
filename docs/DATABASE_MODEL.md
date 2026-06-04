# Modelo de base de datos

Ultima actualizacion: 2026-06-03

Fuente principal historica analizada: `database/centrodecobros.sql`.

Nota vigente: el dump puede existir localmente para diagnostico, pero no debe asumirse versionado ni publicable. `.gitignore` excluye `database/*.sql`. Para decisiones productivas usar MySQL real o un dump autorizado entregado fuera de Git, mas el uso real en controladores/modelos.

## Catálogo completo de tablas y propósito

### Catálogos
- `roles`: perfiles de acceso.
- `estados`: catálogo geográfico.
- `ciudades`: catálogo geográfico dependiente de estado.
- `codigos`: catálogo auxiliar de códigos.

### Identidad / acceso
- `personas`: entidad base de identidad.
- `users`: acceso al sistema, credenciales y parámetros de integración.
- `password_resets`: recuperación de contraseña.
- `notifications`: notificaciones Laravel.

### Clientes / soporte
- `clientes`: datos fiscales/comerciales y bancarios.
- `archivos`: archivos asociados (en código se manejan por `idpersona`).

### Operación financiera
- `transacciones`: solicitudes de cobro de múltiples tipos.
- `respuestas`: respuesta del proveedor de pagos (incluye trazas de tarjeta/operación).
- `transaccionesDom`: cargos de domiciliación y su respuesta operacional.
- `consultaspei`: consultas de status SPEI.
- `pagospei`: resultados de pago SPEI.
- `cancelaspei`: cancelaciones SPEI.
- `cancelacionesDom`: cancelaciones de domiciliación.
- `cancelacionesLector`: cancelaciones de referencias de lector.

### Soporte técnico
- `migrations`: histórico de migraciones ejecutadas.

## Claves primarias y foráneas explícitas (dump)
### FKs explícitas
- `archivos.idpersona -> transacciones.id` (nombre de constraint sugiere `idtransaccion`, pero columna real es `idpersona`).
- `ciudades.idestado -> estados.id`.
- `clientes.id -> personas.id`.
- `users.id -> personas.id`.
- `users.idrol -> roles.id`.

### Relaciones inferidas (sin FK explícita)
- `clientes.idciudad -> ciudades.id`.
- `clientes.idusuario -> users.id`.
- `transacciones.idcliente -> clientes.id`.
- `transacciones.idusuario -> users.id`.
- `respuestas.idtransaccion -> transacciones.id`.
- `transaccionesDom.idtransaccion -> transacciones.id`.
- `transaccionesDom.idcliente -> clientes.id`.
- `consultaspei/pagospei/cancelaspei.idtransaccion -> transacciones.id`.

## Columnas clave por tabla (resumen)
- `transacciones`: `folio`, `PaymentTypes`, `Amount`, `Reference`, `ClientReference`, `url`, `responseReference`, `tipo`, `frecuencia`, `ProximoCargo`, `productivo`.
- `respuestas`: `reference`, `status`, `foliocpagos`, `auth`, `cd_response`, `cd_error`, `amount`, `response`, `enviada`.
- `transaccionesDom`: `Token`, `Amount`, `Reference`, `status`, `response_reference`, `response_token`, `enviada`, `productivo`.
- `users`: `usuario`, `password`, `idrol`, `token`, `IntegrationID`, `BusinessID`, `productivo`, `notificaPago`, `ligaPago`, `recurrente`, `ligaRecurrente`.
- `clientes`: `rfc`, `razon_social`, `clabe`, `forma_pago`, `plazo`, `idusuario`.

## Uso de JSON
Campos JSON detectados: `response` en `transacciones`, `respuestas`, `transaccionesDom`, `consultaspei`, `pagospei`, `cancelaspei`, `cancelacionesDom`, `cancelacionesLector`.

## Riesgos de integridad
- FKs incompletas para gran parte del dominio operativo.
- Relaciones críticas modeladas solo por convención de nombres.
- Nombres ambiguos/heredados (`User`, `Password`, camelcase mixto, singular/plural inconsistente).
