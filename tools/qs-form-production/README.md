# Fuentes productivas del formulario QS

Este directorio contiene cambios que deben incorporarse al proyecto Apps Script
`QS - Formulario de Reservas - Qamiluna`.

## Propiedad canonica

El proyecto Apps Script productivo debe abrirse, editarse y desplegarse desde la
cuenta del estudio `qamilunaservices@qamilunastudio.com`.

Deployment productivo canonico:

`https://script.google.com/macros/s/AKfycbwiV_i0haP4lAQ2ZDrCoR28IWfUODpYlimNswDVBs2DNNnBSt2XPq38aOWhnWQsb4Zyiw/exec`

No usar deployments creados desde cuentas personales como fuente productiva. Si
existe una implementacion equivalente fuera de la cuenta del estudio, debe
deshabilitarse o eliminarse despues de confirmar que el despliegue canonico
contiene las mismas funcionalidades.

## Archivos

- `PendingBookings.gs`: consulta y cancelacion auditada de reservas pendientes.
- `pending-bookings-fragment.html`: seccion visual para insertar antes de `</body>` en `Cotizador.html`.
- `service-selector-fragment.html`: parche de UI para restaurar filtro por tipo,
  selector `Todos`, servicios activos sin precio visible y formato CLP en traslado.
- `gas-src/`: fuente clonada con `clasp` desde el proyecto Apps Script productivo
  de la cuenta del estudio. Incluye `Código.js`, `Cotizador.html`,
  `PendingBookings.js` y `appsscript.json`.

El listado operativo muestra solamente reservas con fecha futura que no hayan sido
canceladas. El historial permanece disponible en las hojas, pero no se mezcla con
las acciones del formulario.

## Semantica de cancelacion

La accion no elimina filas. Marca Agenda y Bitacora como canceladas, conserva las
referencias de auditoria y elimina el evento de Calendar. QS Manager V2 refleja el
cambio al ejecutar `Sincronizar todo`.

## Invitados de Calendar

La Web App productiva usa el calendario de la cuenta del estudio y agrega como
invitadas solo a las profesionales seleccionadas en el formulario:

- `Mou`: `mymarchantc@gmail.com`
- `Paz`: `vi.espectral@gmail.com`
- `Cami`: `cami.verdejo@gmail.com`

Las selecciones combinadas agregan todos los correos correspondientes sin
duplicados. `inviteAssignedStaff` debe permanecer habilitado en `Codigo.gs`.

## Estado de despliegue

- Version 5 (2026-07-15) publicada desde la cuenta del estudio. Incluye el fix de
  cancelacion en cadena: si Agenda no tiene "ID Evento", se usa el "ID Calendar" de
  Bitacora como respaldo y se prueba el ID con y sin sufijo `@google.com`.
- Solo la cuenta del estudio (propietaria: Camila Villalobos) puede administrar las
  implementaciones; las cuentas no propietarias reciben "No tienes permiso".
- Validado con QA E2E 02: la cancelacion elimino el evento de Calendar en ambas
  cuentas. QA E2E 01 quedo cancelado y sin evento remanente.

- Version 6 (2026-07-15): acceso ampliado a cualquier persona con el enlace e
  `inviteAssignedStaff: true` (invitaciones reales a profesionales) para pruebas del team.
- Version 7 (2026-07-16): fix responsive. Causa raiz: el wrapper de Apps Script no
  emitia meta viewport, por lo que los media queries de 820px/720px nunca se activaban
  en moviles reales. Fix: `.addMetaTag('viewport', 'width=device-width, initial-scale=1')`
  en `doGet` de `Codigo.gs`, mas un bloque `@media(max-width:480px)` en `Cotizador.html`
  (overflow-x guard, stepper con wrap, inputs a 16px para evitar zoom de iOS).
- Version 16 (2026-07-26): selector de servicios restaurado con `Todos`,
  servicios activos sin monto visible, traslado en formato CLP y sin cuenta
  personal como invitado por defecto.
- Version 17 (2026-07-26): cache de catalogo/servicio/traslado, cache breve de
  agendamientos pendientes, carga inicial de Agenda 2026 y seccion condicional
  `¿Requiere prueba?`.
- Version 18 (2026-07-27): traslado consultable siempre, detalle dinamico de
  multiples servicios por reserva y descuento automatico del 50% sobre traslado
  cuando la reserva incluye mas de un servicio. El guardado valida cada servicio
  contra `Seguimiento Contable > Servicios`, suma los valores en una sola fila de
  Agenda y deja el desglose en observaciones.
- Version 19 (2026-07-27): el checkbox `Agregar traslado a la reserva` vuelve a
  ser el unico control que habilita/deshabilita la comuna y el calculo de traslado.
  Para consultar un traslado preliminar, la usuaria debe marcarlo explicitamente.
- Version 20 (2026-07-27): la lista de agendamientos pendientes destaca el `Abono`
  real de la reserva y deja `Total` y `Saldo` como detalle secundario. La cache de
  pendientes cambia a `pending_bookings_v3` para evitar respuestas con el esquema
  anterior.
- Version 21 (2026-07-27): mejora UX del armado multi-servicio separando el
  servicio en preparacion del listado que realmente se guardara en Agenda. El boton
  de agregado ahora cambia su texto y ayuda segun el estado del servicio, con layout
  mobile en una sola columna. Se retira la barra flotante mobile de cotizacion para
  que no tape campos ni acciones durante el flujo.

Antes de desplegar, el contenido completo de `Codigo.gs` y `Cotizador.html` debe
versionarse en este directorio. No se debe publicar una implementacion nueva si el
proyecto local no representa exactamente el codigo desplegado.
