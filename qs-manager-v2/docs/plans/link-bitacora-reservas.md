# Plan: vincular Bitácoras ↔ Reservas

> Objetivo: que cada bitácora pueda nacer desde una reserva (pre-llenada) y
> quede vinculada a ella, convirtiendo la pestaña Bitácora en la capa
> operativa de Reservas en vez de un módulo paralelo.
> Estado actual: `qs_bitacoras` y `qs_bookings` no se referencian entre sí.

## Decisiones de diseño (tomar antes de codear)

1. **Cardinalidad 1:1** — una bitácora por reserva, forzada con índice único
   parcial. Si mañana se quiere 1:N (varios hitos por contrato), se elimina
   el índice, nada más.
2. **`ON DELETE SET NULL`** — la bitácora es registro operativo/histórico;
   sobrevive si la reserva se borra.
3. **⚠️ El problema del sync (crítico, no obvio):** los importers de Sheets
   (`BitacoraImporter`, `AgendaMonthImporter`) hacen
   `DELETE FROM qs_bookings WHERE source_sheet = :s` y re-insertan en cada
   sync → **los `id` de reservas importadas cambian en cada sync** y el FK
   quedaría en NULL cada vez. Solución: **doble llave**:
   - `booking_id` (FK volátil, para joins rápidos y reservas locales), y
   - `booking_external_id` (copia de `qs_bookings.sheet_external_id`,
     estable entre syncs, NULL para reservas locales).
   - Paso de **re-link post-import**: al final del import se re-resuelve
     `booking_id` desde `booking_external_id`.

## Fase 1 — Esquema

`database/migrations/0016_bitacora_booking_link.sql`:

```sql
alter table qs_bitacoras
    add column if not exists booking_id bigint references qs_bookings(id) on delete set null,
    add column if not exists booking_external_id varchar(80);

create index if not exists qs_bitacoras_booking_idx
    on qs_bitacoras(booking_id);

-- 1:1 — una bitácora por reserva (parcial: no aplica a las no vinculadas)
create unique index if not exists qs_bitacoras_booking_unique
    on qs_bitacoras(booking_id) where booking_id is not null;

create index if not exists qs_bitacoras_booking_external_idx
    on qs_bitacoras(booking_external_id) where booking_external_id is not null;
```

Aplicar: la corre solo `tools/migrate.php` (bootstrap de tests la aplica al
DB de test; para dev: `docker exec qs-manager-v2-app php tools/migrate.php`).

## Fase 2 — Dominio

- `app/Domain/Bitacora/Bitacora.php`:
  - Nuevos parámetros de constructor `?int $bookingId`, `?string $bookingExternalId`
    (después de `$id`, para mantener legible el orden).
  - `toArray()` agrega `booking_id` y `booking_external_id`.
  - **No agregar accessors públicos innecesarios**: solo `bookingId()` y
    `bookingExternalId()` (son necesarios para persistencia). Verificar que
    `FileSizeGuardrailTest` no proteste (Bitacora.php está lejos del techo
    de 25 métodos; el archivo con ratchet es `Booking.php`, ver Fase 4).
- `BitacoraPolicy`: sin cambios (el vínculo es opcional).
- Actualizar los helpers `makeBitacora()` de
  `tests/Unit/Domain/Bitacora/{BitacoraTest,BitacoraPolicyTest}.php`
  (constructor cambió de aridad).

## Fase 3 — Persistencia

- `app/Domain/Bitacora/BitacoraRepository.php` (puerto):
  ```php
  public function findByBookingId(int $bookingId): ?Bitacora;
  ```
- `PostgresBitacoraRepository`:
  - `booking_id` y `booking_external_id` en INSERT/UPDATE/`fromRow()`.
  - Implementar `findByBookingId()` (SELECT por booking_id, reusa fromRow).
- **Re-link post-sync** en `PostgresSheetReplicaImporter` (al final de
  `importAll()`, después de reconciliación):
  ```sql
  update qs_bitacoras b
  set booking_id = k.id
  from qs_bookings k
  where b.booking_external_id is not null
    and b.booking_id is null
    and k.sheet_external_id = b.booking_external_id;
  ```
  (el DELETE del import dejó `booking_id = NULL` vía FK; esto lo restaura).

## Fase 4 — Aplicación y HTTP

- `BitacoraRequestValidator` (inyectar `BookingRepository`):
  - `booking_id` opcional, entero positivo; si viene y
    `bookings->findById()` es null → error `booking_id: Selected booking does not exist.`
