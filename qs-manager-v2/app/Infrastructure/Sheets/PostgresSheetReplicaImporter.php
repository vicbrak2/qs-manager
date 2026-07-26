<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use PDO;
use QSManager\Application\Sheets\SheetCsvReader;
use QSManager\Application\Sheets\SheetReplicaImporter;
use QSManager\Application\Sheets\SheetSyncResult;
use QSManager\Infrastructure\Sheets\Importers\AgendaMonthImporter;
use QSManager\Infrastructure\Sheets\Importers\BitacoraImporter;
use QSManager\Infrastructure\Sheets\Importers\CashTrackingImporter;
use QSManager\Infrastructure\Sheets\Importers\ExpensesImporter;
use QSManager\Infrastructure\Sheets\Importers\ServicesCatalogImporter;
use QSManager\Infrastructure\Sheets\Importers\ServicesMasterImporter;
use QSManager\Infrastructure\Sheets\Importers\WorkshopsImporter;
use RuntimeException;
use Throwable;

/**
 * Orquestador de la sincronizacion de todas las hojas replicadas.
 *
 * Partido en Fase 4 del plan de migracion: el parseo/normalizacion vive en
 * SheetRowMapper, la configuracion de hojas en SheetImportSource, las
 * proyecciones compartidas (qs_services/qs_bookings) en
 * BookingProjectionWriter, y cada hoja tiene su propio Importer en
 * Infrastructure/Sheets/Importers/. Esta clase solo coordina: adquiere el
 * lock, recorre las fuentes, delega el import de cada una, registra el
 * resultado y reconcilia proyecciones al final.
 *
 * Constructor y comportamiento publico sin cambios respecto a la version
 * anterior (pre-Fase-4) -- KISS, sin framework interno: cada importer recibe
 * PDO + runId + sheetName + rows y devuelve la cantidad importada.
 */
final class PostgresSheetReplicaImporter implements SheetReplicaImporter
{
    private const ADVISORY_LOCK_KEY = 987654321;

    /** @var array<string, callable(int, string, list<list<mixed>>): int> */
    private readonly array $handlers;

    public function __construct(
        private readonly PDO $connection,
        private readonly SheetCsvReader $reader,
    ) {
        $mapper = new SheetRowMapper();
        $projections = new BookingProjectionWriter($this->connection, $mapper);

        $servicesCatalog = new ServicesCatalogImporter($this->connection, $mapper, $projections);
        $servicesMaster = new ServicesMasterImporter($this->connection, $mapper, $projections);
        $workshops = new WorkshopsImporter($this->connection, $mapper);
        $agendaMonth = new AgendaMonthImporter($this->connection, $mapper, $projections);
        $bitacora = new BitacoraImporter($this->connection, $mapper, $projections);
        $cashTracking = new CashTrackingImporter($this->connection, $mapper);
        $expenses = new ExpensesImporter($this->connection, $mapper);

        $this->handlers = [
            'importServices' => [$servicesCatalog, 'import'],
            'importServicesMaster' => [$servicesMaster, 'import'],
            'importWorkshops' => [$workshops, 'import'],
            'importAgendaMonth' => [$agendaMonth, 'import'],
            'importBitacora' => [$bitacora, 'import'],
            'importCashTracking' => [$cashTracking, 'import'],
            'importOperationalExpenses' => [$expenses, 'importOperational'],
            'importFixedExpenses' => [$expenses, 'importFixed'],
        ];
    }

