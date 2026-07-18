# QS Manager V2 - Flujo E2E de reservas y trabajo pendiente

**Fecha de validacion:** 2026-07-15  
**Entorno:** Formulario GAS productivo controlado, Agenda 2026, Google Calendar, Bitacora QS y QS Manager V2 local  
**Estado general:** Flujo operativo con tres hallazgos pendientes antes de considerarlo cerrado.

## 1. Objetivo de la validacion

Comprobar que una reserva creada desde el Cotizador y Agenda QS recorra de extremo a extremo el siguiente circuito:

```text
Cotizador GAS
  -> Agenda 2026 (hoja mensual)
  -> Google Calendar e invitaciones
  -> Bitacora QS - Servicios
  -> sincronizacion total de V2
  -> PostgreSQL y UI de QS Manager V2
```

La prueba utilizo registros identificables como `QA E2E 01` a `QA E2E 06`. Son datos de prueba y deben eliminarse de forma coordinada cuando finalicen las correcciones y la prueba de regresion.

## 2. Flujo funcional completo

### Paso 1. Cotizacion en el formulario GAS

El usuario selecciona tipo de servicio, servicio y cantidad. El formulario consulta dinamicamente las tarifas vigentes de Sheets. Cuando corresponde, agrega traslado por comuna o una promocion porcentual/personalizada. Antes de solicitar datos personales debe poder mostrar:

- precio maestro del servicio;
- cantidad;
- descuento o precio promocional;
- traslado;
- total;
- reserva sugerida o abono ingresado;
- saldo pendiente.

La cotizacion no debe escribir en Sheets ni crear eventos. La persistencia comienza unicamente al presionar `Agendar reserva` con los campos obligatorios validos.

### Paso 2. Registro en Agenda 2026

El GAS determina la hoja mensual a partir de la fecha y agrega una fila individual. La fila debe conservar, sin reinterpretaciones:

- encargada;
- fecha y hora local de Chile;
- servicio y cantidad;
- clienta y telefono;
- direccion, comuna y traslado;
- abono y fecha de abono/cobro;
- valor del servicio, total y saldo;
- estado de pago.

Los talleres tambien deben registrarse como una fila normal y con la cantidad real de participantes. No debe usarse una estructura agrupada distinta de las demas reservas.

### Paso 3. Evento en Google Calendar

Despues de guardar la fila, el GAS crea el evento en el calendario cuyo propietario es `qamilunaservices@qamilunastudio.com`. El ID de Calendar se escribe en Bitacora para mantener trazabilidad e idempotencia.

En la fase actual, el unico invitado permitido es `vic.martinez777@gmail.com`. Las profesionales no deben recibir invitaciones ni correos hasta habilitar explicitamente ese comportamiento en una fase posterior.

### Paso 4. Propagacion a Bitacora QS

El proceso homologa la fila de Agenda y crea una entrada en `Bitacora QS - Servicios` con:

- ID `QS-nnn`;
- referencia exacta `Agenda: <Mes>!<fila>`;
- ID de Calendar;
- servicio homologado y servicio original;
- valores de servicio, traslado, abono, total y saldo;
- estados contractual, de pago y de servicio.

Bitacora QS es el registro maestro consolidado para la importacion de reservas de V2.

### Paso 5. Sincronizacion local de V2

El endpoint `POST /api/v1/sync/sheets/import` encola una ejecucion. El worker importa las fuentes de solo lectura, incluida Agenda y Bitacora, y actualiza PostgreSQL de forma idempotente. La UI consulta el estado hasta `completed`, `partial` o `failed`.

La ejecucion verificada fue `run_id=21`:

- estado: `completed`;
- fuentes completadas: 19 de 19;
- fuentes fallidas: 0;
- filas importadas: 291;
- worker: `worker-1-dec8eedc`;
- reservas QA presentes en `qs_bookings`: 6 de 6.

### Paso 6. Gestion de agendamientos pendientes

