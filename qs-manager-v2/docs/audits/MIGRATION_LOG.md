# Log de ejecución del plan de migración V1 → V2 — para continuar en otra sesión

Este documento resume qué se hizo, en qué orden, con qué resultado, y qué
queda pendiente. El plan original (7 fases) se ejecutó completo. Léelo junto
con `migration-audit.md` (Fase 1) y `deprecated-v1-components.md` (Fase 2),
que quedan en esta misma carpeta.

## Contexto del repo

- Raíz: `C:\Users\USER\Repo\qs-manager` (V1, PHP+WordPress, `app/`)
- V2 standalone: `C:\Users\USER\Repo\qs-manager\qs-manager-v2` (`app/`, sin WordPress)
- Stack V2: PHP 8.3, Slim 4, Postgres, PHPUnit 11, Playwright (E2E)
- **No hay PHP CLI instalado localmente fuera de Docker.** Todo corre vía `docker compose`:
  ```powershell
  # Tests unitarios/integración/arquitectura
  docker compose --profile test run --rm test vendor/bin/phpunit

  # Filtrar por nombre
  docker compose --profile test run --rm test vendor/bin/phpunit --filter "Architecture"
  ```
- Contenedores del día a día: `app` (puerto 8080, sirve `public/` con volumen montado
  en vivo — **editar JS/CSS/PHP en `public/` o `app/` se refleja al instante, sin
  rebuild**), `db` (puerto 5433 desde el host), `worker`, `db_test`/`test` (solo con
  `--profile test`).
- E2E (Playwright): desde `qs-manager-v2/tests/E2E`, requiere el contenedor `app`
  corriendo en `localhost:8080` (`docker ps` para verificar) y `node_modules`
  instalados en la raíz del proyecto (ya lo estaban):
  ```powershell
  cd qs-manager-v2/tests/E2E
  npx playwright test
  ```

## Estado al cierre de esta sesión

- **PHPUnit: 121/121 verde** (1 skip intencional, `testStaticAssetsAreServedCorrectly`,
  requiere servidor local aparte).
- **Playwright E2E: 15/15 verde.**
- **Guardrails de arquitectura: 4/4 verde**, verificados con una violación
  deliberada (se insertó `$wpdb` en `Domain/Finance/` a propósito, el guardrail
  lo detectó, se revirtió).

## Fase 1 — Auditoría (`docs/audits/migration-audit.md`)

Script: `tools/audit-v1-v2.ps1` (PowerShell, no PHP CLI — reproducible con
`pwsh qs-manager-v2/tools/audit-v1-v2.ps1`). Análisis textual/regex sobre
ambos árboles de código.

