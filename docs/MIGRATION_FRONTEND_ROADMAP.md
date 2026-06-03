# Ruta Propuesta de Modernización Frontend

Fecha de corte: 2026-03-11

## Fuentes oficiales base

- Vue 2.7 release notes: <https://blog.vuejs.org/posts/vue-2-7-naruto>
- Vue 3 migration guide: <https://v3-migration.vuejs.org/>
- Laravel Vite: <https://laravel.com/docs/11.x/vite>

## Estado actual confirmado

### Arquitectura frontend

- Shell principal Blade en `principal.blade.php`.
- Registro global de componentes en `resources/assets/js/app.js`.
- Navegación por `menu` numérico.
- Vistas públicas de OTP con jQuery fuera del shell Vue.
- Assets servidos con nombres estáticos: `js/app.js`, `js/plantilla.js`, `css/plantilla.css`.

### Dependencias relevantes

- Vue `2.5.13`
- `laravel-mix 2.0.0`
- `webpack 3.10.0`
- `vue-loader 13.7.1`
- `vue-template-compiler 2.5.13`
- `node-sass 4.7.2`
- `bootstrap-sass 3.3.7`
- `jquery 3.3.1`
- `axios 0.17.1`
- `laravel-echo 1.4.0`
- `pusher-js 4.3.1`
- `vue-select 2.5.0`
- `vue-barcode 1.1.0` sin uso confirmado

## Diagnóstico de viabilidad

### Qué sí permite el diseño actual

- Mantener el frontend funcional mientras avanza el backend, porque los Blade consumen bundles estáticos.
- Separar cambios de build y cambios de runtime en fases distintas.

### Qué vuelve riesgosa una migración directa

1. `laravel-mix 2` depende de `webpack 3` y `vue-loader 13`; no es base segura para una ruta moderna.
2. `vue-select 2.5.0` es paquete Vue 2-only.
3. El runtime actual mezcla Vue con jQuery y Blade en flujos distintos.
4. Existe realtime con Pusher/Echo parcialmente hardcoded.
5. No hay routing SPA ni composición modular moderna; hay menú numérico y registro global de componentes.

## Comparación de escenarios

| Escenario | Descripción | Riesgo | Ventaja | Recomendación |
| --- | --- | --- | --- | --- |
| A | Saltar de Vue 2.5.13 + Mix 2 directo a Vue 3 + Vite | Muy alto | Acelera convergencia teórica | No recomendado |
| B | Subir Vue 2.5.13 -> Vue 2.7 sin tocar build primero | Alto | Reduce una variable | No recomendado; el toolchain actual es demasiado viejo |
| C | Modernizar build primero, luego Vue 2.7, luego evaluar Vite y Vue 3 | Medio | Separa riesgos y permite validación incremental | Recomendado |
| D | Mantener Vue 2.5.13 indefinidamente | Alto a mediano plazo | Costo inmediato menor | No recomendado como estrategia final |

## Recomendación

La ruta más segura es el escenario `C`.

## Secuencia recomendada

### FE-1: Baseline y reproducción controlada del frontend actual

Objetivo:

- documentar build actual,
- capturar pantallas críticas,
- probar el carril Node/npm legacy,
- confirmar cuáles bundles son realmente necesarios.

Validaciones:

- shell autenticado renderiza menú y dashboard,
- reportes por `tipo` funcionan,
- OTP público sigue operativo,
- notificaciones se muestran por polling.

### FE-2: Sustituir Laravel Mix 2 por toolchain mantenible sin cambiar runtime

Objetivo:

- salir de `webpack 3`, `node-sass 4.7.2` y `vue-loader 13`,
- mantener Vue 2 y el contrato visual/funcional actual.

Justificación:

- La release de Vue 2.7 requiere un baseline de tooling más moderno que el actual.
- Intentar Vue 2.7 sobre Mix 2 agrega riesgo innecesario.

Alcance esperado de esta subfase:

