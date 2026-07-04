# Seguridad, robustez y mantenibilidad

Ultima actualizacion: 2026-07-03

## Hallazgos de seguridad vigentes

1. Credenciales e integraciones
   - `.env.example` esta saneado; `.env` real no debe versionarse.
   - Pagadetodo y Pusher fueron externalizados hacia `.env`, `config/services.php` y variables `VITE_PUSHER_*`.
   - Los secretos reales deben vivir en el servidor/Docker o gestor de secretos, nunca en Git.
   - Pagadetodo fue probado exitosamente desde servidor en sandbox y productivo, confirmado por el propietario el 2026-06-08.
   - Las llamadas reales Pagadetodo no deben intentarse desde local porque el proveedor restringe el IP address de origen; local debe usar `PAGADETODO_MOCK=true`.

2. Autorizacion y ownership
   - Fase 31 corrigio middleware `Administrador`: admin tiene acceso total, cliente queda acotado por allowlist y otros roles reciben `403`.
   - Fase 32 agrego ownership por registro y whitelists en clientes, archivos, transacciones, respuestas, SPEI, domiciliacion y exportaciones criticas.
   - Riesgo residual: falta UAT formal por rol con datos reales controlados.

3. Autenticacion API heterogenea
   - Endpoints legacy aceptan `User`/`Password` por payload.
   - Validaciones tempranas y mock reducen regresiones locales, pero no sustituyen evidencia servidor ni un contrato moderno.
   - Webhooks `Service/*` tienen validacion minima e idempotencia local; falta firma/origen hasta recibir especificacion del proveedor.

4. Scheduler financiero
   - El scheduler ejecuta procesos sensibles de domiciliacion y revision de status.
   - No activar ni duplicar scheduler en ambientes paralelos contra la misma DB.
   - En produccion Docker, cualquier ajuste debe validar que solo exista un scheduler activo.

5. Publicacion y Git
   - La carpeta actual ya es repo Git en rama `main`.
   - `.gitignore` excluye secretos, dependencias, logs, SQLite, dumps, outputs, test-results, snippets accidentales y assets compilados.
   - Antes de push/deploy ejecutar `git status --short`.

6. Dependencias frontend
   - `npm audit --omit=dev --audit-level=low` es la frontera runtime usada en CI.
   - La auditoria completa puede incluir deuda dev/tooling y debe atenderse en carril separado, con build y smoke browser.

7. Auditoria de integraciones
   - Las bitacoras nuevas guardan headers/payloads sanitizados, no valores crudos de passwords, tokens, cookies, CSRF, CLABE ni datos de tarjeta.
   - El acceso a `Outgoing API Requests`, `Incoming API Requests` y `User Activity Log` es solo Administrador.
   - La purga recomendada es manual a 365 dias con `audit:purge`; no se modifico scheduler.

## Robustez e integridad

- Multiples relaciones no tienen FK confiables; validar relaciones desde uso real en codigo.
- Folios por `max()+1` pueden tener carreras bajo concurrencia.
- Controladores monoliticos elevan riesgo de regresion.
- Manejo de errores sigue mixto: algunas rutas tienen respuestas controladas y otras conservan patrones legacy.
- La auditoria no debe bloquear flujos financieros; si el insert de bitacora falla, solo debe registrarse warning.
- Feature tests con SQLite cubren regresiones importantes, pero UAT MySQL/productivo sigue siendo necesario para aceptar negocio.

## Riesgos por modulo

- Transacciones: alto acoplamiento con Pagadetodo, callbacks y reportes.
- Domiciliacion: scheduler y cargos recurrentes pueden duplicar efectos si se ejecutan dos instancias.
- SPEI: webhooks de pago/cancelacion dependen de contrato externo y de idempotencia local.
- Respuestas: falta firma/origen de proveedor.
- Clientes: consolidacion/depuracion debe preservar integridad en tablas sin FK completa.
- Notificaciones: polling funciona; realtime websocket no esta cerrado.
- Integraciones/Auditoria: riesgo de crecimiento de tablas y exposicion operativa si se amplian campos sin sanitizacion.

## Oportunidades de mejora

1. Adapter formal para Pagadetodo.
2. Comandos/jobs dedicados para scheduler en vez de metodos de controlador.
3. Politicas de autorizacion por accion.
4. Pruebas Feature por rol y por flujo financiero.
5. Documentacion del compose Docker productivo cuando este disponible.
6. Remediacion controlada de `npm audit` completo.
