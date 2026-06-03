# Ruta Propuesta de Modernización Laravel

Fecha de corte: 2026-03-11

## Fuentes oficiales base

- Laravel 9 upgrade guide: <https://laravel.com/docs/9.x/upgrade>
- Laravel 10 upgrade guide: <https://laravel.com/docs/10.x/upgrade>
- Laravel 11 upgrade guide: <https://laravel.com/docs/11.x/upgrade>
- Laravel 12 upgrade guide: <https://laravel.com/docs/12.x/upgrade>
- Laravel releases: <https://laravel.com/docs/12.x/releases>

## Criterio rector

No se recomienda salto directo a la última versión mayor. La ruta defendible es:

1. `Laravel 8.83.23 -> 9`
2. `Laravel 9 -> 10`
3. `Laravel 10 -> 11`
4. `Laravel 12`: solo evaluación posterior

## Fase 1 previa al primer salto

### Objetivo

Crear un baseline local reproducible y una base mínima de validación antes de tocar dependencias del framework.

### Prerrequisitos

- Copia aislada del proyecto.
- Importación local del dump SQL sin usar migrations.
- Entorno local con PHP 7.4 operativo.
- Evidencia del estado actual de rutas, scheduler, login y assets.

### Tareas obligatorias

1. Confirmar arranque local de la aplicación.
2. Confirmar el problema actual de `route:list` y decidir su tratamiento seguro.
3. Verificar permisos de `storage` y logging.
4. Construir smoke suite mínima de negocio.
5. Documentar secretos hardcoded y mover su tratamiento a fase controlada posterior.
6. Confirmar build legacy o, si no compila, documentar bloqueo reproducible.

### Puerta de salida

- El estado base es reproducible.
- Existen casos de validación manual/semiautomatizada por flujo crítico.
- Los bloqueos estructurales del baseline están documentados.

## Fase 2: Laravel 8 -> Laravel 9

### Objetivo

Subir el framework a Laravel 9 con el menor cambio funcional posible.

### Requisitos de plataforma

| Elemento | Objetivo recomendado |
| --- | --- |
| PHP | `8.0.x` |
| Composer | `2.2+`, recomendado `2.7.x` |
| Apache | `2.4.x` |

### Paquetes y temas a tratar antes o durante el salto

| Paquete/tema | Estado actual | Tratamiento requerido |
| --- | --- | --- |
| `pusher/pusher-php-server` | `3.3.1` | Actualizar antes de asumir broadcasting estable en L9 |
| `barryvdh/laravel-dompdf` | `0.8.7` | No declara soporte L9; actualizar o encapsular su uso |
| `wildbit/swiftmailer-postmark` | `3.3.0` | Laravel 9 migra a Symfony Mailer; asumir reemplazo hasta demostrar compatibilidad |
| `fideloper/proxy` | `4.4.2` | Tratar como legado; revisar retiro o sustitución por config nativa |
| `laravel/helpers` | `1.5.0` | Mantener solo si el código realmente lo necesita; iniciar plan de eliminación |
| Auth scaffolding residual | `laravel/ui 3.4.6` | Puede mantenerse en L9, pero debe auditarse por código muerto/inconsistente |
| Ruta rota `ArticuloController` | Existente | Debe tratarse porque rompe introspección y validación operativa |

### Validaciones obligatorias de salida

- Login por `usuario/password`.
- Menú principal admin/cliente.
- Rutas públicas de verificación OTP.
- Generación de liga de pago y generación de domiciliación en sandbox o endpoint controlado.
- Endpoints `Service/*`.
- Exportaciones.
- Scheduler (`schedule:list` y ejecución controlada).
- Polling/notificaciones y, si aplica, canal Pusher.

### Criterios de aceptación

- App estable en Laravel 9.
- Sin regresiones funcionales críticas detectadas.
- Matriz de compatibilidad actualizada.
- Plan maestro y registro de riesgos actualizados.