- modernizar build,
- respetar nombres de salida actuales,
- no tocar lógica de negocio ni UX.

### FE-3: Vue 2.5.13 -> Vue 2.7.x

Objetivo:

- estabilizar el runtime Vue en la última rama 2.x mantenible.

Temas a revisar:

- `vue-select 2.5.0`
- compatibilidad de componentes con cambios menores del runtime
- axios y plugins auxiliares
- mantenimiento de registro global y mixins legacy

Puerta de salida:

- todos los componentes `.vue` críticos funcionan bajo Vue 2.7,
- no hay regresión en reportes, filtros, exportaciones ni notificaciones.

### FE-4: Evaluación e introducción de Vite en etapa separada

Objetivo:

- decidir si conviene mover el build a Vite una vez estabilizado Vue 2.7.

Razón para separarlo:

- Vite cambia la manera de resolver y servir assets.
- El proyecto hoy usa assets legacy concatenados y rutas estáticas en Blade.
- Cambiar build y runtime en la misma fase multiplicaría el radio de falla.

Puerta de salida:

- build equivalente en Vite,
- Blade sigue consumiendo los bundles esperados,
- no se rompe el shell principal ni el OTP público.

### FE-5: Evaluación formal de Vue 3

Objetivo:

- decidir con evidencia si el proyecto debe migrar a Vue 3.

Bloqueos actuales para Vue 3:

- `vue-select 2.5.0` no es compatible.
- `vue-template-compiler` debe salir.
- el patrón de registro global y menú numérico no aporta valor a una migración directa.
- jQuery y Blade comparten responsabilidades con el frontend actual.

Condiciones mínimas para considerar Vue 3:

1. Toolchain moderno ya establecido.
2. Vue 2.7 estable.
3. Paquetes Vue 2-only reemplazados o encapsulados.
4. Baseline visual y funcional comparado contra producción/homologación.

## Impacto por librería actual

| Librería/tema | Impacto esperado | Tratamiento recomendado |
| --- | --- | --- |
| Vue 2.5.13 | Runtime legacy | Subir primero a 2.7 |
| `laravel-mix 2` | Bloquea toolchain moderno | Reemplazar antes de tocar Vue mayor |
| `bootstrap-sass 3` | UI legacy | Mantener temporalmente; reemplazo posterior |
| jQuery | Sigue activo en OTP y plantilla | Encapsular y reducir gradualmente |
| `laravel-echo` / `pusher-js` | Realtime | Actualizar por separado del salto Vue |
| `vue-select` | Vue 2-only | Actualizar o reemplazar antes de Vue 3 |
| `vue-barcode` | Sin uso confirmado | Confirmar y retirar si sigue muerto |
| Assets legacy `plantilla.*` | Base visual actual | Mantener estables durante backend upgrades |

## Qué no se recomienda hacer

1. Saltar a Vue 3 mientras siga vivo `laravel-mix 2`.
2. Introducir Vite y Vue 3 en una sola fase.
3. Reescribir la navegación de menú durante un salto Laravel.
4. Eliminar jQuery sin primero identificar todos sus puntos de uso.

## Dependencia con la ruta Laravel

La modernización frontend debe ir desacoplada de los saltos de framework backend, pero con puntos de sincronización:

- Fase 1 prepara baseline frontend.
- Fase 2 y Fase 3 pueden avanzar backend sin tocar el runtime frontend, siempre que los bundles actuales sigan sirviéndose.
- Después de Laravel 9 o Laravel 10 estable es razonable abrir FE-2.
- Vue 3 no debe intentarse mientras el backend siga inestable.

## Decisión recomendada al cierre de Fase 0

1. No migrar todavía a Vue 3.
2. No introducir Vite todavía.
3. Preparar Fase 1 para reproducir el build actual.
4. Planear un frente separado: `Mix 2 -> build mantenible -> Vue 2.7 -> evaluación Vite -> evaluación Vue 3`.
