# Fase 0: Diagnóstico Técnico Ejecutivo y Detallado

Fecha de corte: 2026-03-11

## 1. Diagnóstico ejecutivo

`centrodecobros` es un monolito Laravel legacy cuyo backend y frontend siguen estrechamente acoplados. La modernización es viable, pero no mediante un salto directo ni mediante upgrades mecánicos de dependencias.

Las condiciones que obligan a una ruta incremental son:

- controladores financieros de gran tamaño,
- dependencias legacy sin soporte para versiones mayores posteriores,
- frontend Vue 2 acoplado a Blade y jQuery,
- secretos e integraciones incrustados,
- y una base de datos real cuya forma operativa no coincide con las migrations históricas.

## 2. Fuentes de verdad usadas

1. Código actual del repositorio.
2. `composer.json` y `composer.lock`.
3. `package.json` y `package-lock.json`.
4. `routes/web.php`, `routes/api.php`, `routes/channels.php`.
5. `database/centrodecobros.sql`.
6. Documentación existente en `docs/`.
7. Documentación oficial consultada para upgrades:
   - Laravel 9/10/11/12 upgrade guides
   - Laravel releases
   - Laravel Vite
   - Vue 2.7 release notes
   - Vue 3 migration guide

## 3. Mapa de consistencia documental de `docs/`

| Documento existente | Estado | Hallazgo |
| --- | --- | --- |
| `docs/README.md` | Vigente parcial | Sirve como índice, pero no como plan rector de migración |
| `docs/DIAGNOSTIC_REPORT.md` | Vigente parcial | Resume bien la naturaleza legacy, pero no trae faseo operativo ni matriz de compatibilidad |
| `docs/ARCHITECTURE.md` | Vigente | Refleja correctamente el monolito acoplado y la ausencia de capa de servicios |
| `docs/STACK_AND_DEPENDENCIES.md` | Parcial y desalineado | La línea general es correcta, pero hay drift de versiones reales; Vue lockeado en `2.5.13` y jQuery en `3.3.1` |
| `docs/ROUTES_AND_FLOW.md` | Vigente parcial | Describe rutas y flujos, pero no las puertas de control para migración |
| `docs/DOMAIN_MODEL.md` | Vigente parcial | Resume bien entidades y relaciones inferidas |
| `docs/DATABASE_MODEL.md` | Parcial | No refleja toda la realidad detectada; faltaba `tmp_personas_merge` y la semántica conflictiva de `archivos.idpersona` |
| `docs/DATABASE_MIGRATIONS_VS_DUMP.md` | Vigente parcial | Detecta la divergencia, pero requería consolidación en un reality check operativo |
| `docs/INTEGRATIONS.md` | Vigente parcial | Enumera integraciones, pero faltaba conectar riesgos con fases concretas |
| `docs/FRONTEND_ANALYSIS.md` | Parcial | Describe el frontend actual, pero no compara rutas de migración ni desacopla toolchain de runtime |
| `docs/SECURITY_AND_RISKS.md` | Vigente parcial | Señala riesgos reales, pero faltaba registro vivo con severidad, probabilidad y fase |
| `docs/ENVIRONMENT_AND_OPERATION.md` | Parcial | Necesitaba objetivos por fase y estrategia de entorno aislado |
| `docs/AI_AGENT_GUIDE.md` | Vigente | Útil para sesiones futuras |
| `docs/DEVELOPER_ONBOARDING.md` | Vigente | Útil como guía de entrada, no como documento de migración |
| `docs/MODULES/*.md` | Vigentes parciales | Útiles para contexto funcional; no sustituyen plan maestro, matriz ni checklist de validación |

## 4. Hallazgos técnicos base

### Backend

- Laravel real: `8.83.23`.
- PHP observado: `7.4.33`.
- `laravel/ui`: `3.4.6`.
- `guzzle`: `7.4.5`.
- `maatwebsite/excel`: `3.1.40`.
- `barryvdh/laravel-dompdf`: `0.8.7`.
- `pusher/pusher-php-server`: `3.3.1`.
- `telesign/telesign`: `3.0.0`.
- `wildbit/swiftmailer-postmark`: `3.3.0`.

### Frontend/build