El cotizador productivo debe incluir una lista operativa que consulte Agenda 2026 y
Bitacora QS. Solo muestra reservas futuras o servicios cuyo estado aun no sea
`Terminado`, `Finalizado`, `Realizado` o `Completado`.

La cancelacion desde el formulario debe:

1. volver a validar el registro bajo un lock para evitar cancelar una fila que cambio;
2. marcar Agenda como `CANCELADO - FORMULARIO` sin eliminar la fila;
3. marcar Bitacora y el contrato como `Cancelado` y agregar motivo/fecha a Observaciones;
4. eliminar el evento de Calendar conservando su ID para auditoria;
5. informar que V2 reflejara el cambio en la siguiente sincronizacion manual.

El cliente recibe un token firmado y no puede elegir libremente una hoja o numero de
fila. Toda cancelacion exige un motivo y debe ser idempotente.

## 3. Evidencia de los seis escenarios

| Caso | Cobertura | Agenda 2026 | Bitacora QS | V2 |
| --- | --- | --- | --- | --- |
| QA-E2E-01 | Servicio regular, sin traslado, abono 50% | Diciembre!4 | QS-111, fila 109 | booking 5933 |
| QA-E2E-02 | Servicio regular con traslado Providencia | Diciembre!5 | QS-112, fila 110 | booking 5934 |
| QA-E2E-03 | Novia con promocion de 20% | Diciembre!6 | QS-113, fila 111 | booking 5935 |
| QA-E2E-04 | Precio personalizado y reserva sin abono | Diciembre!7 | QS-114, fila 112 | booking 5936 |
| QA-E2E-05 | Taller para dos participantes | Diciembre!8 | QS-115, fila 113 | booking 5937 |
| QA-E2E-06 | Glitter Bar con traslado Providencia | Diciembre!9 | QS-116, fila 114 | booking 5938 |

Cada registro genero un `calendar_event_id`, lo que confirma que la creacion del evento se ejecuto. La entrega del correo no pudo comprobarse desde Gmail porque el conector disponible no tiene los scopes de lectura necesarios.

## 4. Trabajo pendiente priorizado

### P0. Verificar la politica de invitados antes de publicar

**Estado corregido:** el proyecto productivo `QS - Formulario de Reservas - Qamiluna`
ya contiene `inviteAssignedStaff: false` y configura
`defaultCalendarGuest: 'vic.martinez777@gmail.com'`. La alerta anterior provenia de
una copia antigua del script vinculado a Agenda y no demuestra que el cotizador
productivo haya invitado a Cami.

**Cambio requerido:** mantener esta configuracion y cubrirla con una prueba de
regresion. La copia antigua no debe volver a desplegarse sobre el proyecto productivo.

**Criterios de aceptacion:**

1. Un evento nuevo contiene como unico invitado a `vic.martinez777@gmail.com`.
2. Ninguna profesional recibe correo ni invitacion.
3. El organizador sigue siendo `qamilunaservices@qamilunastudio.com`.
4. Una prueba controlada confirma la recepcion del correo predeterminado.

### P0. Corregir el desfase horario Agenda -> V2

**Problema:** las horas importadas aparecen una hora mas tarde en V2. Ejemplo: `09:00` en Agenda se almaceno como `13:00:00+00` y se presenta como `10:00` en `America/Santiago`. Para diciembre de 2026, `09:00` local debe equivaler a `12:00:00+00`.

**Causa probable:** conversion con offset fijo `-04:00` o construccion de fecha fuera de la zona IANA. Chile cambia entre horario estandar y horario de verano; no se debe hardcodear el offset.

**Cambio requerido:** parsear fecha y hora con `DateTimeZone('America/Santiago')` y persistir el instante UTC resultante. La presentacion debe convertir nuevamente a `America/Santiago`.

**Criterios de aceptacion:**

