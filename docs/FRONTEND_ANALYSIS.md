# Análisis frontend

## Estructura
- Entrada principal: `resources/assets/js/app.js`.
- Arquitectura por componentes Vue 2 (`resources/assets/js/components/*.vue`).
- Shell visual server-side con Blade (`principal.blade.php`, sidebars, contenido).

## Mezcla tecnológica
- Vue 2 para vistas de negocio autenticadas.
- Blade para composición/layout y vistas públicas.
- jQuery para scripts puntuales (OTP y dependencias legacy).
- Plantilla estática combinada por Mix (`plantilla.js`, `plantilla.css`).

## Dependencias fuertes del backend
- Endpoints hardcodeados en cada componente (`/transaccion/...`, `/cliente/...`, etc.).
- Mapeo de menú por enteros (`menu=1`, `menu=2`...) acoplado a componentes.
- Formatos de payload y nombres de campos dependen de contratos backend legacy.

## Formularios críticos
- Alta de transacciones (normal, domiciliación, SPEI, lector/caja).
- Captura de cliente + carga de archivos.
- Verificación OTP pública.

## Flujos AJAX
- Axios para CRUD/reportes/export.
- Descargas por `responseType: 'blob'` en exportables.
- Consulta de notificaciones y suscripción Echo en `created()`.

## Riesgos detectados
- Alto acoplamiento rutas-campos-componentes.
- Convivencia de Vue + jQuery aumenta complejidad de mantenimiento.
- Sin tipado/contratos frontend formalizados.