- Vue real lockeado: `2.5.13`.
- Laravel Mix: `2.0.0`.
- webpack: `3.10.0`.
- `node-sass`: `4.7.2`.
- `vue-loader`: `13.7.1`.
- `vue-template-compiler`: `2.5.13`.
- `bootstrap-sass`: `3.3.7`.
- `jQuery`: `3.3.1`.
- `axios`: `0.17.1`.
- `laravel-echo`: `1.4.0`.
- `pusher-js`: `4.3.1`.
- `vue-select`: `2.5.0`.
- `vue-barcode`: `1.1.0`, sin uso confirmado.

## 5. Puntos de acoplamiento que condicionan la migración

| Punto de acoplamiento | Evidencia | Riesgo |
| --- | --- | --- |
| Navegación por `menu` numérico | `app.js`, `contenido.blade.php`, sidebars | Alto |
| Shell Blade con Vue global | `principal.blade.php` | Alto |
| OTP público con jQuery | `resources/views/verificar/*` | Alto |
| Assets estáticos sin `mix()` | Blade principales | Medio |
| Scheduler con métodos de controladores | `app/Console/Kernel.php` | Alto |
| API legacy sin `/api` prefix | `RouteServiceProvider`, `routes/api.php` | Crítico |

## 6. Flujos críticos que no deben romperse

1. Ligas de pago por UI y por API.
2. Domiciliación y cargos recurrentes.
3. SPEI: generación, consulta, pago y cancelación.
4. Respuestas de pago y webhooks `Service/*`.
5. OTP/SMS en flujo público.
6. Exportaciones por Excel.
7. Importación masiva de transacciones.
8. Notificaciones y dashboard.

## 7. Riesgos operativos detectados en código

### Seguridad y secretos

- Credenciales y endpoints del proveedor hardcoded en controladores.
- Credenciales TeleSign hardcoded.
- Key y cluster de Pusher hardcoded en frontend.
- `.env.example` con valores sensibles aparentes.

### Seguridad de acceso

- `Administrador` no restringe.
- Parte del control de privilegios está embebido en controladores.

### Observabilidad

- `route:list` falla por una ruta huérfana.
- No hay pruebas automatizadas útiles.

## 8. Base de datos: realidad operativa

### Hallazgo principal

El dump real debe tratarse como baseline de schema. Las migrations históricas no reconstruyen el sistema actual.

### Discrepancias clave

- Tablas financieras sin migrations equivalentes.
- `users` real con columnas operativas adicionales.
- `tmp_personas_merge` existe en dump y código.
- `archivos.idpersona` presenta conflicto entre FK y uso operativo.

## 9. Ambiente local observado

| Elemento | Estado |
| --- | --- |
| PHP | `7.4.33` |
| Composer | `2.7.3` |
| Apache | `2.4.58` |
| Node/npm | No disponibles en `PATH` |
| Git | Sin `.git` en la carpeta |

## 10. Diagnóstico de viabilidad por frente

### Laravel

Viable si se ejecuta por fases:

- Fase 1 baseline,
- Fase 2 Laravel 9,
- Fase 3 Laravel 10,
- Fase 4 Laravel 11,
- Fase 5 evaluación Laravel 12.

### Frontend

No viable un salto directo a Vue 3.  
Ruta recomendada:

- baseline frontend,
- salida de Mix 2,
- Vue 2.7,
- evaluación Vite,
- evaluación Vue 3.

## 11. Pendientes por confirmar

| Tema | Motivo | Cómo validarlo en el futuro |
| --- | --- | --- |
| Uso real de DomPDF | Paquete instalado, uso no confirmado | Buscar trazas/ejecuciones reales en Fase 1 |
| Build legacy funcional | Node/npm no están listos | Preparar carril legacy y ejecutar build |
| Semántica real de `archivos.idpersona` | El dump y el código discrepan | Revisar data real y relaciones en BD local |
| Cobertura de datos para smoke tests | El dump no trae datos | Dataset sanitizado o fixtures representativos |

## 12. Conclusión de Fase 0

El sistema no está listo para un upgrade directo ni para una modernización frontend agresiva. Sí está listo para iniciar una Fase 1 de congelamiento técnico y baseline reproducible, que es el prerequisito mínimo razonable para abrir los saltos Laravel y el frente frontend sin improvisación.
