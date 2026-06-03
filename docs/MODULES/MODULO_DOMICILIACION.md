# Módulo: Domiciliación y cargos recurrentes

## Propósito
Gestionar generación de liga de domiciliación, ejecución de cargos recurrentes y cancelaciones.

## Archivos clave
- `app/Http/Controllers/TransaccionController.php` (generación/cancelación)
- `app/Http/Controllers/TransaccionDomController.php` (cargos recurrentes)
- `app/TransaccionDom.php`
- `resources/assets/js/components/TransaccionDom.vue`
- `resources/assets/js/components/ReporteLigasDom.vue`
- `resources/assets/js/components/ReporteCargosRecurrentes.vue`

## Flujo operativo
- Alta de liga domiciliación -> `transacciones` tipo domiciliación.
- Cargo recurrente -> `transaccionesDom`.
- Scheduler diario ejecuta `ejecutarCron`.
- Se notifica callback a URL cliente (`ligaRecurrente`) cuando aplica.

## Tablas involucradas
- `transacciones`, `transaccionesDom`
- `cancelacionesDom`
- `respuestas` (en parte del flujo)

## Riesgos
- Scheduler invoca métodos de controlador (acoplamiento infra-dominio).
- Reglas de negocio temporal/estado embebidas sin capa de dominio.

## Importación masiva de ligas de domiciliación
- Desde `Generación de Ligas` (`tipo=2`) se puede importar Excel con las columnas:
  `Cliente`, `Forma de pago`, `Descripción`, `Monto`, `Fecha Expiración`, `Referencia`, `Frecuencia`.
- `Frecuencia` acepta número o texto del catálogo vigente (`Semanal`, `Mensual`, `Bimestral`, `Semestral`, `Anual`).
- El proceso es secuencial, muestra barra de progreso, soporta cancelación y genera bitácora descargable en formato `xlsx`.
