# Fase 6 - FE-2 Build

Ultima actualizacion documental: 2026-03-12

## Estado final

Resultado: `GO`

FE-2 implementacion queda cerrada en la copia aislada `C:\temp\centrodecobros_phase6_fe2_build` con build operativo en `Node 22.22.1`, sin migrar Vue runtime, sin introducir Vite, sin cambiar `resources/views/principal.blade.php` y preservando el contrato de salida:

- `public/js/app.js`
- `public/js/plantilla.js`
- `public/css/plantilla.css`

## Resumen ejecutivo

1. Se creo primero la copia aislada `C:\temp\centrodecobros_phase6_fe2_build` a partir de `C:\temp\centrodecobros_phase5_fe2_discovery`.
2. La implementacion migro el pipeline desde `laravel-mix 2.0.0` + `webpack 3.10.0` a `laravel-mix 6.0.49` + `webpack 5.105.4`.
3. Vue runtime se mantuvo en `2.5.13`; no se abrio FE-3.
4. `node-sass 4.7.2` dejo de formar parte de la lane operativa; la salida oficial actual no compila Sass y el proyecto ya no instala `node-sass`.
5. `principal.blade.php` quedo byte a byte igual respecto de la copia de discovery.
6. `plantilla.js` y `plantilla.css` quedaron byte-identicos al baseline legacy en build `dev`.
7. `app.js` cambia de hash y tamano en build `dev`, pero conserva el contrato de nombre/ruta y el cambio queda explicado por el salto a webpack 5 y por el ajuste minimo de interop para componentes Vue 2.
8. `npm ci`, `npm run dev` y `npm run production` quedaron validados en la lane moderna.

## Contexto de entrada

1. Backend ya estable en Laravel `11.48.0` sobre PHP `8.2.24`.
2. FE-2 Discovery ya cerrado en `C:\temp\centrodecobros_phase5_fe2_discovery`.
3. Baseline frontend legacy documentada en `Node 8.17.0` / npm `6.13.4`.
4. Contrato Blade vigente:
   - `css/plantilla.css`
   - `js/app.js`
   - `js/plantilla.js`
5. FE-2B separado para `Role.vue`, `ReporteSpei.vue`, `ReporteCargosRecurrentes.vue` y `/url`.

## Rutas usadas

| Tema | Ruta |
| --- | --- |
| Ruta base usada | `C:\temp\centrodecobros_phase5_fe2_discovery` |
| Ruta nueva creada | `C:\temp\centrodecobros_phase6_fe2_build` |
| Resultado de la copia | `Creada correctamente antes de modificar archivos` |

## Lane exacta usada

| Item | Valor |
| --- | --- |
| Node detectado | `v22.22.1` |
| npm detectado | `10.9.4` |
| `.nvmrc` final | `22.22.1` |
| `package-lock.json` final | `lockfileVersion 3` |

## Estrategia aplicada

1. Se siguio la ruta recomendada por `docs/MIGRATION_FE2_BUILD_STRATEGY.md`: `laravel-mix 6` + `webpack 5`.
2. Se fijaron versiones exactas de dependencias directas para evitar drift por ranges semver modernos, especialmente en `vue`.
3. Se agrego `vue-loader 15.11.1` y `vue-template-compiler 2.5.13` para compilar `.vue` manteniendo Vue `2.5.13`.
4. Se mantuvieron `.styles()` y `.scripts()` en `webpack.mix.js` para preservar `plantilla.css` y `plantilla.js`.
5. Se agrego `.vue({ version: 2 })` en Mix para declarar explicitamente el carril Vue 2.
6. Se aplico un ajuste minimo no funcional en `resources/assets/js/app.js` para resolver componentes Vue via `component.default || component`; esto evita el drift de CommonJS/ES module al compilar con loader moderno.
7. Se agrego `scripts/local/run_mix_build.js` para ejecutar `dev` y `production` con cierre explicito del compilador. El wrapper evita que el proceso quede vivo despues de compilar en `Node 22.22.1`.
8. Se desactivo `extractComments` de Terser para impedir la emision de `public/js/app.js.LICENSE.txt` y mantener una salida mas fiel al contrato legacy.

## Toolchain resultante

| Tema | Antes | Despues |
| --- | --- | --- |
| Build wrapper | `laravel-mix 2.0.0` | `laravel-mix 6.0.49` |
| Bundler | `webpack 3.10.0` | `webpack 5.105.4` |
| Vue runtime | `2.5.13` | `2.5.13` |
| Vue loader | `13.7.1` | `15.11.1` |
| Vue compiler | `2.5.13` | `2.5.13` |
| Sass toolchain operativo | `node-sass 4.7.2` | `No requerido por la salida oficial actual` |
| Lane oficial de build | `Node 8.17.0 / npm 6.13.4` | `Node 22.22.1 / npm 10.9.4` |

Confirmaciones locales:

1. `node_modules/node-sass` no existe tras `npm ci`.
2. `node_modules/sass` tampoco fue necesario para esta fase.
3. `plantilla.css` sigue saliendo de concatenacion de CSS plano.

## Archivos modificados

### Build y configuracion

1. `package.json`
2. `package-lock.json`
3. `.nvmrc`
4. `webpack.mix.js`
5. `scripts/local/run_mix_build.js`

### Compatibilidad minima de frontend

1. `resources/assets/js/app.js`

### Documentacion

1. `docs/MIGRATION_PHASE_6_FE2_BUILD.md`
2. `docs/MIGRATION_MASTER_PLAN.md`
3. `docs/MIGRATION_CHANGELOG.md`
4. `docs/MIGRATION_RISK_REGISTER.md`
5. `docs/MIGRATION_DECISIONS_LOG.md`
6. `docs/MIGRATION_ENVIRONMENT_MATRIX.md`
7. `docs/MIGRATION_SMOKE_TESTS.md`
8. `docs/MIGRATION_NEXT_PROMPTS.md`
9. `docs/README.md`

