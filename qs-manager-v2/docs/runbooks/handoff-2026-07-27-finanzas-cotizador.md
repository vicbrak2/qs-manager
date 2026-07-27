# Handoff 2026-07-27: Finanzas V2 y Cotizador QS

## Estado operativo

- App local V2: `http://localhost:8080`.
- Firebase publico del cotizador: `https://qs-manager-reservas.web.app`.
- GAS canonico del estudio: `https://script.google.com/macros/s/AKfycbwiV_i0haP4lAQ2ZDrCoR28IWfUODpYlimNswDVBs2DNNnBSt2XPq38aOWhnWQsb4Zyiw/exec`.
- Proyecto Apps Script canonico: `1EgdgpeE5b0bsCLf4dLLZEi4SL_LG51IhwczhMqxnYA_kcQFKjcsS-V5K`.
- Cuenta operativa: `qamilunaservices@qamilunastudio.com`.

## Finanzas V2

- La pestaña Finanzas abre por defecto en el mes actual y usa el calculo mensual como modo principal.
- El calculo por rango sigue disponible mediante checkbox y deshabilita los controles mensuales cuando se usa.
- `Resultado disponible` queda en `$0` cuando los ingresos realizados no cubren gastos/costos, para evitar saldos negativos confusos.
- `Gastos fijos` tiene detalle clickeable con conceptos, categoria, periodicidad y monto.
- `Retenido como reserva` suma abonos abiertos desde Agenda, Bitacora y Caja con deduplicacion.
- Correccion puntual: los pagos fallback desde Bitacora ahora usan `Fecha Abono` de Agenda cuando existe la referencia. Caso Cecilia:
  - Agenda `Agosto!2`: servicio `21/08/2026`, abono `$60.000`, fecha abono `26/07/2026`.
  - Finanzas proyecta ese `customer_payment` en `2026-07-26`, no en agosto.

## Cotizador y agendamiento

- El formulario productivo vive en `tools/qs-form-production/gas-src`.
- `Cotizador.html` mantiene cache inicial del catalogo y carga agendamientos futuros al abrir.
- `PendingBookings.js` usa cache breve para evitar lecturas repetidas de Agenda 2026 y limpia cache al cancelar.
- La seccion `¿Requiere prueba?` es condicional:
  - fecha, hora y lugar de prueba;
  - lugar: `En estudio`, `A domicilio`, `Pendiente de definir`;
  - si es a domicilio, permite usar direccion del servicio, ingresar nueva direccion o dejar pendiente.
- El selector de servicios conserva:
  - tipo con opcion `Todos`;
  - campo `Servicio` desactivado mientras no exista tipo seleccionado;
  - solo servicios activos desde `Seguimiento Contable > Servicios`;
  - nombres sin precio visible en el dropdown;
  - traslado en formato CLP.

## Cambio nuevo multi-servicio

- El traslado se puede consultar siempre, incluso antes de agregar servicios o desbloquear datos de clienta.
- La reserva puede incluir mas de un servicio, incluso de distinto tipo.
- El usuario agrega servicios al detalle; el servicio en edicion queda separado del detalle final.
- El detalle de cotizacion recalcula en vivo:
  - servicios agregados;
  - valor por servicio;
  - ahorro de promociones por servicio;
  - traslado incluido o solo consultado;
  - descuento automatico del 50% en traslado cuando hay mas de un servicio;
  - total, reserva sugerida, abono y saldo.
- El GAS valida cada servicio contra la hoja maestra antes de guardar.
- El guardado registra una sola fila en Agenda 2026 con:
  - `Servicio` como nombres concatenados;
  - `Valor Servicio` como suma de servicios;
  - `Traslado` como tarifa final cobrada despues del descuento;
  - observaciones con desglose de servicios, descuentos y traslado.

## Validaciones realizadas

- `node --check` sobre `Código.js`, `PendingBookings.js` y scripts embebidos de `Cotizador.html`.
- `vendor/bin/phpunit tests/Integration/Finance/RebuildFinanceProjectionTest.php`.
- `php -l` sobre archivos PHP tocados en Finanzas.
- Rebuild local de proyeccion financiera con sync `52`.
- Endpoint `GET /api/v1/finance/dashboard?from=2026-07-01&to=2026-07-31&basis=cash_estimated` devuelve reconciliacion en cero.

## Pendientes sugeridos

- Ejecutar una reserva real controlada de QA multi-servicio en Agenda 2026 y luego cancelarla desde el panel de pendientes.
- Confirmar visualmente mobile despues de cada despliegue GAS porque Apps Script envuelve el HTML en su propio contenedor.
