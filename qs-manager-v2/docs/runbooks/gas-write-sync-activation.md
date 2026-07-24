# Runbook — Activar la escritura del catálogo hacia Sheets (GAS write-sync)

Estado actual: el lado PHP está **terminado y testeado** (`ServicesRoutesTest`
usa el mock `mock-gas://`), apagado por flag. Lo único que falta es del lado
Google, y requiere la cuenta `qamilunaservices@qamilunastudio.com` — son pasos
manuales del operador, no automatizables desde este repo.

## Arquitectura

```
POST /api/v1/services (V2)
  → ServicesController (recibe ServiceCatalogGateway opcional)
    → HttpGasServiceCatalogGateway
       POST JSON { action: "create_service", api_key, idempotency_key, service }
         → tools/qs-manager-v2-webapp.gs (Web App desplegado)
            → valida api_key contra Script Property QS_MANAGER_CATALOG_API_KEY
            → cachea idempotency_key ("service-write:<id>") para reintentos seguros
            → escribe en Servicios_Master
            ← { ok: true, result: {...} }
```

`AppFactory` solo inyecta el gateway si `SHEETS_WRITE_SYNC_ENABLED=true` **y**
`GAS_CATALOG_WEBAPP_URL` **y** `GAS_CATALOG_SECRET` están definidos. Con
cualquiera ausente, la app funciona igual que hoy (sin write-back).

## Pasos de activación (operador)

1. **Desplegar el webapp**: en [script.google.com](https://script.google.com)
   con la cuenta propietaria, pegar/actualizar el contenido de
   `tools/qs-manager-v2-webapp.gs` → Implementar → Nueva implementación →
   *Aplicación web* → ejecutar como el propietario, acceso "Cualquier usuario"
   (la autenticación real es el `api_key` del payload, no la sesión Google).
   Copiar la URL `/exec` resultante.
2. **Script Properties** (Configuración del proyecto → Propiedades):
   - `QS_MANAGER_CATALOG_API_KEY`: secreto fuerte generado por el operador
     (p. ej. 32+ caracteres aleatorios). Es la llave de escritura.
   - `QS_MANAGER_READ_API_KEY` (opcional): llave solo-lectura para
     `list_active_services` / `get_transport_values` (si no existe, esas
     acciones aceptan la llave de catálogo).
3. **`.env` del servidor V2** (nunca commitear estos valores):
   ```
   SHEETS_WRITE_SYNC_ENABLED=true
   GAS_CATALOG_WEBAPP_URL=<URL /exec del paso 1>
   GAS_CATALOG_SECRET=<mismo valor que QS_MANAGER_CATALOG_API_KEY>
   ```
4. **Recrear contenedores** (el env se lee al arrancar):
   ```powershell
   docker compose up -d --force-recreate app worker
   ```

## Verificación

1. Smoke del webapp (solo lectura, sin tocar datos) — reemplazar placeholders:
   ```powershell
   curl -L -X POST "<GAS_CATALOG_WEBAPP_URL>" -H "Content-Type: application/json" -d '{"action":"list_active_services","api_key":"<READ_O_CATALOG_KEY>"}'
   ```
   Debe responder `{"ok":true,...}` con los servicios de `Servicios_Master`.
2. Crear un servicio de prueba desde la UI de V2 (pestaña Servicios) o vía
   `POST /api/v1/services`, y confirmar que la fila aparece en
   `Servicios_Master`. El siguiente sync de lectura lo re-importa con
   `sheet_external_id`, cerrando el ciclo.
3. Reintentos: repetir el mismo POST con la misma `idempotency_key` no debe
   duplicar la fila (GAS devuelve el resultado cacheado).

## Rollback

`SHEETS_WRITE_SYNC_ENABLED=false` + recrear contenedores. El resto del
sistema no depende del gateway.

## Seguridad

- El secreto vive solo en `.env` (servidor) y en Script Properties (GAS).
  No va en el repo, ni en el frontend, ni en logs.
- Si se sospecha filtración: rotar `QS_MANAGER_CATALOG_API_KEY` en Script
  Properties y actualizar `GAS_CATALOG_SECRET` — no hay más copias.
