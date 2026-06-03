# Seguridad, robustez y mantenibilidad

Ultima actualizacion: 2026-06-02

## Hallazgos de seguridad
1. **Credenciales de integracion**
   - `.env.example` ya esta saneado con placeholders locales, pero `.env` real no debe versionarse.
   - Preparacion GitHub/sandbox externalizo credenciales Pagadetodo y Pusher desde controladores/frontend hacia `.env`/`config/services.php`.
   - Riesgo residual: los valores reales deben rotarse/provisionarse fuera de Git antes de cualquier ambiente compartido.
2. **Autorización débil**
   - Fase 31 corrigio el middleware `Administrador`: admin tiene acceso total, cliente queda acotado a su superficie operativa y otros roles reciben `403`.
   - Fase 32 agrego ownership por registro y whitelists en clientes, archivos, transacciones, respuestas, SPEI, domiciliacion y exportaciones criticas.
   - Riesgo residual: falta UAT MySQL real y formalizar politicas por accion si aparecen roles adicionales.
3. **Autenticación API heterogénea**
   - Algunos endpoints validan usuario/token por payload plano.
   - Fase 31 agrego validacion temprana en SPEI, lector, domiciliacion, cargo recurrente y cancelacion, pero no cambio el esquema de autenticacion externa.
   - Fase 32 agrego mock Pagadetodo controlado por `services.pagadetodo.mock`, sin sustituir validacion contra sandbox oficial.
   - Fase 33 valido el mock en Feature completos y browser, pero no conecto sandbox oficial para evitar uso accidental de produccion.
   - Fase 34 agrego validacion minima e idempotencia en webhooks `Service/*`, pero no agrega firma/origen porque falta especificacion del proveedor.
   - Preparacion GitHub/sandbox deja `PAGADETODO_*` como variables de entorno; sin valores reales, solo debe operar con `PAGADETODO_MOCK=true`.
4. **Riesgo de superficie de ataque legacy**
   - `npm audit --audit-level=low` sigue reportando 29 vulnerabilidades (`5 low`, `16 moderate`, `8 high`).
   - `npm audit --omit=dev` queda limpio, pero la deuda dev/tooling requiere carril separado.
5. **Riesgo de despliegue paralelo**
   - La nueva version no debe activar scheduler contra la misma DB mientras convive con la version PHP 7.4.
   - En Ubuntu 20.04 no se debe asumir instalacion nativa soportada de PHP 8.3 por PPA; usar contenedor o migracion de host.
6. **Riesgo de publicacion desde workspace sucio**
   - El corte 2026-06-02 confirma que la carpeta actual no es un checkout Git activo.
   - Hay archivos accidentales en raiz generados por snippets de navegador; no deben subirse a GitHub.
   - Antes de deploy se requiere repo limpio, `.gitignore` y revision de artefactos locales.

## Robustez / integridad
- Múltiples relaciones sin FK en BD -> riesgo de huérfanos.
- Manejo de errores inconsistente (capturas parciales, silencios, variables potencialmente no definidas).
- Fase 31 corrigio el bloqueo puntual de `CancelarDomiciliacion` por `$e` indefinido.
- Fase 32 aislo contratos Pagadetodo con mock controlado, pero los contratos externos reales siguen pendientes de sandbox.
- Fase 33 recupero Feature completos con PHP WAMP/SQLite y preparo SQLite persistente para browser controlado.
- Corte 2026-06-02 confirma que Feature aislado sigue verde, pero Feature contra MySQL local sigue bloqueado por acceso denegado a `centro_user`.
- Fase 34 deja Feature completo en verde con 54 tests y 234 assertions despues de endurecer webhooks.
- Lógica financiera en controladores monolíticos difícil de probar.
- Folios generados con consultas `max()+1` pueden sufrir carreras bajo concurrencia.

## Deuda técnica
- Desalineación migrations vs esquema real.
- Nombres de columnas mixtos (`snake_case`, `CamelCase`, mayúsculas) y semántica ambigua.
- Código duplicado (generación de folios, armado de payloads, parseo respuestas).

## Fragilidad estructural
- Procesos recurrentes dependen de métodos de controlador.
- Exportes/reportes con posibles referencias a alias/tabla incorrectos en algunas consultas legacy.
- Rutas potencialmente rotas por referencias a controladores inexistentes.

## Oportunidades (solo documentación)
- Consolidar “fuentes de verdad” por módulo.
- Definir catálogo de contratos externos y esquemas de payload.
- Preparar roadmap de desacoplamiento por fases.
