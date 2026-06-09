# Prompts sugeridos vigentes

Ultima actualizacion: 2026-06-08

## Estado rector

La plataforma ya funciona en produccion por Docker. A partir del 2026-06-03 no se deben crear carpetas nuevas para fases o cambios. Todo trabajo debe realizarse sobre:

`C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia`

Rama vigente: `main`.

Addendum 2026-06-08: el propietario confirmo que los servicios Pagadetodo ya fueron probados exitosamente desde servidor en sandbox y productivo. No intentar llamadas reales Pagadetodo desde local; el proveedor restringe por IP address de origen. Local debe usar `PAGADETODO_MOCK=true`.

## 1. Prompt recomendado exacto - siguiente tarea tecnica general

```text
Trabaja sobre el proyecto Centro de Cobros en la carpeta actual:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

No crees una nueva carpeta ni una nueva copia de fase. Usa la rama `main` del repositorio actual.

Objetivo:
- analizar la solicitud contra el codigo y la documentacion vigente;
- localizar rutas, controladores, componentes Vue, tablas y pruebas relacionadas;
- implementar solo los cambios necesarios y mantener intacta la funcionalidad no relacionada;
- no tocar `principal.blade.php` ni el contrato publico de assets salvo necesidad tecnica justificada;
- no ejecutar migraciones sobre DB productiva;
- no activar ni duplicar scheduler;
- no usar credenciales productivas de Pagadetodo para pruebas;
- actualizar la documentacion general o del modulo si cambia comportamiento, operacion, rutas o riesgos;
- ejecutar validaciones proporcionales al cambio;
- dejar un resumen claro de archivos modificados, pruebas ejecutadas y pendientes.

Restricciones:
1. No cambiar funcionalidad de negocio fuera del alcance solicitado.
2. No publicar secretos ni archivos locales.
3. No versionar assets compilados.
4. No crear carpetas `phase*` nuevas.
5. No modificar Docker productivo sin instruccion explicita y sin conocer el compose real del servidor.
```

## 2. Prompt recomendado exacto - documentacion y diagnostico

```text
Trabaja sobre:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

No crees una nueva carpeta. Analiza el codigo y la documentacion actual para actualizar la documentacion del proyecto y de los modulos.

Objetivo:
- revisar `docs/PROJECT_OPERATING_MODEL.md`, `docs/README.md`, `docs/AI_AGENT_GUIDE.md`, `docs/ROUTES_AND_FLOW.md`, `docs/ARCHITECTURE.md`, `docs/ENVIRONMENT_AND_OPERATION.md`, `docs/SECURITY_AND_RISKS.md`, `docs/INTEGRATIONS.md` y `docs/MODULES/*.md`;
- verificar contra `route:list`, controladores, componentes Vue, `composer.json`, `package.json`, `.gitignore`, workflow GitHub y configuracion;
- corregir informacion obsoleta;
- documentar estado por modulo, rutas, roles, pruebas recomendadas, riesgos y pendientes;
- no cambiar codigo de negocio;
- ejecutar `git diff --check docs` al terminar.

Entrega:
- resumen de documentos actualizados;
- inconsistencias corregidas;
- validaciones ejecutadas;
- pendientes recomendados.
```

## 3. Prompt recomendado exacto - UX/UI etapa 1

```text
Trabaja sobre:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

No crees una nueva carpeta. Ejecuta la Etapa 1 del plan UX/UI documentado en `docs/UX_UI_AUDIT_AND_WORK_PLAN_2026-06-04.md`.

Objetivo:
- corregir los warnings Mixed Content observados en produccion al entrar a `/main`;
- confirmar si la causa esta en `APP_URL`, proxy Docker, headers `X-Forwarded-*`, `TrustProxies` o generacion de `route('logout')`;
- aplicar el cambio minimo seguro;
- validar login/logout y consola limpia en browser real;
- no cambiar funcionalidad de negocio, rutas publicas ni componentes no relacionados;
- actualizar el control de avances del documento UX/UI con evidencia y validaciones ejecutadas.

Restricciones:
1. No ejecutar migraciones.
2. No tocar credenciales productivas.
3. No modificar scheduler.
4. No tocar `principal.blade.php` salvo necesidad tecnica justificada y verificada.
5. No versionar assets compilados.
```

## 4. Prompt recomendado exacto - evidencia Pagadetodo servidor/IP autorizado

Usar solo cuando se trabaje desde servidor/IP autorizado o cuando se documente evidencia ya obtenida. No usar para llamadas reales desde ambiente local.

```text
Trabaja sobre:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

No crees una nueva carpeta. Documenta y valida la evidencia de Pagadetodo ejecutada desde servidor/IP autorizado.

Objetivo:
- no intentar llamadas reales desde ambiente local porque Pagadetodo restringe por IP de origen;
- usar `PAGADETODO_MOCK=true` para pruebas locales automatizadas;
- conservar o solicitar evidencia sanitizada de las pruebas servidor sandbox/productivo ya exitosas;
- mantener scheduler deshabilitado salvo instruccion explicita;
- comparar payload y respuesta real contra la matriz mock de Fase 34 para GenerarLigaPago, GenerarLigaDomiciliacion, GenerarSpei, GenerarLigaLector, CargoDomiciliacion, CancelarDomiciliacion y Service/*;
- registrar evidencia request/response sanitizada sin secretos;
- ajustar solo adaptadores internos si el contrato real difiere;
- agregar pruebas de contrato con fixtures sanitizados;
- actualizar documentacion y dictamen de release.

Restricciones:
1. No usar credenciales productivas fuera del servidor autorizado.
2. No cambiar rutas publicas sin evidencia.
3. No cambiar payloads externos sin prueba.
4. No ejecutar migraciones.
```

## 5. Prompt recomendado exacto - hardening npm

```text
Trabaja sobre:
C:\temp\centrodecobros_phase34_validacion_pagadetodo_webhooks_idempotencia

No crees una nueva carpeta. Abre un carril controlado para remediar `npm audit` completo del proyecto Centro de Cobros.

Objetivo:
- clasificar vulnerabilidades por runtime vs dev/tooling;
- mantener Vue 3, Vite, contrato de assets y lane `plantilla.*`;
- proponer e implementar upgrades seguros por lotes pequenos;
- ejecutar `npm ci`, `npm run production`, `npm audit --omit=dev --audit-level=low`, y smoke browser si hay cambios visibles;
- actualizar documentacion de dependencias y riesgos.

Restricciones:
1. No cambiar rutas ni contratos de negocio.
2. No tocar `principal.blade.php` salvo necesidad minima justificada.
3. No mezclar cambios de Pagadetodo ni scheduler.
4. No versionar assets compilados.
```
