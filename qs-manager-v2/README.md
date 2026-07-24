# QS Manager V2

Aplicacion standalone local-first para reemplazar la V1 basada en WordPress.

## Stack

- PHP 8.3
- Slim 4
- PostgreSQL 16
- Docker Compose

## Levantar entorno local

```bash
docker compose up -d
```

> [!IMPORTANT]
> El entorno de desarrollo consta de tres contenedores principales: `db` (PostgreSQL), `app` (API y Web, puerto 8080) y `worker` (Procesador asíncrono). Si intentas correr la aplicación mediante `php -S` fuera de Docker, el procesamiento asíncrono de sincronización no funcionará a menos que también levantes el worker localmente.

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

## Migraciones de base de datos

La base de datos utiliza un sistema de migraciones idempotente y seguro transaccionalmente que previene la corrupción del esquema.

> [!CAUTION]
> El directorio `database/migrations` **ya no se monta automáticamente** en `/docker-entrypoint-initdb.d`. El ciclo de vida del esquema es gestionado al 100% por nuestra herramienta en PHP.

Para aplicar o actualizar las migraciones en cualquier entorno (Desarrollo, Testing, o Producción), ejecuta el comando oficial:

```bash
docker compose exec app php tools/migrate.php
```

Usuarios de Windows pueden usar el atajo de PowerShell:

```powershell
.\tools\migrate.ps1
```

## Pruebas de Integración y Aislamiento

La suite de pruebas en esta V2 corre bajo un entorno **completamente aislado**. Se levanta una base de datos PostgreSQL efímera en memoria RAM (`tmpfs`), lo que previene que PHPUnit corrompa la base de datos operativa y garantiza pruebas veloces.

Para ejecutar la suite completa de pruebas:

```bash
docker compose --profile test run --rm test vendor/bin/phpunit
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
- `Servicios_Master` de Bitacora QS es la fuente operativa de identidad, nombre y precio publicado.
- `Servicios` de Seguimiento Contable conserva el desglose financiero de costos y utilidad.
- `Valores` de Agenda 2026 queda excluida del flujo de sincronizacion; su tabla historica se conserva solo por compatibilidad y auditoria.
- `qs_sheet_workshop_rows`: filas normalizadas de `Talleres` de `Agenda 2026`.

El dominio operativo sigue en tablas propias (`qs_services`, `qs_staff`, `qs_bookings`) y conserva referencias de origen (`source_sheet`, `source_row`, estados GAS) sin depender de Google para funcionar.

La sincronizacion desde Sheets a PostgreSQL se procesa mediante una arquitectura **asíncrona** de alta concurrencia.

```bash
# Encola un trabajo de sincronización en la base de datos (Devuelve HTTP 202)
curl -X POST http://localhost:8080/api/v1/sync/sheets/import
```

Para procesar estas colas, el sistema cuenta con un Worker deduplicado basado en bloqueos de PostgreSQL (`FOR UPDATE SKIP LOCKED`).
En Docker, el servicio `worker` ya corre el script:

```bash
docker exec qs-manager-v2-worker php tools/sync-worker.php
```

`SHEETS_READ_SYNC_ENABLED=true` habilita la lectura de CSV export desde Google Sheets. La escritura del catalogo permanece deshabilitada salvo que se configuren explicitamente las variables descritas abajo.

## Apps Script / GAS

El archivo `../tools/qs-manager-v2-webapp.gs` contiene dos acciones del Web App:

- La accion por defecto mantiene el upsert de bookings hacia Bitacora.
- `action=create_service` publica un alta idempotente en `Servicios` (Seguimiento Contable) y `Servicios_Master` (BitacoraQS).

La escritura de servicios requiere `QS_MANAGER_CATALOG_API_KEY` en Script Properties y las siguientes variables locales:

```dotenv
SHEETS_WRITE_SYNC_ENABLED=true
GAS_CATALOG_WEBAPP_URL=https://script.google.com/macros/s/.../exec
GAS_CATALOG_SECRET=<mismo valor configurado en Script Properties>
```

El secreto se guarda solo en `.env` (ignorado por Git). Ante un fallo de GAS, la API devuelve `502` y no crea una fila local. Tras una escritura correcta, PostgreSQL recibe una proyeccion identificada como `Servicios_Master` y se encola el sync de lectura completo.

Por defecto `GAS_WEBAPP_URL` esta vacio en `docker-compose.yml`, asi que `POST /api/v1/bookings/{id}/sync-gas` responde `skipped` y no realiza llamadas externas. Para activar la integracion, define `GAS_WEBAPP_URL` con la URL desplegada de Apps Script y reinicia el contenedor `app`.

## Regla de fase actual

No se permite conectar LLMs, Qdrant, WhatsApp ni APIs externas. Los modulos que antes dependian de
servicios cloud deben permanecer como stubs locales hasta una decision arquitectonica posterior. GAS tambien queda desactivado mientras `GAS_WEBAPP_URL` no este configurado.