## Validaciones ejecutadas

| Validacion | Resultado |
| --- | --- |
| `node -v` | `v22.22.1` |
| `npm -v` | `10.9.4` |
| `npm install` | OK; usado para regenerar lockfile y resolver `vue-loader` |
| `npm ci` | OK |
| `npm run dev` | OK |
| `npm run production` | OK |
| Verificacion de `principal.blade.php` | Sin cambios; hash identico a discovery |
| Verificacion de `public/js/app.js` | Emitido correctamente |
| Verificacion de `public/js/plantilla.js` | Emitido correctamente |
| Verificacion de `public/css/plantilla.css` | Emitido correctamente |
| Verificacion de artefacto extra `app.js.LICENSE.txt` | Eliminado; ya no se reemite |

## Comparacion contra la baseline legacy documentada

### Build `dev`

| Asset | Baseline legacy | FE-2 `dev` | Resultado |
| --- | --- | --- | --- |
| `public/js/app.js` | `2398202` bytes / `BC34653EE8B0A2CB0C5E5399E5EF23AAF47593500AF4CD4C08799B2CA3271330` | `2601002` bytes / `19365FAE30236EDA3A3807381F04D2BD8442F544D38F2CE100490AD44870FE6D` | `Cambia` |
| `public/js/plantilla.js` | `438302` bytes / `3C31EFB0DBB99914807C6B46980EE42FED63C7E8C2C890228B6C0EF73272FA8D` | `438302` bytes / `3C31EFB0DBB99914807C6B46980EE42FED63C7E8C2C890228B6C0EF73272FA8D` | `Identico` |
| `public/css/plantilla.css` | `301744` bytes / `F960DD80201C42062DA3753308E7FD646C6B33C2DFA56D14494705AF58E4F306` | `301744` bytes / `F960DD80201C42062DA3753308E7FD646C6B33C2DFA56D14494705AF58E4F306` | `Identico` |

### Motivo razonado del cambio en `app.js`

1. Webpack `5` genera un bootstrap distinto al de webpack `3`.
2. `vue-loader 15` cambia la interop de modulos para componentes `.vue`.
3. FE-2 agrega un ajuste minimo no funcional en `app.js` para resolver `component.default || component`.
4. No hubo cambio en `principal.blade.php`, ni en nombres de assets, ni en la concatenacion legacy de `plantilla`.

### Build `production`

| Asset | FE-2 `production` |
| --- | --- |
| `public/js/app.js` | `737965` bytes / `C319D645B4903641EF15987DB64502E631A32402D39839450889793768E7D854` |
| `public/js/plantilla.js` | `395219` bytes / `59B71934A34B78C5425941E227B9D6EF56DF474852D9B6F5B6B6ED7107D83092` |
| `public/css/plantilla.css` | `250320` bytes / `C1645260CA240E25D7BFD58A042E7DE90A55D0AB21C02DEB0549EBE1561B868D` |

Observacion:

1. El estado final dejado en la copia es `dev`, porque es el punto comparable con la baseline documentada de FE-1.
2. `production` quedo validado como lane funcional adicional; no deja artefactos extra fuera del contrato oficial.

## Estado del contrato Blade y assets

1. `resources/views/principal.blade.php` no se modifico.
2. El hash SHA-256 del archivo Blade es identico al de la copia de discovery:
   - `91287760C37DCA437CA12F8B3DAE385D4E6950A904EF38A6B0EBAA75755179E9`
3. Las rutas cargadas desde Blade siguen siendo:
   - `css/plantilla.css`
   - `js/app.js`
   - `js/plantilla.js`

## Resultado final

FE-2 implementacion queda en `GO`.

Se cumplieron los criterios de salida:

1. Existe nueva copia aislada para la fase.
2. La lane moderna `Node 22.22.1` quedo activa y validada.
3. El build ya no depende operativamente de `Node 8.17.0`.
4. El runtime Vue sigue en `2.5.13`.
5. `principal.blade.php` sigue intacto.
6. FE-2B queda separada como la siguiente fase para alinear funcionalidad viva legacy.

## Riesgos residuales

1. FE-2B sigue pendiente para `Role.vue`, `ReporteSpei.vue`, `ReporteCargosRecurrentes.vue` y `/url`.
2. `npm audit` sigue reportando `19` vulnerabilidades en dependencias legacy congeladas o en su toolchain asociado; los hallazgos mas visibles afectan `axios 0.17.1`, `lodash 4.17.4`, `jquery 3.3.1`, `bootstrap-sass 3.3.7`, `vue 2.5.13`, `vue-loader 15.11.1` y transitive deps de `laravel-mix`.
3. El riesgo de seguridad de esas dependencias no se corrige en FE-2 para no mezclar build migration con FE-2B, FE-3 o hardening funcional.
4. El bundle `app.js` ya no es byte-identico al baseline legacy, aunque el contrato de salida se mantiene.

## Rollback detallado

El rollback de FE-2 se mantiene por descarte de la copia aislada:

1. dejar de usar `C:\temp\centrodecobros_phase6_fe2_build`;
2. volver a operar desde `C:\temp\centrodecobros_phase5_fe2_discovery` para reusar la baseline frontend legacy congelada;
3. si se necesita regresar al carril legacy validado:
   - usar `Node 8.17.0` / npm `6.13.4`;
   - usar los hashes baseline documentados de `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css`;
4. si se desea limpiar el experimento:
   - borrar `C:\temp\centrodecobros_phase6_fe2_build`;
5. FE-2B debe partir de esta copia solo si se acepta formalmente el `GO` aqui documentado.
