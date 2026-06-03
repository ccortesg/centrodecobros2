# Plan de Pruebas y Validación por Fase

Fecha de corte: 2026-03-14

## Principio rector

Como el proyecto no tiene una suite automatizada de negocio útil, la validación futura debe combinar:

- smoke tests manuales guiados,
- chequeos técnicos de entorno,
- validación de rutas y contratos HTTP,
- pruebas controladas de integraciones en sandbox o endpoint mock.

## Estado actual

| Elemento | Estado |
| --- | --- |
| Pruebas unitarias/feature | Smoke suite activa: `21` tests, `114` assertions |
| Cobertura de negocio | No existe una suite completa; se combina smoke backend con validacion manual controlada |
| Baseline visual | Existe validacion browser manual; no existe baseline visual automatizado |
| Contratos API documentados con fixtures | No existe |
| Reproducibilidad frontend | Validada en `Node 22.22.1` / npm `10.9.4` con `npm ci`, `npm run development` y `npm run production` |

## Catálogo mínimo de validación

### Grupo A: salud técnica

| ID | Validación | Resultado esperado |
| --- | --- | --- |
| ENV-01 | `php artisan --version` | App reconoce framework y runtime |
| ENV-02 | `php artisan schedule:list` | Scheduler visible |
| ENV-03 | `php artisan route:list` | Debe funcionar; si no, bloqueo tratado/documentado |
| ENV-04 | Login page | Renderiza correctamente |
| ENV-05 | Logging | Se generan logs sin error de permisos |

### Grupo B: autenticación y shell

| ID | Validación | Resultado esperado |
| --- | --- | --- |
| AUTH-01 | Login con `usuario/password` | Acceso correcto al shell principal |
| AUTH-02 | Logout | Sesión termina correctamente |
| AUTH-03 | Menú por rol | Sidebar correcto por `idrol` |
| AUTH-04 | Notificaciones por polling | Se listan sin error |

### Grupo C: catálogos y CRUD básicos

| ID | Validación | Resultado esperado |
| --- | --- | --- |
| CAT-01 | Listado de estados/ciudades | Datos visibles |
| CAT-02 | Listado de clientes | Paginación y filtros operan |
| CAT-03 | Usuario/rol | Listado básico operativo |
| CAT-04 | Archivos adjuntos | Subida/descarga/eliminación controlada |

### Grupo D: flujos financieros críticos

| ID | Validación | Resultado esperado |
| --- | --- | --- |
| PAY-01 | Generar liga de pago desde UI | Transacción persistida y respuesta controlada |
| PAY-02 | Generar liga por API `GenerarLigaPago` | Contrato legacy respetado |
| PAY-03 | Generar liga de domiciliación | Persistencia y respuesta correctas |
| PAY-04 | Ejecutar cargo recurrente controlado | `transaccionesDom` y callback según corresponda |
| PAY-05 | Generar referencia SPEI | Registro en `transacciones` y salida correcta |
| PAY-06 | Registrar pago SPEI | `pagospei` y callback correctos |
| PAY-07 | Cancelar SPEI | `cancelaspei` correcto |
| PAY-08 | Recibir webhook `Service/EntregarPago*` | Registro en `respuestas` y eventual callback |

### Grupo E: OTP/SMS (retirado en Fase 21)

| ID | Validación | Resultado esperado |
| --- | --- | --- |
| OTP-01 | Flujo verify/SMS retirado | No aplica desde Fase 21 |
| OTP-02 | Flujo verify/SMS retirado | No aplica desde Fase 21 |
| OTP-03 | Flujo verify/SMS retirado | No aplica desde Fase 21 |

### Grupo F: exportación, importación y reporteo

| ID | Validación | Resultado esperado |
| --- | --- | --- |
| EXP-01 | Exportar clientes | Descarga válida |
| EXP-02 | Exportar transacciones | Descarga valida (`CSV` streaming para listado general y `xlsx` para reportes filtrados) |
| EXP-03 | Exportar respuestas | Descarga válida |
| EXP-04 | Exportar SPEI | Descarga válida |
| IMP-01 | Iniciar importación masiva | Estado creado |
| IMP-02 | Procesar importación | Progreso y resultado consistentes |
| IMP-03 | Cancelar importación | Estado cancelado sin corrupción |
| IMP-04 | Descargar log de importación | Archivo descargable |

### Grupo G: realtime y scheduler

| ID | Validación | Resultado esperado |
| --- | --- | --- |
| RTC-01 | Polling de notificaciones | Operativo |
| RTC-02 | Broadcast Pusher/Echo | Operativo si el ambiente lo configura |
| SCH-01 | Scheduler diario | No rompe app; ejecución controlada |
| SCH-02 | Revisión cada 5 minutos | No rompe app; ejecución controlada |

## Validación por fase

### Fase 1

Obligatorias:

- Grupo A completo
- Grupo B completo
- al menos un caso representativo de cada uno de C, D, E, F y G

Salida mínima:

- smoke suite documentada,
- formato de evidencia definido,
- pendientes explícitos por falta de dataset o sandbox.

### Fase 2: Laravel 8 -> 9

Obligatorias:

- Grupo A completo
- Grupo B completo
- Grupo D completo
- Grupo F completo
- Grupo G completo

Especial atención:

- mail/callbacks,
- cambios de paquetes satélite,
- rutas API sin prefijo `/api`.

### Fase 3: Laravel 9 -> 10

Obligatorias:

- todo lo de Fase 2,
- más validación exhaustiva de importación masiva y exportaciones.

Especial atención:

- `maatwebsite/excel`,
- `laravel/ui`,
- helpers legacy.

### Fase 4: Laravel 10 -> 11

Obligatorias:

- todo lo de Fase 3,
- más revisión de logs, excepciones, scheduler y broadcasting.

Especial atención:

- desempeño del shell principal,
- comportamiento de notificaciones,
- jobs implícitos y colas sync.

### FE-H1 / FE-4 / FE-5

Obligatorias:

- inventario y clasificacion de advisories/deprecations si la fase toca dependencias,
- baseline visual antes/después,
- navegación por menús,
- filtros y selects en reportes,
- OTP público,
- realtime,
- descarga de archivos.

## Evidencia mínima por ejecución de fase

Cada fase futura debe guardar evidencia documental de:

1. versiones finales de PHP, Composer y framework,
2. resultado de validaciones críticas,
3. errores encontrados y tratamiento,
4. paquetes ajustados,
5. rollback documental.

## Rollback documental mínimo

Antes de abrir una fase futura debe existir:

- copia de `composer.json` y `composer.lock`,
- copia de `package.json` y `package-lock.json`,
- snapshot de entorno `.env` sanitizado,
- snapshot de assets/compilación si el frontend participa,
- checklist de riesgos vigente.

## Pendientes por confirmar

| Tema | Motivo |
| --- | --- |
| Dataset sanitizado para pruebas funcionales profundas | El dump actual no tiene datos |
| Endpoint mock o sandbox para callbacks | Los flujos externos son sensibles |
| Estrategia automatizada de screenshots/baseline visual | No existe todavía |
