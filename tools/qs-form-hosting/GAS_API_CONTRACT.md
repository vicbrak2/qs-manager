# Contrato HTTP GAS para Cotizador QS

Base provisional: despliegue web de Apps Script propietario de `qamilunaservices@qamilunastudio.com`.

Todas las respuestas usan JSON:

```json
{
  "ok": true,
  "data": {},
  "error": null,
  "request_id": "uuid"
}
```

## Lecturas publicas

### `GET ?action=bootstrap&api_version=1`

Devuelve servicios activos disponibles para el selector del cotizador, tipos, profesionales seleccionables y metadatos de vigencia. La opcion `Todos` pertenece al selector de tipo de servicio; al seleccionarla, el selector de servicios debe listar todos los servicios activos sin filtro por tipo. El selector de servicios muestra solo el nombre del servicio, sin precios. No devuelve correos privados de profesionales.

### `GET ?action=travel_quote&api_version=1&commune=...`

Devuelve la tarifa vigente y la marca temporal de la fuente de Sheets.

### `GET ?action=pending_bookings&api_version=1&token=...`

Devuelve solo reservas futuras o no terminadas autorizadas por un token temporal.

## Escrituras protegidas

### `POST ?action=create_booking&api_version=1`

Registra una reserva en Agenda 2026, Bitacora QS y Calendar. Requiere:

- `Content-Type: text/plain;charset=utf-8` para evitar preflight incompatible con GAS.
- `idempotency_key` generado por el navegador.
- token de formulario firmado y vigente.
- esquema estricto de campos permitidos.

### `POST ?action=cancel_booking&api_version=1`

Cancela en cadena Agenda, Bitacora y Calendar. Requiere token especifico de reserva, motivo e `idempotency_key`.

## Seguridad

- El frontend no contiene secretos.
- Los tokens se firman en GAS con una clave guardada en `PropertiesService`.
- Vigencia recomendada: 15 minutos para cotizacion y 30 minutos para completar la reserva.
- Las escrituras rechazan tokens vencidos, payloads desconocidos e idempotency keys reutilizadas con contenido diferente.
- Los errores no exponen IDs internos, stack traces ni correos de profesionales.
- La lista de orígenes permitidos incluye exclusivamente los dominios Firebase del proyecto y, posteriormente, el dominio personalizado.

## Compatibilidad durante la migracion

El despliegue GAS actual continua sirviendo `Cotizador.html`. Los endpoints HTTP nuevos se agregan sin retirar `doGet()` visual hasta que Firebase supere las pruebas de extremo a extremo.
