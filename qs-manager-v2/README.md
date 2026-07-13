# QS Manager V2

Aplicacion standalone local-first para reemplazar la V1 basada en WordPress.

## Stack

- PHP 8.3
- Slim 4
- PostgreSQL 16
- Docker Compose

## Levantar entorno local

```bash
docker compose up --build
```

Endpoints iniciales:

- `GET http://localhost:8080/`
- `GET http://localhost:8080/health`
- `GET http://localhost:8080/api/v1/modules`
- `GET http://localhost:8080/api/v1/services`
- `POST http://localhost:8080/api/v1/services`
- `GET http://localhost:8080/api/v1/team`
- `POST http://localhost:8080/api/v1/team`
- `GET http://localhost:8080/api/v1/bookings`
- `POST http://localhost:8080/api/v1/bookings`
- `POST http://localhost:8080/api/v1/bookings/{id}/sync-gas`
- `GET http://localhost:8080/api/v1/sync/sheets/status`
- `POST http://localhost:8080/api/v1/sync/sheets/import`
- `POST http://localhost:8080/api/v1/agents/chat`

Ejemplo de stub local:

```bash
curl -X POST http://localhost:8080/api/v1/agents/chat \
  -H "Content-Type: application/json" \
  -d "{\"message\":\"hola\"}"
```

Ejemplo de creacion de servicio:

```bash
curl -X POST http://localhost:8080/api/v1/services \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"Maquillaje social\",\"category\":\"maquillaje\",\"duration_minutes\":90}"
```

Ejemplo de creacion de staff:

```bash
curl -X POST http://localhost:8080/api/v1/team \
  -H "Content-Type: application/json" \
  -d "{\"display_name\":\"Camila Villalobos\",\"role\":\"coordinadora\"}"
```

Ejemplo de creacion de booking con campos compatibles con Bitacora/Seguimiento Caja:

```bash
curl -X POST http://localhost:8080/api/v1/bookings \
  -H "Content-Type: application/json" \
  -d "{\"service_id\":1,\"staff_id\":1,\"customer_name\":\"Cliente Demo\",\"customer_phone\":\"+56912345678\",\"scheduled_for\":\"2026-07-20T14:30:00Z\",\"status\":\"confirmed\",\"address\":\"Av. Siempre Viva 123\",\"comuna\":\"Providencia\",\"service_value\":85000,\"transfer_value\":12000,\"deposit_amount\":30000,\"total_service\":97000,\"balance_due\":67000,\"payment_status\":\"abonado\",\"service_status\":\"agendado\",\"contract_id\":\"QS-2026-001\",\"milestone\":\"reserva\",\"cash_group\":\"servicios\"}"
```

## Migraciones locales

En una base nueva, Docker ejecuta automaticamente los SQL montados en `database/migrations`.
Si el volumen de PostgreSQL ya existia, aplica las migraciones manualmente:

```powershell
.\tools\migrate.ps1
```

## Replica normalizada de Google Sheets

La V2 mantiene tablas locales para representar los Sheets como base historica normalizada:

- `qs_sheet_sources`: metadata de cada spreadsheet/sheet conectado.
- `qs_sheet_import_runs`: auditoria de cada importacion.
- `qs_sheet_service_catalog_rows`: filas normalizadas del catalogo `Servicios`.
- `qs_sheet_bitacora_rows`: filas normalizadas de `Bitacora QS - Servicios`.
- `qs_sheet_cash_tracking_rows`: filas normalizadas de `Seguimiento Caja`.
- `qs_sheet_operational_expense_rows`: filas normalizadas de `Gastos Operativos`.
- `qs_sheet_agenda_month_rows`: filas normalizadas de los meses de `Agenda 2026`.
- `qs_sheet_agenda_value_rows`: filas normalizadas de precios/base `Valores` de `Agenda 2026`.
- `qs_sheet_workshop_rows`: filas normalizadas de `Talleres` de `Agenda 2026`.

El dominio operativo sigue en tablas propias (`qs_services`, `qs_staff`, `qs_bookings`) y conserva referencias de origen (`source_sheet`, `source_row`, estados GAS) sin depender de Google para funcionar.

La sincronizacion actual es solo de lectura desde Sheets hacia PostgreSQL. No modifica celdas, formulas, tabs ni Apps Script.

```bash
curl -X POST http://localhost:8080/api/v1/sync/sheets/import
```

Tambien se puede ejecutar dentro del contenedor:

```bash
docker exec qs-manager-v2-app sh -lc "php tools/import-sheets.php"
```

`SHEETS_READ_SYNC_ENABLED=true` habilita la lectura de CSV export desde Google Sheets. `sheets_write_sync` permanece deshabilitado.

## Apps Script / GAS

El archivo `../tools/qs-manager-v2-webapp.gs` contiene un `doPost(e)` seguro para desplegar como Web App de Apps Script cuando se quiera sincronizar bookings hacia la Bitacora.

Por defecto `GAS_WEBAPP_URL` esta vacio en `docker-compose.yml`, asi que `POST /api/v1/bookings/{id}/sync-gas` responde `skipped` y no realiza llamadas externas. Para activar la integracion, define `GAS_WEBAPP_URL` con la URL desplegada de Apps Script y reinicia el contenedor `app`.

## Regla de fase actual

No se permite conectar LLMs, Qdrant, WhatsApp ni APIs externas. Los modulos que antes dependian de
servicios cloud deben permanecer como stubs locales hasta una decision arquitectonica posterior. GAS tambien queda desactivado mientras `GAS_WEBAPP_URL` no este configurado.
