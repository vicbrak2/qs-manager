# Fuentes productivas del formulario QS

Este directorio contiene cambios que deben incorporarse al proyecto Apps Script
`QS - Formulario de Reservas - Qamiluna`.

## Archivos

- `PendingBookings.gs`: consulta y cancelacion auditada de reservas pendientes.
- `pending-bookings-fragment.html`: seccion visual para insertar antes de `</body>` en `Cotizador.html`.

El listado operativo muestra solamente reservas con fecha futura que no hayan sido
canceladas. El historial permanece disponible en las hojas, pero no se mezcla con
las acciones del formulario.

## Semantica de cancelacion

La accion no elimina filas. Marca Agenda y Bitacora como canceladas, conserva las
referencias de auditoria y elimina el evento de Calendar. QS Manager V2 refleja el
cambio al ejecutar `Sincronizar todo`.

## Invitados de Calendar

La Web App productiva conserva `vic.martinez777@gmail.com` como invitado de
coordinacion y agrega a las profesionales seleccionadas en el formulario:

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
  implementaciones; la cuenta personal recibe "No tienes permiso".
- Validado con QA E2E 02: la cancelacion elimino el evento de Calendar en ambas
  cuentas. QA E2E 01 quedo cancelado y sin evento remanente.

- Version 6 (2026-07-15): acceso ampliado a cualquier persona con el enlace e
  `inviteAssignedStaff: true` (invitaciones reales a profesionales) para pruebas del team.
- Version 7 (2026-07-16): fix responsive. Causa raiz: el wrapper de Apps Script no
  emitia meta viewport, por lo que los media queries de 820px/720px nunca se activaban
  en moviles reales. Fix: `.addMetaTag('viewport', 'width=device-width, initial-scale=1')`
  en `doGet` de `Codigo.gs`, mas un bloque `@media(max-width:480px)` en `Cotizador.html`
  (overflow-x guard, stepper con wrap, inputs a 16px para evitar zoom de iOS).
- Pendiente: `Codigo.gs` y `Cotizador.html` productivos aun no estan versionados en
  este directorio.

Antes de desplegar, el contenido completo de `Codigo.gs` y `Cotizador.html` debe
versionarse en este directorio. No se debe publicar una implementacion nueva si el
proyecto local no representa exactamente el codigo desplegado.