Resultado: V1 206 archivos/15119 líneas/199 clases/340 refs WordPress. V2 70
archivos/7195 líneas/69 clases/**0 refs WordPress** (cumple regla de Fase 0).

## Fase 2 — Deprecación V1 (`docs/audits/deprecated-v1-components.md`)

**50 archivos únicos** de V1 marcados con razón explícita (WordPress / N8n /
Qdrant / LatePoint / revisión manual). Se amplió la lista literal del plan con
grep de contenido real (encontró acoplados que los globs no capturaban, ej.
`WpServiceCostRepository.php`, `RoleHooks.php`).

- **5 controllers ya son redundantes hoy** (V2 tiene su propio equivalente en
  `Interfaces/Http/`): Services, Finance, Team/Staff, Booking. Se pueden dar de
  baja sin esperar más.
- `BitacoraController.php` (V1) queda **excluido** de baja inmediata: V2 no
  tiene módulo de Bitácora todavía (ver pendientes).
- Nada se borró de V1 — solo se marcó. Borrar es una decisión aparte, no tomada.

## Fase 3 — Dominio puro migrado

Patrón que se repitió en 3 de los 4 módulos: el plan pedía clases puntuales
de V1, pero V2 ya había reconstruido esa lógica de forma independiente y
mejor adaptada a su propio modelo — en esos casos **no se duplicó**, solo se
migró lo que era un vacío real.

| Módulo | Migrado (nuevo en V2) | NO migrado — ya superado por V2 |
|---|---|---|
| Finance | `app/Domain/Finance/PaymentMethod.php` | `MarginCalculator`/`MonthlySummaryBuilder` → ya cubiertos por `FinancialMetrics` (existente) |
| Booking | `app/Domain/Booking/BookingTimeRange.php`, `BookingConflictException.php` | `ReservationStatus`/`ReservationNormalizer`/`Reservation*` (forma de datos de LatePoint) → `Booking`/`BookingStatus` propios de V2 |
| Bitácora | `app/Domain/Bitacora/{PickupPoint,ServiceAddress,TravelDuration,RoutePlan,BitacoraPolicy,Bitacora,TravelNote}.php` | — (módulo nuevo, sin equivalente previo en V2) |
| Team | `app/Domain/Team/{AvailabilityWindow,AvailabilityChecker}.php` | `StaffMember`/`StaffId`/`Specialty` → `StaffRole` propios de V2 |

Cada clase tiene su test en `tests/Unit/Domain/<Modulo>/`. Cero dependencias
de framework en todas.

**⚠️ IMPORTANTE — estado de conexión a la app real** (actualizado 2026-07-24):
- `PaymentMethod`: ✅ **conectado** — `BitacoraImporter` normaliza
  `forma de pago` con `PaymentMethod::fromNullable()` antes de persistir en
  `qs_sheet_bitacora_rows.payment_method` (`transferencia`/`efectivo`/`otro`).
  Test: `testBitacoraPaymentMethodIsNormalizedOnImport`. Nadie más lee esa
  columna hoy (verificado por grep en app/, database/, public/), así que el
  cambio no rompe consumidores.
- `BookingTimeRange`/`BookingConflictException`: no hay ningún caso de uso
  ("verificar disponibilidad antes de confirmar reserva") que los use todavía.
- `BitacoraPolicy`/`Bitacora`: no existe `Infrastructure/Persistence` ni
  `Interfaces/Http` para Bitácora en V2 — solo el dominio.
- `AvailabilityChecker`: no hay endpoint de disponibilidad de staff.

## Fase 4 — Refactor del importer (`PostgresSheetReplicaImporter.php`)

**1509 → 352 líneas.** Dividido en:

```
app/Infrastructure/Sheets/
  PostgresSheetReplicaImporter.php    # orquestador: lock, loop, dispatch, reconciliación
  SheetImportSource.php               # config de las 17 hojas (antes SOURCES const)
  SheetRowMapper.php                  # 23 helpers puros de parseo (sin PDO)
  BookingProjectionWriter.php         # escritura compartida qs_services/qs_bookings
  Importers/
    ServicesCatalogImporter.php       # hoja "Servicios"
    ServicesMasterImporter.php        # hoja "Servicios_Master"
    WorkshopsImporter.php             # hoja "Talleres"
    AgendaMonthImporter.php           # hojas Enero..Diciembre
    BitacoraImporter.php              # hoja "Bitácora QS — Servicios"
    CashTrackingImporter.php          # hoja "Seguimiento Caja"
    ExpensesImporter.php              # "Gastos Operativos" + "Gastos_Fijos" (2 métodos)
```

- Constructor público de `PostgresSheetReplicaImporter` **sin cambios**
  (`__construct(PDO $connection, SheetCsvReader $reader)`) — `AppFactory.php`
  no necesitó tocarse.
- El test de integración (`tests/Integration/Sheets/PostgresSheetReplicaImporterTest.php`)
  usaba **Reflection sobre métodos privados** que se movieron de clase — se
  actualizó para reflejar sobre `SheetRowMapper`/`BookingProjectionWriter` en
  vez de `PostgresSheetReplicaImporter`. Si vas a tocar más métodos privados
  de estas clases, revisá primero si algún test usa `ReflectionMethod` sobre
  ellos.
- **Código muerto `upsertWorkshopBookingProjection`: ✅ borrado (2026-07-24).**
  La investigación git corrigió la hipótesis "nunca invocado": el método SÍ
  se invocaba desde `importWorkshops` en el commit base de V2 (`1b14777`), y
  la llamada se eliminó deliberadamente en `03fe53d` (los talleres dejaron de
  proyectarse a `qs_bookings`; quedan solo en `qs_sheet_workshop_rows`). Se
  borraron también sus dos helpers huérfanos de `SheetRowMapper`
  (`inferWorkshopServiceName`, `normalizePhone`); `workshopPaymentAmount` se
  conserva (lo usa `WorkshopsImporter`). Recuperable de git si hiciera falta.
- Guardrail de tamaño (Fase 6) actualizado: la excepción de línea/métodos
  para este archivo ya no hace falta.

## Fase 5 — Tests HTTP divididos

`tests/Integration/HttpRoutesTest.php` (990 líneas, 25 tests, 2 clases
mezcladas) → :

```
tests/Support/
  HttpTestCase.php          # base: setUp transaccional + json()/payload()
  MockGasStreamWrapper.php  # mock del stream mock-gas://
tests/Integration/Http/
  ServicesRoutesTest.php    # 6 tests
  BookingRoutesTest.php     # 13 tests (incluye sync GAS)
  TeamRoutesTest.php        # 2 tests
  SheetsRoutesTest.php      # 3 tests (incluye dashboard web "/")
  FinanceRoutesTest.php     # 1 test
```

25/25 tests preservados exactos (verificado por conteo antes/después).

## Fase 6 — Guardrails de arquitectura

`tests/Architecture/`:
- `NoWordPressDependencyTest.php` — falla si `app/` tiene `wp_*`/`WP_*`/`$wpdb`/hooks.
- `FileSizeGuardrailTest.php` — techo 700 líneas / 25 métodos por archivo,
  con **baseline congelado tipo "ratchet"** para deuda técnica ya conocida
  (hoy solo `Domain/Booking/Booking.php`, 26 métodos — puede existir pero no
  crecer más).
- `DomainPurityTest.php` — `app/Domain/**` no puede usar `PDO`/`Slim`/`curl_init`/superglobales.

Verificados insertando una violación real (`$wpdb` en `Domain/Finance/`),
confirmando que el guardrail la detecta, y revirtiendo.

## Fase 7 — Validación final + 2 bugs reales encontrados y corregidos

Al correr Playwright por primera vez: 13/15 pasaban, 2 fallaban. Investigados
a fondo (no se descartaron como "flaky" sin mirar):

1. **Bug real de UI** (`public/assets/js/features/bookings.js`,
   `toggleBookingSort()`): la columna `scheduled_for` tenía un caso especial
   que **siempre reimponía la dirección según la vista actual** en vez de
   alternar — clickear el header de fecha no hacía nada si ya estabas en la
   vista por defecto. Corregido para que se comporte como cualquier otra
   columna (alterna asc/desc). **Este fix ya está viviendo en el contenedor
   corriendo** (volumen montado, sin rebuild) — notable en la pestaña
   Reservas, columna Fecha, ahora sí alterna al clickear.
2. **Selector obsoleto** en `tests/E2E/ux_audit.spec.ts`: `.finance-grid` no
   existe en el código (el dashboard se rediseñó a
   `.finance-dashboard-row > .finance-main + .chart-panel`). Corregido el
   selector al equivalente real (`.finance-main`).
3. **Efecto colateral del fix #1**: `tests/E2E/dashboard.spec.ts` tenía una
   aserción que literalmente esperaba el bug (clickear fecha de nuevo debía
   quedarse en "ascending"). Se corrigió la expectativa para reflejar el
   comportamiento correcto (alterna), agregando un segundo click para
   confirmar el ciclo completo asc→desc→asc.

## Qué queda pendiente (actualizado 2026-07-24)

Resueltos en la sesión 2026-07-24 (suite tras los cambios: **PHPUnit 122/122**,
**Playwright 15/15**):
- ✅ `PaymentMethod` conectado al `BitacoraImporter` (ver Fase 3).
- ✅ `upsertWorkshopBookingProjection` investigado y borrado (ver Fase 4).
- ✅ Barrido completo de selectores E2E: se verificaron ~65 selectores/textos
  de los 4 specs (`dashboard`, `finance`, `sync`, `ux_audit`) contra
  `public/` + `app/Interfaces/Http/` — **cero obsoletos**; los 2 corregidos
  en la sesión anterior eran los únicos.
- ✅ Decisión sobre borrar V1: el usuario eligió **no borrar nada todavía**
  (los 50 archivos siguen solo marcados en `deprecated-v1-components.md`).

También en 2026-07-24, fuera del plan original:
- ✅ Tests de integración para **gastos fijos** (la cadena `Gastos_Fijos` →
  `qs_sheet_fixed_expense_rows` → proyección mensual → dashboard no tenía
  ninguna cobertura): `testFixedExpensesSheetIsImportedIntoReplicaTable` y
  `testFixedExpensesProjectMonthlyConfirmedEntries`.
- ✅ Runbook para activar el write-sync del catálogo vía GAS:
  `docs/runbooks/gas-write-sync-activation.md`. El lado PHP está listo y
  testeado; falta solo el deploy del webapp + secretos (pasos manuales del
  operador con la cuenta Google).

- ✅ `BookingTimeRange`/`BookingConflictException` **conectados** (2026-07-24):
  `CreateBooking` verifica solapamiento de horario por staff antes de guardar
  (duración del servicio, default 60 min si no declara; reservas canceladas
  no bloquean; sin staff no hay chequeo). El controller lo devuelve como 422
  sobre `scheduled_for`, que el form ya pinta. Solo aplica a creación — el
  PUT de update no pasa por `CreateBooking` (pendiente menor si se quiere).
  Test: `testOverlappingBookingForSameStaffIsRejected`.

- ✅ **Módulo Bitácora nativo de V2** (2026-07-24): migración `0015`
  (`qs_bitacoras` + `qs_bitacora_notes`), `PostgresBitacoraRepository`,
  `SaveBitacora` (aplica `BitacoraPolicy`), `BitacoraRequestValidator` y
  `BitacoraController` con las mismas rutas funcionales que V1: CRUD,
  `/{id}/summary` y `/{id}/notes`. Test: `BitacoraRoutesTest`. Con esto el
  `BitacoraController` de V1 quedó redundante.
- ✅ **Endpoint de disponibilidad de staff** (2026-07-24):
  `GET /api/v1/team/{id}/availability?date=&time=&duration_minutes=` usa
  `AvailabilityChecker`/`AvailabilityWindow` con las reservas activas como
  fuente de ocupación (no hay agenda declarada de ventanas — decisión
  registrada en `CheckStaffAvailability`). Test en `TeamRoutesTest`.

- ✅ **Bitácoras vinculadas a Reservas** (2026-07-25): migración `0016`
  agrega `qs_bitacoras.booking_id` (`ON DELETE SET NULL`) y
  `booking_external_id` para re-vincular reservas importadas después del
  delete/reinsert de Sheets. El listado de reservas expone `bitacora_id`,
  el form de Bitácora nace prellenado desde Reservas y el importer ejecuta
  el re-link por `sheet_external_id` tras la reconciliación.

- ✅ **V1 eliminado por completo** (2026-07-24, confirmado por el usuario tras
  quedar Bitácora V2 operativo): plugin WordPress entero (`app/`, `assets/`,
  `config/`, `database/`, `tests/`, `var/`, `qs-core.php`, `uninstall.php`,
  `readme.txt`, `composer.*`, `phpunit.xml.dist` de la raíz) — 316 archivos.
  Todo recuperable desde git. Se dejaron en la raíz `infrastructure/`
  (workflows n8n/WhatsApp que exceden al plugin) y `docs/` (documentación
  histórica) — decidir su destino es una conversación aparte.

- ✅ **Plan de traslado en la bitácora** (2026-07-26): migración `0017`
  (`hora_inicio_servicio`, `hora_fin_servicio`, `tramos` jsonb, `objetivo`,
  `consideraciones`) + dominio `TravelLeg`/`TravelItinerary`/
  `TravelPlanCalculator`. La regla operativa quedó codificada en un solo
  lugar (`TravelPlanCalculator`, replicada en `bitacora.js` para el cálculo
  en vivo): **llegada = inicio − 15 min**, **salida = llegada − suma de
  tramos − 15 min de holgura por tráfico** (la holgura se diluye en el
  viaje, no se muestra por tramo). Las horas calculadas NO se persisten:
  se derivan siempre de `hora_inicio_servicio` + tramos, así que corregir
  un tiempo de tramo replanifica sola la salida. UI: editor de tramos,
  panel que lista lo que falta para armar la bitácora, y botón "Copiar
  para el equipo" que arma el texto con emojis del formato acordado.

Sigue pendiente:
1. **Bitácoras de pruebas (a domicilio / en estudio) — EN PAUSA por decisión
   del usuario (2026-07-26).** Las pruebas también generan bitácora, pero
   antes hay que resolver un tema de negocio, no técnico: cuando el servicio
   requiere prueba presencial (en el estudio o a domicilio), a la
   profesional se le paga **una parte** del servicio en esa instancia y el
   **resto el día del evento** (matrimonio). Hasta definir cómo se registran
   esos dos pagos parciales contra un mismo servicio, no se modela la
   bitácora de prueba — de lo contrario los números de costo staff / margen
   quedarían mal. Al retomar, decidir primero el modelo de pagos y recién
   después la bitácora.
2. El chequeo de conflictos en el update de reservas (PUT) no existe aún
   (solo en creación).
3. **Discrepancia de puerto de Jarvis** — de otro repo (`llm-virtual-brain`),
   fuera del alcance de este plan.

## Cómo retomar en otra sesión

1. Leer este archivo + `migration-audit.md` + `deprecated-v1-components.md`.
2. Confirmar que el suite sigue verde antes de tocar nada:
   ```powershell
   cd C:\Users\USER\Repo\qs-manager\qs-manager-v2
   docker compose --profile test run --rm test vendor/bin/phpunit
   cd tests/E2E && npx playwright test
   ```
3. Si se va a conectar Fase 3 a la app real, empezar por `PaymentMethod` en
   `BitacoraImporter`/`AgendaMonthImporter` — es el camino más corto a
   impacto visible (columna `payment_method` en `qs_sheet_bitacora_rows`
   pasaría de string crudo a enum normalizado).
