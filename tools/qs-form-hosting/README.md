# QS Form Hosting

Frontend publico del Cotizador y Agenda QS, alojado en Firebase Hosting y conectado a Google Apps Script como backend.

## Proyecto Firebase

- Nombre: `QS Manager Reservas`
- Project ID: `qs-manager-reservas`
- Organizacion: `qamilunastudio.com`
- Analytics: desactivado

## Arquitectura

Firebase Hosting sirve una URL publica estable, recursos visuales y el contenedor de la aplicacion. Google Apps Script conserva el acceso a Google Sheets, Calendar y correo.

La migracion se ejecuta en dos etapas:

1. **Puente embebido:** Firebase aloja un `iframe` de pantalla completa con el formulario GAS V15. Esto elimina la navegacion directa que Google reescribe como `/u/1/` y mantiene `google.script.run` sin cambios.
2. **API HTTP:** una vez validado el flujo productivo en Firebase, el formulario se extrae del iframe y consume el contrato HTTP versionado documentado en `GAS_API_CONTRACT.md`.

## Reglas de despliegue

1. No retirar el despliegue GAS mientras Firebase no complete cotizacion, reserva, listado y cancelacion.
2. Las operaciones de escritura deben usar idempotency key y token firmado de corta duracion.
3. El backend debe validar esquema, origen permitido y expiracion.
4. Nunca incluir secretos de GAS, Google o Calendar en `public/`.

## Comandos

```powershell
npx --yes firebase-tools@latest login
npx --yes firebase-tools@latest use qs-manager-reservas
npx --yes firebase-tools@latest emulators:start --only hosting
npx --yes firebase-tools@latest deploy --only hosting
```

El login y el despliegue deben ejecutarse con `qamilunaservices@qamilunastudio.com`.
