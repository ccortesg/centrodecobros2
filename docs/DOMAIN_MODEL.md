# Modelo de dominio

## Entidades raíz y núcleo
- **Persona**: identidad base compartida.
- **User**: cuenta de acceso (1:1 con persona, relación implícita por `users.id = personas.id`).
- **Cliente**: entidad comercial (1:1 con persona por `clientes.id = personas.id`).
- **Transaccion**: solicitud de cobro (liga, domiciliación, SPEI, caja/terminal por `tipo`).
- **Respuesta**: resultado de pago reportado por proveedor.
- **TransaccionDom**: ejecución de cargo recurrente asociado a una transacción base.

## Entidades auxiliares
- `consultaspei`, `pagospei`, `cancelaspei`.
- `cancelacionesDom`, `cancelacionesLector`.
- `archivos` (evidencia documental por entidad ligada en código a persona).
- `notifications` (Laravel notifications).
- `roles`, `estados`, `ciudades`, `codigos`.

## Relaciones de negocio críticas
- Persona ↔ User (1:1, explícita en FK del dump).
- Persona ↔ Cliente (1:1, explícita en FK del dump).
- Cliente ↔ Ciudad (N:1 inferida por `idciudad`, sin FK explícita en dump).
- Transaccion ↔ Respuesta (1:N inferida por `respuestas.idtransaccion` y también por `reference/responseReference`).
- Transaccion ↔ TransaccionDom (1:N inferida por `idtransaccion`).
- Transaccion ↔ Pago/Consulta/Cancelación SPEI (1:N inferida por `idtransaccion`).

## Reglas de negocio detectadas
- `tipo` en transacción segmenta flujos (liga, domiciliación, SPEI, caja/terminal).
- `productivo` separa entorno productivo/sandbox en ejecución y folios.
- Folio y referencias se generan incrementalmente por contexto de negocio.
- En respuestas aprobadas, puede enviarse notificación callback al cliente (`ligaPago`/`ligaRecurrente`).
- OTP para verificación pública en flujo `verify`.

## Banderas/estados relevantes
- `condicion` (activo/inactivo) en varios catálogos y usuarios.
- `status` en respuestas y transacciones domiciliadas.
- `enviada` en tablas de trazabilidad de callback.

## Procesos transaccionales/recurrentes
- Scheduler ejecuta:
  - cargos recurrentes domiciliados (`ejecutarCron`) diariamente.
  - revisión de status SPEI/liga cada 5 minutos.
