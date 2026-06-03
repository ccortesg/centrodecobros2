# Fase 34 - Validacion Pagadetodo, webhooks e idempotencia

Fecha de cierre: 2026-06-02  
Workspace: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`  
Baseline origen: `C:\temp\centrodecobros_phase33_entorno_sandbox_e2e`

## Dictamen

`GO tecnico parcial fuerte para sandbox controlado; NO-GO para liberacion directa o cobro real`.

Fase 34 crea una copia aislada y endurece webhooks `Service/*` sin cambiar nombres de rutas publicas, payloads externos de exito ni contrato de assets. La validacion oficial con sandbox Pagadetodo queda bloqueada porque no hay URL/credenciales sandbox no productivas diferenciadas disponibles en el proyecto. Se mantiene `PAGADETODO_MOCK=true` para pruebas.

## Cambios realizados

### Respuestas de liga y lector

Archivo: `app/Http/Controllers/RespuestaController.php`

1. `Service/EntregarPagoLiga` y `Service/EntregarPagoLigaToken` validan JSON y campos minimos:
   - `reference`
   - `response`
   - `amount`
2. `Service/EntregarPagoLector` aplica la misma validacion minima.
3. Campos opcionales del proveedor ya no se leen con indice directo obligatorio; se guardan con valor por defecto.
4. Reintentos duplicados con la misma combinacion `idtransaccion + reference` regresan `success` sin crear otro registro.
5. Fallas de esquema regresan `error` con HTTP `422` en vez de provocar `500`.
6. El reenvio a `users.ligaPago` evita dereferenciar usuario nulo.

### SPEI `Service/*`

Archivo: `app/Http/Controllers/TransaccionController.php`

1. `Service/ConsultaClabe` corrige ramas con referencias nulas:
   - ya no llama `$transaccion->save()` cuando se procesa referencia vacia;
   - ya no asigna `$transaccion->id` cuando no existe transaccion.
2. `Service/PagoClabe` valida JSON y campos minimos:
   - `clabe`
   - `monto`
   - `fecha`
   - `transaccion`
3. `Service/PagoClabe` rechaza monto no numerico con respuesta controlada `codigo=50`.
4. `Service/PagoClabe` deduplica por `transaccion`; un reintento devuelve el resultado guardado sin insertar otro pago.
5. `Service/CancelaClabe` valida JSON y campos minimos:
   - `clabe`
   - `fecha`
   - `monto`
   - `transaccion`
   - `autorizacion`
6. `Service/CancelaClabe` deduplica por `transaccion + autorizacion`.
7. `Service/CancelaClabe` evita fallar cuando no existe pago asociado y devuelve respuesta controlada.

### Pruebas agregadas

Archivo: `tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php`

Cubre:

- webhook liga con payload incompleto;
- webhook liga con payload minimo valido;
- webhook liga duplicado;
- webhook lector duplicado;
- webhook lector con monto invalido;
- `ConsultaClabe` con referencia vacia;
- `PagoClabe` con payload incompleto;
- `PagoClabe` duplicado;
- `CancelaClabe` con payload incompleto;
- `CancelaClabe` duplicado.

## Matriz mock vs sandbox oficial

No se ejecuto llamada real a Pagadetodo porque no hay credenciales ni URL sandbox no productivas disponibles. No se usaron credenciales productivas.

| Contrato | Mock controlado | Sandbox oficial | Resultado Fase 34 |
| --- | --- | --- | --- |
| `GenerarLigaPago` | Verde desde Fase 33; conserva `code`, `url`, `reference`, `referenceEmisor`. | Bloqueado por falta de credenciales/URL sandbox. | Sin cambio funcional; contrato mock sigue cubierto por Feature. |
| `GenerarLigaDomiciliacion` | Verde desde Fase 33. | Bloqueado. | Sin cambio funcional. |
| `GenerarSpei` | Verde desde Fase 33; genera CLABE mock. | Bloqueado. | Sin cambio funcional. |
| `GenerarLigaLector` | Verde desde Fase 33. | Bloqueado. | Sin cambio funcional. |
| `CargoDomiciliacion` | Verde desde Fase 33. | Bloqueado. | Sin cambio funcional. |
| `CancelarDomiciliacion` | Verde desde Fase 33. | Bloqueado. | Sin cambio funcional. |
| `Service/EntregarPagoLiga*` | Fase 34 agrega validacion e idempotencia. | Bloqueado. | Mejorado con pruebas. |
| `Service/EntregarPagoLector` | Fase 34 agrega validacion e idempotencia. | Bloqueado. | Mejorado con pruebas. |
| `Service/ConsultaClabe` | Fase 34 corrige errores controlados. | Bloqueado. | Mejorado con pruebas. |
| `Service/PagoClabe` | Fase 34 agrega validacion e idempotencia. | Bloqueado. | Mejorado con pruebas. |
| `Service/CancelaClabe` | Fase 34 agrega validacion e idempotencia. | Bloqueado. | Mejorado con pruebas. |

## Evidencia ejecutada

| Validacion | Resultado |
| --- | --- |
| Copia aislada | Creada en `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`. |
| `php -l app/Http/Controllers/RespuestaController.php` | OK |
| `php -l app/Http/Controllers/TransaccionController.php` | OK |
| `php -l tests/Feature/Phase34/WebhookIdempotencyFeatureTest.php` | OK |
| `php artisan route:list` | OK, 97 rutas. |
| `php artisan schedule:list` | OK, 2 tareas registradas; no deben activarse en sandbox paralelo con misma DB. |
| `composer validate --no-check-publish --no-interaction` | OK; Composer 2.2.6 emite deprecations, pero valida. |
| `npm run production` | OK; assets generados/verificados. |
| `npm audit --omit=dev --audit-level=low` | 0 vulnerabilidades. |
| `npm audit --audit-level=low` | 29 vulnerabilidades dev/tooling: 5 low, 16 moderate, 8 high. |
| `php vendor/bin/phpunit --testsuite Unit` | OK, 13 tests, 72 assertions. |
| `WebhookIdempotencyFeatureTest` | OK, 10 tests, 35 assertions. |
| Feature completo WAMP/SQLite | OK, 54 tests, 234 assertions. |

## Porcentaje actualizado por modulo

| Modulo / funcionalidad | Avance | Cambio Fase 34 |
| --- | ---: | --- |
| Ligas de pago | 84% | Webhooks de respuesta de liga ahora tienen validacion e idempotencia. |
| Domiciliacion | 83% | Sin cambio en generacion/cargo; webhooks compartidos mejoran respuestas. |
| SPEI generacion | 82% | Sin cambio en generacion; `Service/*` SPEI mejora robustez. |
| SPEI consulta/pago/cancelacion | 84% | `ConsultaClabe`, `PagoClabe`, `CancelaClabe` ahora evitan errores por payload incompleto/reintento. |
| Lector / terminal | 79% | Webhook lector ahora valida payload minimo y deduplica. |
| Respuestas y webhooks | 80% | Mejora principal de Fase 34; faltan firma/origen y sandbox oficial. |
| Contratos Pagadetodo controlados | 78% | Mock completo sigue verde; falta sandbox real. |
| API cliente externo | 74% | Se mantiene auth `User`/`Password`; falta rate limit/versionado/firma. |
| Scheduler | 70% | Sin cambio; no habilitar en sandbox paralelo. |
| Readiness sandbox paralelo | 77% | Mejoran webhooks, pero faltan sandbox oficial, secretos y GitHub limpio. |
| Readiness reemplazo productivo | 56% | Sigue bloqueado por sandbox oficial, UAT MySQL, secretos, npm audit y operacion productiva. |

## Porcentaje actualizado por rol/acceso

| Rol / acceso | Avance | Estado |
| --- | ---: | --- |
| Administrador `idrol=1` | 88% | Sin cambio; Feature completo sigue verde en SQLite. |
| Cliente operativo `idrol=2` | 87% | Sin cambio directo; ownership no se altero. |
| Otros roles | 58% | Bloqueo `403` se mantiene. |
| Guest publico | 76% | Sin cambio. |
| Cliente API externo | 74% | Webhooks y respuestas son mas tolerantes e idempotentes; auth robusta sigue pendiente. |
| Proveedor/webhook externo | 68% | Avanza por validacion minima, deduplicacion y errores controlados; falta firma/origen y sandbox oficial. |
| Scheduler / sistema | 70% | Sin cambio; riesgo de duplicidad sigue abierto. |

## Riesgos residuales

1. Sandbox oficial Pagadetodo no validado por falta de credenciales/URL no productivas.
2. Webhooks aun no verifican firma/origen porque no hay especificacion del proveedor disponible en esta fase.
3. Integraciones y credenciales Pagadetodo/Pusher siguen hardcoded; se debe tratar en carril separado.
4. `npm audit` completo sigue con 29 hallazgos dev/tooling.
5. Composer local sigue sin `composer audit`.
6. Fase 34 no cambia scheduler; no debe activarse contra la misma DB que produccion.
7. La carpeta sigue sin ser un repositorio Git limpio para publicacion.

## Siguiente paso recomendado

Abrir carril de preparacion GitHub/release sandbox paralelo, o carril de secretos si el objetivo inmediato es reducir riesgo de integraciones antes de publicar.

## Prompt optimo posterior

```text
Quiero preparar el proyecto Centro de Cobros Fase 34 para subirlo a GitHub y desplegar una version sandbox paralela sin tocar la version productiva actual.

Usa como baseline:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

Objetivo:
- crear o validar un repositorio Git limpio;
- preparar `.gitignore` para excluir `.env`, `vendor/`, `node_modules/`, logs, SQLite local, outputs, test-results y archivos accidentales de raiz;
- definir branch/tag de release sandbox;
- confirmar si los assets compilados se versionaran o si se generaran en CI;
- preparar comandos exactos de deploy y rollback;
- documentar vhost/subdominio separado, PHP 8.3, sesiones/cache/logs aislados, scheduler deshabilitado y `PAGADETODO_MOCK=true` hasta sandbox oficial;
- ejecutar validaciones de rutas, Unit, Feature WAMP/SQLite y build production;
- actualizar documentacion con checklist final de publicacion.

Restricciones:
1. No cambiar funcionalidad de negocio.
2. No ejecutar migraciones sobre DB productiva.
3. No publicar secretos ni archivos locales.
4. No activar scheduler en la version paralela.
5. No usar credenciales productivas de Pagadetodo.
```
