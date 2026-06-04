# Diagnostico ejecutivo vigente

Ultima actualizacion: 2026-06-03

## Estado actual

Proyecto funcional en produccion por Docker, con Laravel 12, Vue 3/Vite y deuda tecnica legacy todavia relevante. La carpeta actual es el repositorio de trabajo en rama `main`; no se deben crear copias nuevas para cambios futuros.

## Hallazgos principales

1. Migrations historicas no reconstruyen el esquema operativo real.
2. La logica critica sigue concentrada en controladores grandes.
3. Pagadetodo/Pusher ya usan configuracion externalizada, pero secretos reales y sandbox oficial siguen siendo control operativo externo.
4. Integridad referencial parcial: muchas relaciones se infieren desde codigo.
5. Frontend Vue 3 sigue acoplado a rutas/campos legacy y a menu por codigos.
6. Scheduler financiero debe mantenerse bajo control para evitar duplicidad.

## Inconsistencias relevantes

- `archivos`: semantica historica de `idpersona`/FK no coincide completamente con uso real.
- `database/migrations`: conserva estructuras de dominio inventario/ventas no alineadas con Centro de Cobros.
- Realtime: polling funciona, websocket E2E sigue pendiente.
- Docker productivo funciona, pero compose/orquestacion no esta versionado en repo.

## Riesgos mayores

- Flujos de cobro/conciliacion (`transacciones`, `respuestas`, `transaccionesDom`, SPEI).
- Webhooks sin firma/origen formal hasta especificacion del proveedor.
- Scheduler recurrente.
- Cambios en `principal.blade.php` o contrato publico de assets.
- Publicacion accidental de secretos o artefactos locales.

## Mejoras ya cerradas

- Middleware `Administrador` con matriz real por rol.
- Ownership y whitelists en modulos criticos.
- Mock Pagadetodo controlado por configuracion.
- Webhooks `Service/*` con validacion minima e idempotencia local.
- Repo Git en `main`, `.gitignore` saneado y workflow CI de validacion.

## Prioridades recomendadas

1. Obtener sandbox oficial Pagadetodo y validar contratos con evidencia sanitizada.
2. Documentar compose/servicios Docker productivos cuando se tenga acceso al servidor.
3. Validar realtime Pusher/Echo con credenciales aisladas.
4. Extraer adapters/jobs en cambios pequenos, empezando por Pagadetodo y scheduler.
5. Ampliar Feature tests por rol y flujo financiero.
