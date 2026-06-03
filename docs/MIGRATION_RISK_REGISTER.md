# Registro de Riesgos de Modernizacion

Fecha de corte: 2026-06-02
Baseline: `C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

## Escala

- Severidad: `Critica`, `Alta`, `Media`
- Probabilidad: `Alta`, `Media`, `Baja`

## Riesgos vigentes

| ID | Riesgo | Severidad | Probabilidad | Impacto | Mitigacion propuesta | Estado |
| --- | --- | --- | --- | --- | --- | --- |
| R-01 | Credenciales, usuarios y endpoints hardcoded en integraciones financieras | Critica | Alta | Exposicion operativa, drift entre ambientes y riesgo de cambio no controlado | No tocar payloads ni endpoints en release candidate; exigir aceptacion explicita, vaulting posterior y fase separada de seguridad/integraciones | Abierto |
| R-02 | `database/migrations` no representa el schema operativo real | Critica | Alta | Un cambio estructural basado en migrations puede corromper el baseline | Mantener `database/centrodecobros.sql` mas uso real en codigo como referencia primaria | Abierto |
| R-03 | La automatizacion de autenticacion y flujos reales sigue siendo parcial fuera del dataset controlado | Alta | Media | Posibles regresiones silenciosas en datos reales o contratos externos | Fase 33 recupero Feature completos y browser admin/cliente/guest con SQLite controlado; falta UAT/sandbox real | Mitigado parcial |
| R-04 | `principal.blade.php` y el contrato fijo de assets siguen siendo frontera sensible | Alta | Alta | Cualquier cambio de orden, nombre o ownership puede romper shell y guest | Mantener el contrato actual y no tocar `principal.blade.php` en cambios posteriores sin evidencia fuerte | Mitigado controlado |
| R-05 | La lane `plantilla.*` sigue fuera de Vite y fuera de una racionalizacion completa | Alta | Alta | Riesgo de drift entre toolchain moderno y runtime legacy restante | Mantenerla como deuda residual aceptada; cualquier migracion adicional debe abrirse como programa separado | Mitigado controlado |
| R-10 | Realtime sigue con configuracion Pusher hardcoded | Alta | Media | Validacion websocket end-to-end incompleta y riesgo operativo por ambiente | Tratar realtime como carril separado con configuracion externalizada y sandbox propio | Abierto |
| R-11 | `npm audit` reporta `29` vulnerabilidades (`5 low`, `16 moderate`, `8 high`) | Alta | Alta | Deuda de seguridad residual en tooling/dependencias y posible exposicion en dev/build | Abrir fase separada de dependencias; no mezclar con estabilizacion funcional ni contratos externos | Abierto |
| R-17 | Guest publico depende aun de `plantilla.css` y layouts Blade legacy | Media | Media | Un cambio visual o de layout podria romper `/login` y `/url` | Mantener ownership actual y tratar CSS/layout guest como corte separado | Mitigado controlado |
| R-18 | El shell autenticado sigue dependiendo del build de Vue con compilador por template raiz en Blade | Media | Alta | Quitar el alias al build con compilador romperia el mount del root Vue | Mantener la configuracion actual hasta una fase dedicada de desacople Blade/Vue | Abierto controlado |
| R-20 | Sidebar y shell Blade siguen dependiendo de `data-menu-target` alineado con `menu` | Media | Media | Drift silencioso de navegacion entre Blade y Vue | Mantener smoke/browser sobre modulos vivos y centralizar mapa de menu solo en una fase futura puntual | Abierto controlado |
| R-21 | El residual `ajax/hash` sigue encapsulado como compatibilidad temporal | Media | Media | Puede persistir dependencia dormida en superficies no auditadas | Mantenerlo opt-in y retirarlo solo con evidencia adicional | Mitigado parcial |
| R-22 | Reproducir pruebas locales depende de MySQL operativo y de prerequisitos del host | Media | Media | Si MySQL no esta disponible, fallan `phpunit` y parte de la bateria autenticada | Documentar prerequisitos de entorno y usar ventana controlada de validacion | Mitigado controlado |
| R-23 | `template.shared.js` retiene compatibilidad minima legacy | Media | Media | Puede existir dependencia dormida fuera del set auditado | Mantenerlo acotado y retirarlo solo con evidencia | Mitigado controlado |
| R-26 | Ownership por registro aun no esta validado con DB real | Alta | Media | Un usuario cliente podria operar recursos ajenos si algun controlador no cubierto escapa a los guards | Fase 32 agrego ownership/whitelists por controlador y pruebas preparadas; ejecutar Feature reales cuando DB local este habilitada | Mitigado parcial |
| R-27 | `composer audit` no es ejecutable con Composer 2.2.6 del host | Media | Media | No hay evidencia actual de advisories PHP con el comando nativo | Ejecutar auditoria con Composer moderno o herramienta equivalente en siguiente validacion | Abierto |
| R-28 | PHP Linux CLI no tiene `pdo_sqlite` y MySQL local rechaza credenciales | Media | Alta | El runner local depende de PHP WAMP para ejecutar Feature completos | Documentar PHP WAMP/SQLite como runner local o instalar `pdo_sqlite` en PHP Linux | Mitigado parcial |
| R-29 | Mocks Pagadetodo pueden desviarse del contrato externo real | Alta | Media | Los tests pasan contra un contrato controlado pero no prueban drift de sandbox/proveedor | Comparar payloads mock contra sandbox oficial cuando existan credenciales no productivas | Abierto |
| R-30 | Webhooks financieros sin firma/origen fuerte | Alta | Media | Spoofing o payloads no autenticos pueden generar estados incorrectos | Fase 34 agrego validacion minima e idempotencia; falta firma/origen con especificacion del proveedor | Mitigado parcial |
| R-31 | Workspace actual no es checkout Git activo y contiene artefactos accidentales en raiz | Alta | Media | Publicar la carpeta tal cual puede subir archivos locales, SQLite, node_modules/vendor o snippets de navegador | Crear repo limpio, `.gitignore`, branch/tag controlado y limpieza antes de GitHub/deploy | Abierto |
| R-32 | Scheduler duplicado en sandbox paralelo contra la misma DB | Critica | Media | Puede generar cargos o revisar estados dos veces si convive con produccion | No instalar cron del sandbox; agregar locks/comandos dedicados antes de activarlo | Abierto |
| R-33 | Sandbox oficial Pagadetodo no disponible | Alta | Alta | No se puede demostrar equivalencia entre mock y contrato real del proveedor | Obtener URL/credenciales sandbox no productivas y ejecutar matriz mock vs sandbox | Abierto |

## Riesgos cerrados en Fase 30

| ID | Riesgo | Resolucion | Evidencia |
| --- | --- | --- | --- |
| R-25 | Advisory `CVE-2026-33347` en `league/commonmark` | Cerrado al actualizar `league/commonmark 2.8.1 -> 2.8.2` | `composer audit` limpio y `php vendor/bin/phpunit` en verde |
