# Guía para agentes de IA

## Cómo leer este proyecto (orden recomendado)
1. `docs/DIAGNOSTIC_REPORT.md`
2. `docs/ARCHITECTURE.md`
3. `docs/DATABASE_MODEL.md`
4. `docs/DATABASE_MIGRATIONS_VS_DUMP.md`
5. `routes/web.php` y `routes/api.php`
6. `TransaccionController`, `TransaccionDomController`, `RespuestaController`

## Fuentes de verdad recomendadas
- Esquema real: `database/centrodecobros.sql`.
- Reglas de negocio ejecutables: controladores (principalmente transacciones/respuestas).
- Comportamiento UI: componentes Vue.

## Puntos de cuidado
- No asumir que migration == schema real.
- Verificar `productivo`, `tipo`, `status`, `enviada` antes de inferir flujos.
- Revisar siempre integraciones externas y callbacks por usuario.

## Reglas prácticas para tareas futuras
- Antes de modificar, localizar:
  - endpoint,
  - componente Vue que lo consume,
  - tabla(s) afectadas,
  - validaciones y efectos colaterales (callbacks, scheduler, reportes).
- Documentar explícitamente si una relación es inferida.
- Si hay discrepancias entre capas, priorizar dump + uso real en código.

## Checklist previo a cambios
- [ ] Ruta localizada (web/api).
- [ ] Controlador/método identificado.
- [ ] Modelo y columnas verificadas en dump.
- [ ] Validaciones y middleware revisados.
- [ ] Impacto en reportes/exportaciones evaluado.
- [ ] Impacto en integraciones externas evaluado.
- [ ] Plan de pruebas (mínimo endpoint + flujo UI).