- `SaveBitacora`:
  - Acepta `booking_id`; si es reserva importada (`source_sheet` no nulo),
    copiar su `sheet_external_id` a `booking_external_id`.
  - Al **crear** con `booking_id`: si `findByBookingId()` ya devuelve otra
    bitácora → `InvalidArgumentException('La reserva #N ya tiene bitácora (#M).')`.
    El índice único de la Fase 1 queda como backstop.
- `BitacoraController`: catch `PDOException` código `23505` → 422 (carrera
  contra el índice único).
- **Exponer `bitacora_id` en el listado de reservas** (para que la tabla
  Reservas sepa si mostrar "Crear bitácora" o "Ver bitácora #N"):
  - `PostgresBookingRepository::findAll()/findById()`: `LEFT JOIN qs_bitacoras bi ON bi.booking_id = b.id`
    y seleccionar `bi.id AS bitacora_id` (sin N+1).
  - ⚠️ **Ratchet**: `Booking.php` está congelado en 26 métodos por
    `FileSizeGuardrailTest`. Para no sumar un accessor: nuevo parámetro
    `?int $bitacoraId` en `fromPersistence()` + propiedad privada + entrada
    `'bitacora_id'` dentro del `toArray()` existente. Cero métodos nuevos.

## Fase 5 — Frontend

- `WebController.php`:
  - Form de bitácora: `<input type="hidden" name="booking_id">` + chip
    `<div id="bitacora-booking-link" class="hidden"></div>` en el panel-head
    del form ("Vinculada a reserva #N", clickeable).
  - Tabla de reservas: **no agregar columna** (rompería `colgroup` de 15 y
    los `nth-child` de `ux_audit.spec.ts`); agregar el botón dentro de la
    celda Acciones existente:
    `data-create-bitacora="{id}"` o `data-open-bitacora="{bitacora_id}"`.
- `features/bitacora.js`:
  - `startBitacoraFromBooking(booking)`: `setTab('bitacora')` + reset +
    pre-llenar: `fecha_servicio` (fecha local de `scheduled_for`),
    `clienta_nombre`, `direccion_servicio` (address + comuna),
    `tipo_servicio` (service_name), `precio_cliente_clp` (total_service),
    `mua_id` (staff_id de la reserva si está activo), `booking_id` hidden.
  - `bitacoraPayload()` incluye `booking_id`.
  - `editBitacora()`: si `booking_id`, mostrar el chip con link de vuelta
    (`setTab('bookings')` + `editBooking(booking_id)`).
- `features/bookings.js` (`renderBookings`): en la celda Acciones,
  botón condicional según `booking.bitacora_id`.
- `app.js`: handlers delegados para `[data-create-bitacora]`,
  `[data-open-bitacora]` y el link del chip.

## Fase 6 — Tests

PHPUnit (`tests/Integration/Http/BitacoraRoutesTest.php` + nuevo caso en
`BookingRoutesTest`):
1. Crear reserva → POST bitácora con `booking_id` → 201; GET bitácora trae
   `booking_id`; GET `/api/v1/bookings` trae `bitacora_id` en esa reserva.
2. Segunda bitácora con el mismo `booking_id` → 422 con mensaje claro.
3. `booking_id` inexistente → 422 sobre el campo.
4. Reserva importada (sembrar con `source_sheet`/`sheet_external_id`):
   la bitácora guarda `booking_external_id`; simular re-import (DELETE +
   re-insert de la reserva) + correr el re-link → `booking_id` re-resuelto.
5. `FileSizeGuardrailTest` sigue verde (Booking.php sin métodos nuevos).

Playwright (`tests/E2E/bitacora.spec.ts`, con rutas mockeadas):
6. Fila de reserva sin bitácora → click "Crear bitácora" → tab Bitácora
   activa, form pre-llenado (clienta/fecha/precio), y al guardar el POST
   interceptado contiene `booking_id`.
7. Fila con `bitacora_id` → botón abre el editor de esa bitácora con el
   chip "Vinculada a reserva #N".

## Fase 7 — Cierre

```powershell
cd C:\Users\USER\Repo\qs-manager\qs-manager-v2
docker compose --profile test run --rm test vendor/bin/phpunit
cd tests/E2E; npx playwright test; cd ../..
docker exec qs-manager-v2-app php tools/migrate.php
graphify update .
```

Commit sugerido: `feat(bitacora): link bitacoras to bookings with stable re-link across sheet syncs`.
Actualizar `docs/audits/MIGRATION_LOG.md` (sección pendientes).

## Fuera de alcance (explícito)

- 1:N bitácoras por reserva (hitos) — solo requiere quitar el índice único.
- Crear bitácoras automáticamente en el import de Sheets — decisión de
  producto aparte (¿toda reserva importada merece bitácora? probablemente no).
- Chequeo de conflictos en el PUT de reservas (pendiente previo, no de este plan).
