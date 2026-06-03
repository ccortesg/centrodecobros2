# Registro de Decisiones Tecnicas

Ultima actualizacion: 2026-03-25

| ID | Fecha | Decision | Justificacion | Evidencia / impacto |
| --- | --- | --- | --- | --- |
| D-72 | 2026-03-24 | Mantener Fase 29 como cierre de readiness y no como nueva migracion estructural | La migracion ya habia quedado cerrada dentro del alcance actual | La baseline consolidada se preserva y la salida queda en `GO con condiciones previas` |
| D-73 | 2026-03-24 | Mantener el contrato publico `app.js` / `plantilla.js` / `plantilla.css` / `guest-public.js` como restriccion de release | Cambiar nombres o ownership en esta etapa reabriria una migracion estructural | Fase 29 cierra sin tocar contratos externos |
| D-74 | 2026-03-24 | Mantener `principal.blade.php` intacto salvo necesidad tecnica minima estrictamente justificada | Es el punto mas sensible del shell autenticado | La baseline Fase 29 queda estable |
| D-75 | 2026-03-25 | Ejecutar Fase 30 solo en `C:\temp\centrodecobros_phase30_release_candidate`, clonada desde `C:\temp\centrodecobros_phase29_release_readiness` | El cierre de release candidate no puede contaminar la baseline anterior | La copia aislada se creo antes de cualquier modificacion |
| D-76 | 2026-03-25 | Limitar Fase 30 a cierre de condiciones previas, validacion integral, documentacion operativa y dictamen unico de liberacion | El usuario prohibio reabrir migracion estructural, refactor mayor o cambio de contratos | El trabajo queda acotado a validacion, patch puntual seguro y documentacion |
| D-77 | 2026-03-25 | Cerrar el advisory `CVE-2026-33347` actualizando solo `league/commonmark 2.8.1 -> 2.8.2` | `laravel/framework 12.54.1` permite `^2.8.1` y el patch no reabre arquitectura ni negocio | `composer audit` queda limpio y `php vendor/bin/phpunit` sigue en verde |
| D-78 | 2026-03-25 | No tocar integraciones hardcoded ni mover toda la lane `plantilla.*` a Vite en esta fase | Son cambios de riesgo alto y fuera del alcance seguro del release candidate | Se dejan como deuda residual explicita y condicion operativa de liberacion |
| D-79 | 2026-03-25 | Aceptar como prerequisito operativo la disponibilidad temporal de MySQL local para repetir pruebas autenticadas y backend | `phpunit` y parte de la validacion browser dependen del entorno local real | El prerequisito se documenta en checklist y runbook |
| D-80 | 2026-03-25 | Emitir dictamen final `GO con condiciones adicionales`, no `GO` pleno ni `NO-GO` | La baseline esta estable y el advisory critico se cerro, pero siguen abiertas integraciones hardcoded y deuda operativa residual | El proyecto puede liberarse solo con aceptacion explicita de condiciones y control de despliegue |
