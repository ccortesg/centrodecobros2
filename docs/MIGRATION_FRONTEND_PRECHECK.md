# Precheck Frontend Legacy

Ultima actualizacion: 2026-03-12
Estado formal post-Fase 4: `GO con salvedades`

## Contexto de entrada

1. La validacion manual controlada posterior a Fase 4 backend quedo cerrada localmente en `http://localhost:8000`.
2. El backend ya esta estable en Laravel `11.48.0` sobre PHP `8.2.24`.
3. Este precheck se ejecuto sin migrar Vue, sin introducir Vite y sin renombrar assets.

## Restricciones respetadas

1. No se migro a Vue `2.7` ni a Vue `3`.
2. No se introdujo Vite.
3. No se tocaron componentes `.vue`; solo se inspeccionaron.
4. No se cambiaron nombres de salida de assets ni referencias Blade.

## Lane legacy validada

| Item | Resultado confirmado |
| --- | --- |
| Workspace | `C:\temp\centrodecobros_phase4_l11` |
| Lane Node | `8.17.0` |
| Lane npm | `6.13.4` |
| Comando de activacion | `nvm use 8.17.0` |
| Reinstall limpio | `npm ci` |
| Build ejecutado | `npm run dev` |
| Resultado | `Compiled successfully` |

## Estado real del toolchain

| Tema | Estado |
| --- | --- |
| Runtime Vue | `2.5.13` |
| Bundler | `laravel-mix 2.0.0` + `webpack 3.10.0` |
| Sass | `node-sass 4.7.2` |
| ABI binario Sass | `win32-x64-57` |
| UI legacy | `bootstrap-sass 3.3.7` + jQuery |
| Realtime | `laravel-echo 1.4.0` + `pusher-js 4.3.1` |
| Referencia Blade | `resources/views/principal.blade.php` sigue cargando `css/plantilla.css`, `js/app.js` y `js/plantilla.js` de forma estatica |

## Reinstall limpio ejecutado

1. `node_modules` se elimino por completo.
2. `npm ci` termino correctamente bajo Node `8.17.0` / npm `6.13.4`.
3. `node-sass 4.7.2` volvio a descargar y validar `vendor/win32-x64-57/binding.node`.
4. `npm run dev` recompilo sin fallas y sin cambiar la salida esperada.
5. La lane Node `16.17.1` del host sigue siendo carril incorrecto para declarar reproducibilidad del frente legacy.

## Reproducibilidad de assets criticos

| Asset | Tamano | SHA-256 | Reproducible |
| --- | ---: | --- | --- |
| `public/js/app.js` | `2398202` bytes | `BC34653EE8B0A2CB0C5E5399E5EF23AAF47593500AF4CD4C08799B2CA3271330` | Si |
| `public/js/plantilla.js` | `438302` bytes | `3C31EFB0DBB99914807C6B46980EE42FED63C7E8C2C890228B6C0EF73272FA8D` | Si |
| `public/css/plantilla.css` | `301744` bytes | `F960DD80201C42062DA3753308E7FD646C6B33C2DFA56D14494705AF58E4F306` | Si |

## Gaps legacy confirmados y sin corregir

1. `resources/assets/js/components/Role.vue` sigue apuntando a `/role?page=...`, pero `routes/web.php` solo expone `/rol` y `resources/assets/js/app.js` registra `Rol.vue`.
2. `resources/assets/js/components/ReporteCargosRecurrentes.vue` lista con `/transaccionDom/reporteTransaccionesDom`, pero su export apunta a `/transaccionDom/exportarTransacciones`, endpoint inexistente en `routes/web.php`.
3. `resources/assets/js/components/ReporteSpei.vue` lista con `/pagospei/reportePagoSpei`, pero su export apunta a `/pagospei/exportarReporteSpei`, endpoint inexistente en `routes/web.php`.
4. `routes/web.php` mantiene `/url -> showURL / openPublic` con metodos faltantes en `TransaccionController`.
5. Estos gaps se documentan como funcionalidad viva legacy; no se corrigieron en este precheck para no mezclar diagnostico con remediacion.

## GO / NO-GO actual

### `GO`

1. El build legacy ya es reproducible en su lane correcta.
2. Los assets `public/js/app.js`, `public/js/plantilla.js` y `public/css/plantilla.css` se regeneran con hashes identicos al baseline.
3. El frente puede permanecer congelado mientras se planifica FE-2 sin tocar runtime Vue ni nombres de assets.

### `NO-GO`

1. No ejecutar `npm run dev` desde Node `16.x` y tratarlo como baseline reproducible.
2. No arrancar limpieza de componentes o rutas sin pasar primero por FE-2B; el discovery ya confirmo que `Role.vue`, `ReporteSpei.vue` y `ReporteCargosRecurrentes.vue` son funcionalidad viva.
3. No cambiar `principal.blade.php`, runtime Vue o nombres de salida antes de definir la estrategia FE-2.

## Conclusion

El precheck formal del frente legacy congelado queda en `GO con salvedades`: la lane Node `8.17.0` / npm `6.13.4` ya fue validada con reinstall limpio y build reproducible, pero persisten gaps legacy de wiring y exportacion que FE-2 Discovery ya confirmo como funcionalidad viva para atenderse en FE-2B.