    public function importAll(?int $syncRunId = null, ?callable $onSourceCompleted = null): SheetSyncResult
    {
        $lockAcquired = $this->connection->query('SELECT pg_try_advisory_lock(' . self::ADVISORY_LOCK_KEY . ')')->fetchColumn();
        if (!$lockAcquired) {
            throw new RuntimeException('Sync is already running concurrently.');
        }

        try {
            $results = [];
            $allCriticalSucceeded = true;

            foreach (SheetImportSource::all() as $sheetName => $source) {
                $runId = null;
                $rowsSeen = 0;
                $rowsImported = 0;
                $status = 'completed';
                $message = null;
                $duration = null;

                try {
                    $sourceId = $this->sourceId($sheetName, $source);
                    $runId = $this->startRun($sourceId, $syncRunId);

                    $this->connection->beginTransaction();

                    $start = microtime(true);
                    $rows = $this->reader->read($source['spreadsheet_id'], $source['gid'], $sheetName);
                    $duration = (int) ((microtime(true) - $start) * 1000);

                    $rowsSeen = count($rows);
                    $handler = $this->handlers[$source['handler']];
                    $rowsImported = $handler($runId, $sheetName, $rows);

                    $this->connection->commit();
                } catch (Throwable $exception) {
                    $duration = isset($start) ? (int) ((microtime(true) - $start) * 1000) : null;
                    if ($this->connection->inTransaction()) {
                        $this->connection->rollBack();
                    }
                    $status = 'failed';
                    $message = $exception->getMessage();
                } finally {
                    if ($runId !== null) {
                        $this->finishRun($runId, $status, $rowsSeen, $rowsImported, $message, $duration);
                    }
                }

                $results[$sheetName] = [
                    'rows_seen' => $rowsSeen,
                    'rows_imported' => $rowsImported,
                    'status' => $status,
                    'message' => $message,
                ];

                if (($source['is_critical'] ?? false) && $status === 'failed') {
                    $allCriticalSucceeded = false;
                }

                if ($onSourceCompleted) {
                    $onSourceCompleted();
                }
            }

            if ($allCriticalSucceeded) {
                $this->connection->beginTransaction();
                try {
                    $this->reconcileOperationalProjections();
                    $this->relinkBitacorasToImportedBookings();
                    $this->connection->commit();
                } catch (Throwable $exception) {
                    if ($this->connection->inTransaction()) {
                        $this->connection->rollBack();
                    }
                    throw $exception;
                }
            }

            return new SheetSyncResult($results);
        } finally {
            $this->connection->exec('SELECT pg_advisory_unlock(' . self::ADVISORY_LOCK_KEY . ')');
        }
    }

    private function reconcileOperationalProjections(): void
    {
        $this->connection->exec("delete from qs_bookings where source_sheet = 'Talleres'");

        $this->connection->exec(
            "update qs_bookings booking
             set service_id = master_service.id
             from qs_services local_service,
                  qs_services master_service
             where booking.service_id = local_service.id
               and local_service.source_sheet is null
               and local_service.sheet_external_id is not null
               and master_service.source_sheet = 'Servicios_Master'
               and master_service.sheet_external_id = local_service.sheet_external_id"
        );

        $this->connection->exec(
            "delete from qs_services local_service
             using qs_services master_service
             where local_service.source_sheet is null
               and local_service.sheet_external_id is not null
               and master_service.source_sheet = 'Servicios_Master'
               and master_service.sheet_external_id = local_service.sheet_external_id"
        );

        $this->connection->exec(
            "update qs_services archived_service
             set source_sheet = 'Servicios_Master_Archivado',
                 source_row = null,
                 active = false
             where archived_service.source_sheet is null
               and archived_service.sheet_external_id is not null
               and exists (
                   select 1 from qs_bookings booking
                   where booking.service_id = archived_service.id
               )
               and not exists (
                   select 1 from qs_services master_service
                   where master_service.source_sheet = 'Servicios_Master'
                     and master_service.sheet_external_id = archived_service.sheet_external_id
               )"
        );

        $this->connection->exec(
            "delete from qs_services stale_service
             where stale_service.source_sheet is null
               and stale_service.sheet_external_id is not null
               and not exists (
                   select 1 from qs_bookings booking
                   where booking.service_id = stale_service.id
               )
               and not exists (
                   select 1 from qs_services master_service
                   where master_service.source_sheet = 'Servicios_Master'
                     and master_service.sheet_external_id = stale_service.sheet_external_id
               )"
        );

        $this->connection->exec(
            "update qs_bookings b
             set service_id = master_services.id
             from qs_services old_services,
                  qs_services master_services
             where b.service_id = old_services.id
               and old_services.source_sheet = 'Servicios'
               and master_services.source_sheet = 'Servicios_Master'
               and lower(trim(old_services.name)) = lower(trim(master_services.name))"
        );

        $this->connection->exec(
            "update qs_bookings bitacora_booking
             set customer_phone = coalesce(bitacora_booking.customer_phone, agenda_booking.customer_phone),
                 address = coalesce(bitacora_booking.address, agenda_booking.address),
                 comuna = coalesce(bitacora_booking.comuna, agenda_booking.comuna),
                 calendar_event_id = coalesce(bitacora_booking.calendar_event_id, agenda_booking.calendar_event_id)
             from qs_bookings agenda_booking,
                  qs_services agenda_service,
                  qs_services bitacora_service
             where agenda_booking.source_sheet in (
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
             )
               and bitacora_booking.source_sheet = 'Bitácora QS — Servicios'
               and agenda_booking.service_id = agenda_service.id
               and bitacora_booking.service_id = bitacora_service.id
               and lower(trim(coalesce(agenda_booking.customer_name, ''))) = lower(trim(coalesce(bitacora_booking.customer_name, '')))
               and agenda_booking.scheduled_for::date = bitacora_booking.scheduled_for::date
               and lower(trim(agenda_service.name)) = lower(trim(bitacora_service.name))"
        );

        $this->connection->exec(
            "delete from qs_services old_services
             using qs_services master_services
             where old_services.source_sheet = 'Servicios'
               and master_services.source_sheet = 'Servicios_Master'
               and lower(trim(old_services.name)) = lower(trim(master_services.name))"
        );

        $this->connection->exec(
            "delete from qs_bookings agenda_booking
             using qs_bookings bitacora_booking,
                   qs_services agenda_service,
                   qs_services bitacora_service
             where agenda_booking.source_sheet in (
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
             )
               and bitacora_booking.source_sheet = 'Bitácora QS — Servicios'
               and agenda_booking.service_id = agenda_service.id
               and bitacora_booking.service_id = bitacora_service.id
               and lower(trim(coalesce(agenda_booking.customer_name, ''))) = lower(trim(coalesce(bitacora_booking.customer_name, '')))
               and agenda_booking.scheduled_for::date = bitacora_booking.scheduled_for::date
               and lower(trim(agenda_service.name)) = lower(trim(bitacora_service.name))"
        );

        $this->connection->exec(
            "delete from qs_bookings
             where source_sheet in (
                'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
                'Bitácora QS — Servicios'
             )
               and (
                customer_name is null
                or trim(customer_name) = ''
                or service_id is null
             )"
        );
    }

