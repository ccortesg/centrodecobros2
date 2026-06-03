# Integraciones externas

## 1) Pasarela de pagos Pagadetodo
### Endpoints detectados en código
- `GenerarLigaIndi`
- `GenerarLigaDomiciliacionIndi`
- `CancelarDomiciliacionIndi`
- `GenerarClabeIndi`
- `GenerarPagoLectorIndi`
- `CancelarReferenciaLectorIndi`
- `PagarDomiciliacionIndi`

### Puntos de integración
- `TransaccionController`
- `TransaccionDomController`

### Datos intercambiados (resumen)
- Solicitud: `User`, `Password`, `IntegrationID`, `BusinessID`, referencias, monto, token.
- Respuesta: `code`, `message`, `reference`, `url`, payload JSON completo.

### Riesgos
- Credenciales y endpoints hardcodeados.
- Contratos externos acoplados al controlador.

## 2) Callback a sistemas cliente
- URLs configuradas por usuario (`ligaPago`, `ligaRecurrente`).
- Envío post-procesamiento de respuesta aprobada.
- Riesgo de reintentos/control de idempotencia limitado.

## 3) OTP/SMS
- El flujo publico GET /verify/{id} + POST /verifySMS + POST /sendSMS fue retirado en Fase 21 al confirmarse sin callers vivos reales y con falla preexistente.
- El paquete `telesign/telesign` sigue instalado como residuo historico del proyecto; su limpieza de dependencia no formo parte de esta fase.

## 4) Realtime
- Pusher + Laravel Echo para notificaciones.
- Clave de app en frontend.
- Estado actual del workspace: polling HTTP validado, pero lane realtime websocket no validada end-to-end.
- `FE-H1-L4` quedo en `NO-GO documentado`; la siguiente accion pasa a `VAL-A1` con credenciales controladas y sandbox aislado.

## 5) Correo
- SMTP configurado para notificaciones.
- Clase `TransaccionValidada` presente.

## 6) Exportaciones/PDF
- Excel (`maatwebsite/excel`) usado en reportes.
- DomPDF instalado; uso parcial en vistas PDF legacy.
