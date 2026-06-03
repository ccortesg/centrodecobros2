# Prompts sugeridos despues de preparacion GitHub/sandbox

Ultima actualizacion: 2026-06-02

## Estado rector

La Fase 34 cerro validacion local de webhooks e idempotencia sin usar credenciales productivas. La preparacion GitHub/sandbox inicializo repo local, amplio `.gitignore`, definio assets generados por CI/deploy y agrego workflow de validacion. El sandbox oficial Pagadetodo sigue bloqueado por falta de URL/credenciales no productivas.

Dictamen vigente:

`GO tecnico parcial fuerte para sandbox controlado; NO-GO para liberacion directa o cobro real`

Baseline vigente:

`C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

## 1. Prompt recomendado exacto - primer push y deploy sandbox

```text
Quiero ejecutar el primer push a GitHub privado y desplegar el sandbox paralelo del proyecto Centro de Cobros Fase 34 sin tocar la version productiva actual.

Usa como baseline:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

Objetivo:
- revisar `git status --short` y confirmar que no se publican secretos, dependencias, logs, SQLite, outputs, test-results, snippets accidentales ni assets compilados;
- crear el primer commit en la rama `phase34-sandbox-release`;
- conectar un repositorio GitHub privado;
- hacer push de la rama y crear el tag `sandbox-phase34-v1.0.0`;
- verificar que el workflow `.github/workflows/sandbox-release-validation.yml` quede verde;
- desplegar en `/var/www/centro-v12-sandbox` por vhost/subdominio separado con PHP 8.3;
- generar assets en deploy con `npm ci && npm run production`;
- configurar `.env` sandbox con sesiones/cache/logs aislados, scheduler deshabilitado y `PAGADETODO_MOCK=true`;
- ejecutar validaciones postdeploy y documentar evidencia.

Restricciones:
1. No cambiar funcionalidad de negocio.
2. No ejecutar migraciones sobre DB productiva.
3. No publicar secretos ni archivos locales.
4. No activar scheduler en la version paralela.
5. No usar credenciales productivas de Pagadetodo.
6. No versionar assets compilados; deben generarse en CI/deploy.
```

## 2. Prompt alterno - sandbox oficial Pagadetodo

Usar solo cuando ya existan credenciales y URL de sandbox no productivas.

```text
Quiero ejecutar la validacion oficial de sandbox Pagadetodo del proyecto Centro de Cobros.

Usa como baseline:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

Objetivo:
- usar exclusivamente credenciales y URL sandbox no productivas;
- no activar scheduler;
- comparar payload y respuesta real contra la matriz mock de Fase 34 para GenerarLigaPago, GenerarLigaDomiciliacion, GenerarSpei, GenerarLigaLector, CargoDomiciliacion, CancelarDomiciliacion y Service/*;
- registrar evidencia de request/response sanitizada sin secretos;
- ajustar solo adaptadores internos si el contrato real difiere;
- agregar pruebas de contrato con fixtures sanitizados;
- actualizar dictamen de release.

Restricciones:
1. No usar credenciales productivas.
2. No cambiar rutas publicas.
3. No cambiar payloads externos sin evidencia y prueba.
4. No ejecutar migraciones.
```

## 3. Prompt posterior - secretos e integraciones

```text
Quiero abrir un carril separado para externalizar secretos e integraciones hardcoded del proyecto Centro de Cobros.

Usa como origen:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

Objetivo:
- crear una copia aislada;
- inventariar credenciales y endpoints hardcoded Pagadetodo, Pusher, SMTP y callbacks;
- mover configuracion a env/config sin cambiar payloads externos;
- preparar estrategia de rotacion y rollback;
- validar con sandbox o mocks antes de cualquier despliegue.

Restricciones:
1. No cambiar contratos externos.
2. No mezclar upgrades de dependencias.
3. No usar credenciales productivas para pruebas automatizadas.
```

## 4. Prompt posterior - dependencias frontend

```text
Quiero abrir un carril separado para remediar npm audit del proyecto Centro de Cobros sin contaminar la estabilizacion funcional.

Usa como origen:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

Objetivo:
- crear una copia aislada;
- clasificar las 29 vulnerabilidades npm por runtime vs tooling;
- proponer upgrades seguros sin romper Vue 3, Vite, contrato de assets ni lane plantilla.*;
- ejecutar build production y smoke browser;
- documentar riesgos residuales.

Restricciones:
1. No cambiar rutas ni contratos de negocio.
2. No tocar principal.blade.php salvo necesidad minima justificada.
3. No mezclar externalizacion de secretos.
```
