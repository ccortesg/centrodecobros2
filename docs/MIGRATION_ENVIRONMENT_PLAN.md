# Plan de Entorno Local y Consideraciones Futuras para Producción

Fecha de corte: 2026-03-11

## Principios

1. Ninguna fase futura debe ejecutarse directamente sobre este snapshot si no existe aislamiento operativo.
2. La primera obligación es reproducir localmente el baseline actual, no modernizarlo todavía.
3. El entorno debe separarse en dos carriles:
   - `carril legacy` para reproducir el estado actual,
   - `carril objetivo` para preparar cada salto de versión.

## Estado observado al cierre de Fase 0

| Elemento | Estado observado |
| --- | --- |
| PHP CLI | `7.4.33` |
| Composer | `2.7.3` |
| Apache | `2.4.58` en WAMP |
| Node | No disponible en `PATH` |
| npm | No disponible en `PATH` |
| Git | No hay `.git` en este directorio |
| Queue | `sync` |
| Logging | Existe posible problema de escritura en `storage/logs` al ejecutar `route:list` |

## Versiones objetivo por fase

| Fase | PHP | Composer | Node / npm | Apache | Comentario |
| --- | --- | --- | --- | --- | --- |
| Fase 1 | `7.4.33` o equivalente compatible con Laravel 8 actual | `2.7.x` | Carril legacy recomendado: `Node 10 LTS` + `npm 6` como punto de partida, pendiente confirmación exacta por build real | `2.4.58` o `2.4.x` | El objetivo es reproducir el baseline |
| Fase 2 | `8.0.x` | `2.7.x` | Sin cambio obligatorio si frontend no se toca; mantener carril legacy disponible | `2.4.x` | Laravel 9 requiere PHP 8.0+ |
| Fase 3 | `8.1.x` | `2.7.x` | Si se abre FE-2, preparar carril moderno `Node 18 LTS` | `2.4.x` | Laravel 10 requiere PHP 8.1+ |
| Fase 4 | `8.2.x` | `2.7.x` | `Node 18 LTS` o superior si frontend ya salió de Mix 2 | `2.4.x` | Laravel 11 requiere PHP 8.2+ |
| Fase 5 | `8.2.x` o `8.3.x` según decisión L12 | `2.7.x` | Definir según estado frontend | `2.4.x` | Solo evaluación formal |

## Estrategia de aislamiento del repositorio

### Opción preferida

Usar un clon real del repositorio con `.git` y trabajar por `git worktree` o ramas aisladas por fase.

### Opción de contingencia

Si solo existe un snapshot sin `.git`, crear copias aisladas por fase:

- `centrodecobros-phase1-baseline`
- `centrodecobros-l9`
- `centrodecobros-l10`
- `centrodecobros-l11`

### Regla

Nunca ejecutar una fase de upgrade sobre la misma carpeta que sirve de baseline.

## Estrategia de base de datos local

1. Crear base local aislada exclusiva para migración.
2. Importar `database/centrodecobros.sql`.
3. No ejecutar `php artisan migrate`.
4. Documentar cualquier diferencia necesaria entre:
   - schema importado,
   - configuración Laravel,
   - y requerimientos de las rutas/consultas reales.

## Variables de entorno

### Reglas

1. No reutilizar secretos productivos.
2. Crear `.env` específico para cada entorno local o fase.
3. Redactar y rotar secretos expuestos en código antes de cualquier homologación seria.

### Variables especialmente sensibles

- `DB_*`
- `MAIL_*`
- `PUSHER_*`
- credenciales del proveedor de pagos
- credenciales TeleSign
- URLs de callback

## Validaciones mínimas de entorno en Fase 1

| Validación | Resultado esperado |
| --- | --- |
| `php artisan --version` | Laravel 8 arranca |
| Acceso a login | Vista funcional |
| Login con `usuario/password` | Flujo real de auth operativo |
| `php artisan schedule:list` | Tareas visibles |
| `php artisan route:list` | Debe quedar utilizable o con bloqueo documentado y tratado |
| Logging y storage | Escritura operativa |
| Importación del dump | Schema local utilizable |
| Build frontend | Se reconstruye o se documenta bloqueo reproducible |

## Scheduler, colas y notificaciones

### Scheduler

Tareas detectadas:

- `TransaccionDomController@ejecutarCron` diario a las `07:00`
- `TransaccionController@revisarStatus` cada `5` minutos

Validación requerida:

- confirmar ejecución segura local en modo controlado,
- identificar dependencias externas antes de correrlo con credenciales reales.

### Colas

- `QUEUE_DRIVER=sync` por defecto.
- No asumir procesamiento async en el baseline actual.

Validación requerida:

- revisar si notificaciones o procesos futuros dependen tácitamente de cola.

### Broadcasting

- backend configurable por env,
- frontend con key/cluster hardcoded en JS.

Validación requerida:

- definir una estrategia homogénea por ambiente antes de homologación.

## Build frontend por ambiente

### Carril legacy

Objetivo:

- confirmar si los assets actuales pueden compilarse con un runtime Node legado.

Riesgo principal:

- `node-sass 4.7.2` y `webpack 3` son frágiles en entornos modernos.

### Carril moderno

Se habilita solo cuando abra el frente FE-2.  
No debe activarse dentro de Fase 1 si con eso se mezcla reproducción con modernización.

## Preparación para futura homologación en producción

Antes de tocar producción debe existir:

1. inventario de secretos y plan de rotación,
2. whitelist/documentación de endpoints salientes,
3. plan de callbacks de prueba hacia endpoint controlado,
4. verificación de cron equivalente al scheduler actual,
5. checklist de permisos de storage y cache,
6. comparación de versiones PHP/Apache/MySQL contra el entorno objetivo,
7. decisión explícita sobre queue, mail y broadcast.

## Bloqueadores del entorno actuales

| Bloqueador | Impacto | Tratamiento propuesto |
| --- | --- | --- |
| No hay `.git` | Impide `git worktree` | Obtener clon real o usar copias aisladas |
| No hay Node/npm en `PATH` | Impide validar build | Preparar carril legacy en Fase 1 |
| `route:list` falla | Reduce observabilidad | Tratar en baseline técnico Fase 1 |
| Posible problema de permisos en logs | Puede ocultar fallas | Corregir/confirmar en Fase 1 |