## Fase 3: Laravel 9 -> Laravel 10

### Objetivo

Elevar el framework a Laravel 10 y adoptar PHP 8.1.

### Requisitos de plataforma

| Elemento | Objetivo recomendado |
| --- | --- |
| PHP | `8.1.x` |
| Composer | `2.2+`, recomendado `2.7.x` |
| Apache | `2.4.x` |

### Bloqueos principales esperados

| Paquete/tema | Motivo |
| --- | --- |
| `laravel/ui 3.4.6` | No es base segura para Laravel 10; decidir actualizar o retirar lo residual |
| `maatwebsite/excel 3.1.40` | Soporte declarado hasta Laravel 9 |
| `barryvdh/laravel-dompdf 0.8.7` | Sin soporte declarado para L10 |
| `fideloper/proxy` | No es estrategia sostenible para L10 |
| `laravel/helpers` | No debe llegar intacto a L10 |
| Código legacy de auth scaffold | Puede romper por supuestos sobre esquema `users` |

### Validaciones obligatorias

Repetir las validaciones de Fase 2 y además:

- importación masiva,
- descarga de bitácora de importación,
- flujos SPEI completos de consulta/pago/cancelación,
- callbacks de pago,
- reportes por `tipo`.

### Criterios de aceptación

- Laravel 10 estable.
- PHP 8.1 estable.
- Ningún paquete crítico queda en versión sin soporte L10.

## Fase 4: Laravel 10 -> Laravel 11

### Objetivo

Llegar al objetivo backend recomendado: Laravel 11 con PHP 8.2.

### Requisitos de plataforma

| Elemento | Objetivo recomendado |
| --- | --- |
| PHP | `8.2.x` |
| Composer | `2.7.x` |
| Apache | `2.4.x` |

### Bloqueos principales esperados

| Paquete/tema | Motivo |
| --- | --- |
| `maatwebsite/excel` | Debe estar en rama compatible con Laravel 11 |
| `barryvdh/laravel-dompdf` | Debe estar en rama compatible con Laravel 11 o quedar retirado con evidencia |
| `pusher/pusher-php-server` | Debe estar en rama moderna compatible con PHP 8.2 |
| `laravel/ui` | Revaluar si sigue aportando algo al sistema real |
| Código legacy de bootstrap/app config | Laravel 11 simplifica estructura; migrar solo si aporta valor y no compromete estabilidad |

### Validaciones obligatorias

- Suite completa de Fase 3.
- Revisión detallada de scheduler financiero.
- Revisión de notificaciones.
- Verificación de logs, errores y manejo de excepciones.

### Criterios de aceptación

- Laravel 11 estable en local.
- PHP 8.2 estable.
- Todos los flujos críticos aprobados.
- Decisión explícita de congelar backend en 11 mientras se ataca el frente frontend.

## Fase 5: evaluación formal Laravel 12

### Objetivo

Decidir con evidencia si vale la pena subir a Laravel 12.

### Criterios para decidir “sí”

1. El sistema ya está estable en Laravel 11.
2. Los paquetes críticos tienen soporte confirmado para Laravel 12 y el PHP objetivo.
3. El costo operativo del salto es menor que el valor esperado.
4. El frente frontend no queda bloqueado o más riesgoso por ese salto.

### Criterios para decidir “no todavía”

1. Persisten paquetes sensibles sin soporte claro.
2. Aún no se completa la estabilización frontend.
3. No existe presión técnica real que justifique otro salto inmediato.

### Resultado esperado

Documento de decisión `go/no-go`, no upgrade automático.

## Orden recomendado de resolución de bloqueos

1. Baseline reproducible y observabilidad local.
2. Paquetes incompatibles con Laravel 9.
3. Paquetes incompatibles con Laravel 10.
4. Paquetes incompatibles con Laravel 11.
5. Evaluación Laravel 12.
