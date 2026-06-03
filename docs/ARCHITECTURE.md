# Arquitectura real del sistema

## 1) Vista general
- **Patrón predominante**: Laravel MVC clásico con frontend Vue 2 montado en `#app` (single-page parcial por menú dinámico).
- **Backend**: Controladores con lógica de negocio + integraciones HTTP externas + persistencia Eloquent/Query Builder.
- **Frontend**: componentes `.vue` que consumen endpoints web (AJAX) y API (`routes/api.php`) según módulo.
- **Base de datos**: MySQL (dump real en `database/centrodecobros.sql`).

## 2) Capas (reales, no ideales)
1. **Presentación**
   - Blade layout + sidebars + vistas públicas (`register/url`) y shell autenticado.
   - Vue Components por módulo (Transacción, Respuesta, SPEI, Clientes, etc.).
2. **Aplicación**
   - Controladores HTTP gestionan validación, reglas de negocio, integración y persistencia.
   - Casi no hay capa de servicios separada.
3. **Persistencia**
   - Eloquent models por tabla principal.
   - Uso mixto de Query Builder para reportes/joins complejos.
4. **Integración externa**
   - Guzzle para pasarela de pagos/servicios externos.
   - Pusher/Echo para notificaciones.
   - TeleSign queda solo como residuo historico; el flujo publico verify/SMS fue retirado en Fase 21.

## 3) Módulos funcionales detectados
- Catálogos: estados, ciudades, clientes.
- Seguridad/Acceso: login por `usuario`, roles, usuarios.
- Cobros:
  - Ligas de pago.
  - Domiciliación (generación + cargos recurrentes + cancelaciones).
  - SPEI (generación/consulta/pago/cancelación).
  - Pago en caja / pago con terminal (tipos adicionales de transacción).
- Respuestas de pasarela y notificación a sistemas cliente.
- Archivos adjuntos por persona/cliente.
- Reportería y exportaciones Excel.

## 4) Dependencias y acoplamientos críticos
- **Controladores gordos**:
  - `TransaccionController` (~3k líneas).
  - `TransaccionDomController` (~885 líneas).
- Fuerte acoplamiento entre:
  - tablas de negocio (`transacciones`, `respuestas`, `transaccionesDom`) y
  - parseo de payload externo.
- Acoplamiento frontend-backend por nombres exactos de campos legacy (`User`, `Password`, `BusinessID`, etc.).
- Acoplamiento con servicios externos por URLs hardcodeadas.

## 5) Patrones implícitos / inconsistentes
- **Repository/Service pattern**: no implementado de forma consistente.
- **Domain model rico**: no; reglas están en controladores.
- **Eventos/colas**: broadcasting sí, pero proceso crítico se ejecuta en scheduler llamando métodos de controlador (acoplamiento no ideal).

## 6) Flujo principal backend/frontend
1. Usuario autenticado entra a `main`.
2. Menú Vue cambia `menu` y renderiza componente.
3. Componente llama endpoints CRUD/reportes.
4. Controlador valida, arma payload, invoca pasarela, persiste transacción/respuesta.
5. Notificaciones se consultan vía endpoint y broadcasting privado por Pusher.

## 7) Diagnóstico arquitectónico
- Arquitectura **funcional pero frágil** por deuda técnica alta.
- Alto riesgo de regresión por lógica centralizada en pocos archivos grandes.
- Incongruencia fuerte entre migraciones históricas y esquema real actual.
- Integraciones externas directas sin una capa anti-corrupción/adapter formal.