    public function relinkBitacorasToImportedBookings(): void
    {
        // min(b2.id) + not exists: si dos bitacoras terminaron compartiendo
        // booking_external_id (posible tras ciclos de sync que anulan el FK),
        // solo una se re-vincula -- sin esto el UPDATE violaria el indice
        // unico parcial de booking_id y tumbaria toda la reconciliacion.
        $this->connection->exec(
            'update qs_bitacoras b
             set booking_id = k.id
             from qs_bookings k
             where b.booking_external_id is not null
               and b.booking_id is null
               and k.sheet_external_id = b.booking_external_id
               and b.id = (
                   select min(b2.id) from qs_bitacoras b2
                   where b2.booking_external_id = b.booking_external_id
                     and b2.booking_id is null
               )
               and not exists (
                   select 1 from qs_bitacoras b3 where b3.booking_id = k.id
               )'
        );
    }

    /**
     * @param array{spreadsheet_id: string, gid: int, purpose: string, handler: string, is_critical: bool} $source
     */
    private function sourceId(string $sheetName, array $source): int
    {
        $this->connection->prepare(
            'insert into qs_sheet_sources (spreadsheet_id, spreadsheet_title, sheet_name, sheet_gid, purpose)
             values (:spreadsheet_id, :spreadsheet_title, :sheet_name, :sheet_gid, :purpose)
             on conflict (spreadsheet_id, sheet_name) do nothing'
        )->execute([
            'spreadsheet_id' => $source['spreadsheet_id'],
            'spreadsheet_title' => SheetImportSource::spreadsheetTitleFor($source['spreadsheet_id']),
            'sheet_name' => $sheetName,
            'sheet_gid' => $source['gid'],
            'purpose' => $source['purpose'],
        ]);

        $statement = $this->connection->prepare(
            'select id from qs_sheet_sources where spreadsheet_id = :spreadsheet_id and sheet_name = :sheet_name'
        );
        $statement->execute([
            'spreadsheet_id' => $source['spreadsheet_id'],
            'sheet_name' => $sheetName,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function startRun(int $sourceId, ?int $syncRunId = null): int
    {
        $statement = $this->connection->prepare(
            'insert into qs_sheet_import_runs (source_id, sync_run_id, status) values (:source_id, :sync_run_id, :status) returning id'
        );
        $statement->execute([
            'source_id' => $sourceId,
            'sync_run_id' => $syncRunId,
            'status' => 'running',
        ]);

        return (int) $statement->fetchColumn();
    }

    private function finishRun(int $runId, string $status, int $rowsSeen, int $rowsImported, ?string $message, ?int $durationMs = null): void
    {
        $this->connection->prepare(
            'update qs_sheet_import_runs
             set status = :status,
                 rows_seen = :rows_seen,
                 rows_imported = :rows_imported,
                 error_message = :error_message,
                 duration_ms = :duration_ms,
                 finished_at = now()
             where id = :id'
        )->execute([
            'id' => $runId,
            'status' => $status,
            'rows_seen' => $rowsSeen,
            'rows_imported' => $rowsImported,
            'error_message' => $message,
            'duration_ms' => $durationMs,
        ]);

        if ($status === 'completed') {
            $this->connection->prepare(
                'update qs_sheet_sources
                 set last_synced_at = now()
                 where id = (select source_id from qs_sheet_import_runs where id = :run_id)'
            )->execute(['run_id' => $runId]);
        }
    }
}
