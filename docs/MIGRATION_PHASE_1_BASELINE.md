# Fase 1 - Baseline Local y Estado Tecnico Controlado

Ultima actualizacion documental: 2026-03-12  
Evidencia de comandos levantada localmente: 2026-03-11 `-07:00`

## Objetivo alcanzado

Fase 1 dejo una base local controlada para seguir con la modernizacion sin tocar negocio ni ejecutar upgrades mayores:

- baseline backend definido,
- lane frontend legacy identificado,
- smoke suite segura creada,
- plan maestro y prechecks listos,
- observabilidad minima recuperada con `route:list`.

## Fuentes de verdad usadas

1. `docs/` completo.
2. `composer.json` y `composer.lock`.
3. `package.json` y `package-lock.json`.
4. `routes/web.php` y `routes/api.php`.
5. `TransaccionController`, `TransaccionDomController`, `RespuestaController`.
6. Blade y Vue actuales en `resources/`.
7. `database/centrodecobros.sql`.
8. Configuracion en `config/`, `.env.example`, `app/Console/Kernel.php`, middleware y providers.

## Baseline backend confirmado

| Item | Estado confirmado | Evidencia |
| --- | --- | --- |
| PHP CLI | `7.4.33` | `php -v`, `scripts/local/check_baseline.ps1` |
| Composer | `2.7.3` | `composer --version` |
| Laravel | `8.83.23` | `php artisan --version` |
| Scheduler | Activo con 2 tareas | `php artisan schedule:list` |
| `route:list` | Funcional despues de corregir `/ciudad/listarCiudad` | `php artisan route:list` |
| DB local | Conexion operativa | `DB_OK` via baseline script |
| Dataset local actual | Cargado en la BD local actual | `users=9`, `transacciones=60555`, `respuestas=27149` |

## Baseline frontend confirmado

| Item | Estado confirmado | Evidencia |
| --- | --- | --- |
| Node observado en `PATH` | `16.17.1` | `node -v` |
| npm observado en `PATH` | `8.15.0` | `npm -v` |
| Lane legacy recomendada | `Node 8.17.0` + `npm 6.x` | `.nvmrc`, `node_modules/node-sass/vendor/win32-x64-57` |
| Toolchain legacy | Mix 2 + webpack 3 + node-sass 4.7.2 + Vue 2.5.13 | `package-lock.json` |
| Assets preservados | `public/js/app.js`, `public/js/plantilla.js`, `public/css/plantilla.css` | timestamps `2026-03-11 18:22` |
| Build limpio en Node actual | No verificado | `npm run dev` no termino dentro del timeout bajo Node 16 |

## Estrategia de base de datos local

### Decision operativa

1. El schema de referencia sigue siendo `database/centrodecobros.sql`.
2. Las migrations historicas no deben usarse para recrear el sistema.
3. El dump versionado en el repo no trae inserts; por si solo no reproduce login ni modulos con data.
4. La BD local observada en esta maquina ya contiene dataset funcional, pero ese dataset no esta versionado en el repo.

### Implicacion

El baseline de Fase 1 queda dividido en dos niveles:

- `schema reproducible`: si, desde `database/centrodecobros.sql`;
- `dataset funcional reproducible cross-machine`: no todavia, sigue siendo bloqueador para Fase 2.

## Validaciones automaticas preparadas

| Artefacto | Objetivo | Resultado actual |
| --- | --- | --- |
| `scripts/local/check_baseline.ps1` | Verificar runtime, DB, artisan y assets | Operativo |
| `scripts/local/check_route_alignment.ps1` | Detectar drift entre rutas, controladores y frontend | Operativo; hoy reporta gaps reales |
| `vendor/bin/phpunit tests/Feature/Smoke tests/Feature/ExampleTest.php` | Smoke suite segura de lectura | `OK (14 tests, 79 assertions)` |

## Hallazgos nuevos o ajustados respecto a Fase 0

1. `node` y `npm` si estan disponibles hoy en `PATH`; la documentacion Fase 0 quedo desactualizada en ese punto.
2. El arbol frontend instalado no corresponde al Node actual: `node-sass` fue instalado con ABI `57` (Node 8).
3. `route:list` ya no falla despues de corregir la ruta `/ciudad/listarCiudad`.
4. Las rutas guest `/url` siguen referenciando `showURL` y `openPublic`, metodos ausentes en `TransaccionController`.
5. El frontend todavia tiene wiring incompleto o muerto:
   - `ReporteCargosRecurrentes.vue -> /transaccionDom/exportarTransacciones`
   - `ReporteSpei.vue -> /pagospei/exportarReporteSpei`
   - `Role.vue -> /role`
6. El `GET /` de invitados no es una pagina base validada; hoy el smoke solo confirma respuesta `302`.
7. `Administrador` sigue siendo permisivo; la proteccion real continua parcialmente embebida en controladores.

## Cambios de soporte aplicados en Fase 1

1. Se corrigio el wiring de `/ciudad/listarCiudad` hacia `CiudadController@listarCiudad`.
2. Se registraron rutas omitidas ya implementadas para:
   - `PUT /respuesta/eliminar`
   - `GET /cancelaspei/exportar`
3. Se saneo `.env.example` para eliminar credenciales reales y dejar un template local seguro.
4. Se fijo `.nvmrc` a `8.17.0` para la lane legacy del frontend.
5. Se reemplazaron tests placeholder por smoke tests de lectura.

## Estado del baseline

| Area | Estado |
| --- | --- |
| Backend local actual | Verificado |
| Scheduler registrado | Verificado |
| `route:list` | Verificado |
| Login page | Verificada |
| `/main` | Verificado con `actingAs()` |
| `/dashboard` | Verificado con `actingAs()` |
| Modulos criticos en lectura | Verificados por smoke tests |
| Exportaciones reportadas por rutas | Parcial; faltan endpoints heredados de reportes |
| Build legacy limpio | Pendiente |
| Dataset funcional reproducible desde repo | Bloqueado |

## Conclusion operativa

Fase 1 deja un baseline suficientemente documentado y verificable para preparar Fase 2, pero no deja un `go` pleno todavia porque siguen abiertos dos bloqueadores de reproducibilidad:

1. el dataset funcional local no esta representado en el repo;
2. el build legacy no se ha rehecho en limpio dentro de la lane Node 8 fijada.