1. Agenda `29/12/2026 09:00` se muestra como `29/12/2026 09:00` en V2.
2. Se agregan pruebas para una fecha de verano y otra de invierno.
3. Reimportar una fuente no desplaza nuevamente la hora.
4. Calendar, Agenda, Bitacora y V2 muestran la misma hora local.

### P1. Conservar cantidad real de talleres

**Problema:** QA-E2E-05 fue cotizado para dos participantes y totalizo `$90.000`, pero Agenda guardo `Cantidad = 1`.

**Cambio requerido:** transportar la cantidad seleccionada hasta la columna `Cantidad`, Bitacora y V2. El total debe calcularse como precio unitario por cantidad, salvo que el servicio sea un pack con precio cerrado y esa regla este declarada en el catalogo.

**Criterios de aceptacion:**

1. Taller con cantidad 2 guarda `Cantidad = 2`.
2. Bitacora y V2 conservan el mismo valor.
3. Precio unitario, total, abono y saldo permanecen reconciliados.
4. Existe una prueba separada para talleres por participante y packs de Glitter Bar.

### P1. Verificar entrega real del correo

**Problema:** Calendar creo seis IDs, pero no se pudo auditar el inbox por falta de scopes del conector Gmail.

**Accion requerida:** reautorizar Gmail en modo lectura o comprobar manualmente el inbox de `vic.martinez777@gmail.com` buscando los seis eventos. Registrar asunto, remitente, hora y event ID sin exponer contenido personal.

### P1. Limpieza coordinada de datos QA

No eliminar registros antes de corregir y repetir los casos afectados. Luego ejecutar la limpieza en este orden:

1. localizar por `QA E2E 01` a `QA E2E 06` y guardar evidencia final;
2. eliminar o cancelar los seis eventos por `calendar_event_id`;
3. eliminar las filas Diciembre!4:Diciembre!9 solo si no cambiaron de posicion o contenido;
4. eliminar las entradas QS-111 a QS-116 de Bitacora mediante el mecanismo oficial;
5. ejecutar sincronizacion total;
6. confirmar que V2 ya no contiene los seis bookings;
7. confirmar que no se afectaron registros reales.

La limpieza es destructiva y requiere autorizacion explicita inmediatamente antes de ejecutarla.

### P2. Automatizar la regresion E2E

Crear una suite que use datos aislados y cubra:

- cotizacion sin persistencia;
- servicio regular;
- traslado dinamico;
- promocion porcentual;
- precio personalizado;
- reserva sin abono y fecha de cobro;
- taller con cantidad mayor que uno;
- Glitter Bar con pack;
- destinatarios de Calendar;
- igualdad de fecha/hora en todas las capas;
- idempotencia al sincronizar dos veces;
- limpieza de datos de prueba.

La suite no debe enviar correos a profesionales. En automatizacion se debe usar un calendario y spreadsheet de QA o una bandera `QA_MODE` que limite destinatarios.

## 5. Orden recomendado de implementacion

1. Desactivar invitados de profesionales mediante configuracion.
2. Corregir la zona horaria con `America/Santiago`.
3. Corregir la cantidad de talleres y definir semantica de packs.
4. Agregar pruebas unitarias e integracion para los tres cambios.
5. Ejecutar una prueba controlada nueva con tres reservas minimas.
6. Verificar correo, Agenda, Calendar, Bitacora y V2.
7. Limpiar los registros QA con autorizacion explicita.

## 6. Definicion de cierre

El flujo se considera cerrado cuando:

- el unico invitado durante esta fase es el correo predeterminado;
- la fecha y hora son identicas en Agenda, Calendar, Bitacora y V2;
- la cantidad y los valores coinciden peso por peso;
- dos sincronizaciones consecutivas no crean duplicados;
- todas las pruebas automatizadas estan verdes;
- los datos QA han sido retirados sin afectar informacion real;
- la evidencia de ejecucion queda documentada con fecha, comandos y resultados.
