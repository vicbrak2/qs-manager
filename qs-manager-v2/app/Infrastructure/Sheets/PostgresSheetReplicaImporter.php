<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use PDO;
use QSManager\Application\Sheets\SheetCsvReader;
use QSManager\Application\Sheets\SheetReplicaImporter;
use QSManager\Application\Sheets\SheetSyncResult;

final class PostgresSheetReplicaImporter implements SheetReplicaImporter
{
    private const MAIN_SPREADSHEET_ID = '1Nl9hsMADAjJFJ_GPLGxvHenXjb91hLzp5EO-sDar0WE';
    private const BITACORA_SPREADSHEET_ID = '1cXOd9imvZGA5oj-Hlk8QHwB48cbx5hNptnGi0HdjMWE';
    private const AGENDA_2026_SPREADSHEET_ID = '1-tEocDyAbAb6muckm7X8JwJbbb2__uFhc49RP_lf6A4';

    private const SOURCES = [
        'Servicios_Master' => [
            'spreadsheet_id' => self::BITACORA_SPREADSHEET_ID,
            'gid' => 901001001,
            'purpose' => 'services_master',
            'handler' => 'importServicesMaster',
            'is_critical' => true,
        ],
        'Valores' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 0,
            'purpose' => 'agenda_values',
            'handler' => 'importAgendaValues',
            'is_critical' => true,
        ],
        'Talleres' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 1004626842,
            'purpose' => 'workshops',
            'handler' => 'importWorkshops',
            'is_critical' => false,
        ],
        'Enero' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 1600012026,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Febrero' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 297232105,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Marzo' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 817931728,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Abril' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 1913010066,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Mayo' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 2068172479,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Junio' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 544909107,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Julio' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 2073502017,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Agosto' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 301380220,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Septiembre' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 2086235780,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Octubre' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 1600102026,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Noviembre' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 1600112026,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Diciembre' => [
            'spreadsheet_id' => self::AGENDA_2026_SPREADSHEET_ID,
            'gid' => 1600122026,
            'purpose' => 'agenda_month',
            'handler' => 'importAgendaMonth',
            'is_critical' => true,
        ],
        'Servicios' => [
            'spreadsheet_id' => self::MAIN_SPREADSHEET_ID,
            'gid' => 839064078,
            'purpose' => 'service_catalog',
            'handler' => 'importServices',
            'is_critical' => true,
        ],
        'Seguimiento Caja' => [
            'spreadsheet_id' => self::MAIN_SPREADSHEET_ID,
            'gid' => 513021861,
            'purpose' => 'cash_tracking',
            'handler' => 'importCashTracking',
            'is_critical' => false,
        ],
        'Gastos Operativos' => [
            'spreadsheet_id' => self::MAIN_SPREADSHEET_ID,
            'gid' => 1642061717,
            'purpose' => 'operational_expenses',
            'handler' => 'importOperationalExpenses',
            'is_critical' => false,
        ],
        'Bitácora QS — Servicios' => [
            'spreadsheet_id' => self::BITACORA_SPREADSHEET_ID,
            'gid' => 1880538608,
            'purpose' => 'bitacora',
            'handler' => 'importBitacora',
            'is_critical' => true,
        ],
    ];

    public function __construct(
        private readonly PDO $connection,
        private readonly SheetCsvReader $reader,
    ) {
    }

    public function importAll(?int $syncRunId = null, ?callable $onSourceCompleted = null): SheetSyncResult
    {
        $lockAcquired = $this->connection->query("SELECT pg_try_advisory_lock(987654321)")->fetchColumn();
        if (!$lockAcquired) {
            throw new \RuntimeException('Sync is already running concurrently.');
        }

        try {
            $results = [];
            $allCriticalSucceeded = true;

        foreach (self::SOURCES as $sheetName => $source) {
            $runId = null;
            $rowsSeen = 0;
            $rowsImported = 0;
            $status = 'completed';
            $message = null;

            try {
                $sourceId = $this->sourceId($sheetName, $source);
                $runId = $this->startRun($sourceId, $syncRunId);

                $this->connection->beginTransaction();

                $start = microtime(true);
                $rows = $this->reader->read($source['spreadsheet_id'], $source['gid'], $sheetName);
                $duration = (int) ((microtime(true) - $start) * 1000);

                $rowsSeen = count($rows);
                $rowsImported = $this->{$source['handler']}($runId, $sheetName, $rows);

                $this->connection->commit();
            } catch (\Throwable $exception) {
                $duration = isset($start) ? (int) ((microtime(true) - $start) * 1000) : null;
                if ($this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
                $status = 'failed';
                $message = $exception->getMessage();
            } finally {
                if ($runId !== null) {
                    $this->finishRun($runId, $status, $rowsSeen, $rowsImported, $message, $duration ?? null);
                }
            }

            $results[$sheetName] = [
                'rows_seen' => $rowsSeen,
                'rows_imported' => $rowsImported,
                'status' => $status,
                'message' => $message,
            ];

            if ($source['is_critical'] ?? false) {
                if ($status === 'failed') {
                    $allCriticalSucceeded = false;
                }
            }

            if ($onSourceCompleted) {
                $onSourceCompleted();
            }
        }

        if ($allCriticalSucceeded) {
            $this->connection->beginTransaction();
            try {
                $this->reconcileOperationalProjections();
                $this->connection->commit();
            } catch (\Throwable $exception) {
                if ($this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
                throw $exception;
            }
        }

        return new SheetSyncResult($results);
        } finally {
            $this->connection->exec("SELECT pg_advisory_unlock(987654321)");
        }
    }

    private function importServices(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['servicio', 'precio venta']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->combine($headers, $rows[$index]);
            $serviceName = $this->string($row, 'servicio');
            if ($serviceName === null) {
                continue;
            }

            $sourceRow = $index + 1;
            $this->connection->prepare(
                'insert into qs_sheet_service_catalog_rows (
                    import_run_id, source_row, active, category, service_name, quantity, sale_price,
                    payment_mua, payment_stylist, trial_mua, trial_stylist, materials,
                    logistics, transfer_value, other_costs, total_cost, utility,
                    margin_percent, margin_status, observations
                ) values (
                    :import_run_id, :source_row, :active, :category, :service_name, :quantity, :sale_price,
                    :payment_mua, :payment_stylist, :trial_mua, :trial_stylist, :materials,
                    :logistics, :transfer_value, :other_costs, :total_cost, :utility,
                    :margin_percent, :margin_status, :observations
                )'
            )->execute([
                'import_run_id' => $runId,
                'source_row' => $sourceRow,
                'active' => $this->dbBool($this->bool($row, 'activo')),
                'category' => $this->string($row, 'categoria'),
                'service_name' => $serviceName,
                'quantity' => $this->positiveInt($row, 'cantidad') ?? 1,
                'sale_price' => $this->catalogMoney($row, 'precio venta'),
                'payment_mua' => $this->catalogMoney($row, 'pago mua'),
                'payment_stylist' => $this->catalogMoney($row, 'pago estilista'),
                'trial_mua' => $this->catalogMoney($row, 'prueba mua'),
                'trial_stylist' => $this->catalogMoney($row, 'prueba estilista'),
                'materials' => $this->catalogMoney($row, 'materiales'),
                'logistics' => $this->catalogMoney($row, 'traslado / logistica'),
                'transfer_value' => $this->catalogMoney($row, 'valor traslado'),
                'other_costs' => $this->catalogMoney($row, 'otros costos'),
                'total_cost' => $this->catalogMoney($row, 'costo total'),
                'utility' => $this->catalogMoney($row, 'utilidad'),
                'margin_percent' => $this->percent($row, 'margen %'),
                'margin_status' => $this->string($row, 'estado'),
                'observations' => $this->string($row, 'observaciones'),
            ]);

            $this->upsertServiceProjection($sheetName, $sourceRow, $row, $serviceName);
            $imported++;
        }

        return $imported;
    }

    private function importServicesMaster(int $runId, string $sheetName, array $rows): int
    {
        // Clear previous mappings for this sheet to prevent unique constraint violations when row numbers shift
        $this->connection->prepare('update qs_services set source_row = null, source_sheet = null where source_sheet = :sheet')
            ->execute(['sheet' => $sheetName]);

        [$headerIndex, $headers] = $this->findHeader($rows, ['service_id', 'nombre_canonico']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->combine($headers, $rows[$index]);
            $serviceId = $this->string($row, 'service_id');
            $serviceName = $this->string($row, 'nombre_canonico');
            if ($serviceId === null || $serviceName === null) {
                continue;
            }

            $sourceRow = $index + 1;
            $this->upsertMasterServiceProjection($sheetName, $sourceRow, $row, $serviceId, $serviceName);
            $imported++;
        }

        return $imported;
    }

    private function importAgendaValues(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['servicio', 'valor']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->combine($headers, $rows[$index]);
            $serviceName = $this->string($row, 'servicio');
            if ($serviceName === null) {
                continue;
            }

            $sourceRow = $index + 1;
            $this->connection->prepare(
                'insert into qs_sheet_agenda_value_rows (
                    import_run_id, source_row, service_name, makeup_cost, hair_cost,
                    trial_makeup_cost, trial_hair_cost, sale_price, profit, observations
                ) values (
                    :import_run_id, :source_row, :service_name, :makeup_cost, :hair_cost,
                    :trial_makeup_cost, :trial_hair_cost, :sale_price, :profit, :observations
                )'
            )->execute([
                'import_run_id' => $runId,
                'source_row' => $sourceRow,
                'service_name' => $serviceName,
                'makeup_cost' => $this->money($row, 'costo maquillaje'),
                'hair_cost' => $this->money($row, 'costo peinado'),
                'trial_makeup_cost' => $this->money($row, 'costo prueba maquillaje'),
                'trial_hair_cost' => $this->money($row, 'costo prueba peinado'),
                'sale_price' => $this->money($row, 'valor'),
                'profit' => $this->money($row, 'ganancia'),
                'observations' => $this->string($row, 'observaciones'),
            ]);

            $this->upsertAgendaServiceProjection($sheetName, $sourceRow, $row, $serviceName);
            $imported++;
        }

        return $imported;
    }

    private function importWorkshops(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['fecha', 'nombre', 'pago']);
        $imported = 0;
        $currentWorkshopDate = null;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->combine($headers, $rows[$index]);
            $rawValues = $rows[$index];
            $date = $this->date($row, 'fecha') ?? $currentWorkshopDate;
            if ($date !== null && $this->date($row, 'fecha') !== null) {
                $currentWorkshopDate = $date;
            }

            $customerName = $this->string($row, 'nombre');
            if ($customerName === null) {
                continue;
            }

            $notes = $this->workshopNotes($row, $rawValues);
            $this->connection->prepare(
                'insert into qs_sheet_workshop_rows (
                    import_run_id, source_row, workshop_date, customer_name, customer_phone,
                    payment_amount, payment_date, notes
                ) values (
                    :import_run_id, :source_row, :workshop_date, :customer_name, :customer_phone,
                    :payment_amount, :payment_date, :notes
                )'
            )->execute([
                'import_run_id' => $runId,
                'source_row' => $index + 1,
                'workshop_date' => $date,
                'customer_name' => $customerName,
                'customer_phone' => $this->string($row, 'numero'),
                'payment_amount' => $this->workshopPaymentAmount($row, $rawValues),
                'payment_date' => $this->date($row, 'fecha pago'),
                'notes' => $notes,
            ]);

            $imported++;
        }

        return $imported;
    }

    private function importAgendaMonth(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['encargada', 'fecha', 'servicio']);
        $imported = 0;

        $this->connection->prepare('delete from qs_bookings where source_sheet = :source_sheet')
            ->execute(['source_sheet' => $sheetName]);

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->combine($headers, $rows[$index]);
            $serviceName = $this->string($row, 'servicio');
            $customerName = $this->string($row, 'clienta');
            $serviceDate = $this->date($row, 'fecha');

            if ($serviceName === null && $customerName === null && $serviceDate === null) {
                continue;
            }

            $sourceRow = $index + 1;
            $financials = $this->agendaFinancials($row);
            $this->connection->prepare(
                'insert into qs_sheet_agenda_month_rows (
                    import_run_id, source_sheet, source_row, staff_name, day_label, service_date,
                    service_time, service_name, quantity, customer_name, customer_phone, address,
                    comuna, trial_date, trial_time, trial_status, transfer_value, deposit_amount,
                    deposit_date, service_value, total_service, balance_due, action, event_status,
                    calendar_event_id, payment_status
                ) values (
                    :import_run_id, :source_sheet, :source_row, :staff_name, :day_label, :service_date,
                    :service_time, :service_name, :quantity, :customer_name, :customer_phone, :address,
                    :comuna, :trial_date, :trial_time, :trial_status, :transfer_value, :deposit_amount,
                    :deposit_date, :service_value, :total_service, :balance_due, :action, :event_status,
                    :calendar_event_id, :payment_status
                )'
            )->execute([
                'import_run_id' => $runId,
                'source_sheet' => $sheetName,
                'source_row' => $sourceRow,
                'staff_name' => $this->string($row, 'encargada'),
                'day_label' => $this->string($row, 'dia'),
                'service_date' => $serviceDate,
                'service_time' => $this->time($row, 'hora'),
                'service_name' => $serviceName,
                'quantity' => $this->positiveInt($row, 'cantidad'),
                'customer_name' => $customerName,
                'customer_phone' => $this->string($row, 'telefono'),
                'address' => $this->string($row, 'direccion'),
                'comuna' => $this->string($row, 'comuna'),
                'trial_date' => $this->date($row, 'fecha prueba'),
                'trial_time' => $this->time($row, 'hora prueba'),
                'trial_status' => $this->string($row, 'estado prueba'),
                'transfer_value' => $this->money($row, 'traslado'),
                'deposit_amount' => $this->money($row, 'abono'),
                'deposit_date' => $this->date($row, 'fecha abono'),
                'service_value' => $financials['service_value'],
                'total_service' => $financials['total_service'],
                'balance_due' => $financials['balance_due'],
                'action' => $this->string($row, 'accion'),
                'event_status' => $this->string($row, 'estado evento'),
                'calendar_event_id' => $this->string($row, 'id evento'),
                'payment_status' => $financials['payment_status'],
            ]);

            if ($serviceName !== null && $customerName !== null) {
                $this->upsertAgendaBookingProjection($sheetName, $sourceRow, $row);
            }
            $imported++;
        }

        return $imported;
    }

    private function importBitacora(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['id', 'fecha', 'servicio']);
        $imported = 0;

        $this->connection->prepare('delete from qs_bookings where source_sheet = :source_sheet')
            ->execute(['source_sheet' => $sheetName]);

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->combine($headers, $rows[$index]);
            $externalId = $this->string($row, 'id');
            if ($externalId === null) {
                continue;
            }

            $sourceRow = $index + 1;
            $this->connection->prepare(
                'insert into qs_sheet_bitacora_rows (
                    import_run_id, source_row, qs_external_id, service_date, service_time, type,
                    staff_name, service_name, service_type, customer_name, comuna, transfer_value,
                    deposit_amount, service_value, total_service, balance_due, payment_method,
                    payment_status, service_status, observations, address, calendar_event_id,
                    agenda_reference, contract_id, milestone, cash_group, contract_status, contract_reserve
                ) values (
                    :import_run_id, :source_row, :qs_external_id, :service_date, :service_time, :type,
                    :staff_name, :service_name, :service_type, :customer_name, :comuna, :transfer_value,
                    :deposit_amount, :service_value, :total_service, :balance_due, :payment_method,
                    :payment_status, :service_status, :observations, :address, :calendar_event_id,
                    :agenda_reference, :contract_id, :milestone, :cash_group, :contract_status, :contract_reserve
                )'
            )->execute([
                'import_run_id' => $runId,
                'source_row' => $sourceRow,
                'qs_external_id' => $externalId,
                'service_date' => $this->date($row, 'fecha'),
                'service_time' => $this->time($row, 'hora'),
                'type' => $this->string($row, 'tipo'),
                'staff_name' => $this->string($row, 'encargada'),
                'service_name' => $this->string($row, 'servicio'),
                'service_type' => $this->string($row, 'tipo de servicio'),
                'customer_name' => $this->string($row, 'clienta'),
                'comuna' => $this->string($row, 'comuna'),
                'transfer_value' => $this->money($row, 'traslado'),
                'deposit_amount' => $this->money($row, 'abono'),
                'service_value' => $this->money($row, 'valor servicio'),
                'total_service' => $this->money($row, 'total servicio'),
                'balance_due' => $this->money($row, 'saldo'),
                'payment_method' => $this->string($row, 'forma de pago'),
                'payment_status' => $this->string($row, 'estado pago'),
                'service_status' => $this->string($row, 'estado servicio'),
                'observations' => $this->string($row, 'observaciones'),
                'address' => $this->string($row, 'direccion'),
                'calendar_event_id' => $this->string($row, 'id calendar'),
                'agenda_reference' => $this->string($row, 'referencia agenda'),
                'contract_id' => $this->string($row, 'id contrato'),
                'milestone' => $this->string($row, 'hito'),
                'cash_group' => $this->string($row, 'grupo caja'),
                'contract_status' => $this->string($row, 'estado contrato'),
                'contract_reserve' => $this->money($row, 'reserva contrato'),
            ]);

            $this->upsertBookingProjection($sheetName, $sourceRow, $row, $externalId);
            $imported++;
        }

        return $imported;
    }

    private function importCashTracking(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['id servicios', 'fecha', 'clienta']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->combine($headers, $rows[$index]);
            $externalId = $this->string($row, 'id servicios');
            if ($externalId === null) {
                continue;
            }

            $this->connection->prepare(
                'insert into qs_sheet_cash_tracking_rows (
                    import_run_id, source_row, service_external_id, service_date, service_names,
                    customer_name, comuna, deposit_amount, total_services, balance_due,
                    operating_expenses, payment_status, service_status
                ) values (
                    :import_run_id, :source_row, :service_external_id, :service_date, :service_names,
                    :customer_name, :comuna, :deposit_amount, :total_services, :balance_due,
                    :operating_expenses, :payment_status, :service_status
                )'
            )->execute([
                'import_run_id' => $runId,
                'source_row' => $index + 1,
                'service_external_id' => $externalId,
                'service_date' => $this->date($row, 'fecha'),
                'service_names' => $this->string($row, 'servicio(s)'),
                'customer_name' => $this->string($row, 'clienta'),
                'comuna' => $this->string($row, 'comuna'),
                'deposit_amount' => $this->money($row, 'abono reserva'),
                'total_services' => $this->money($row, 'total servicios'),
                'balance_due' => $this->money($row, 'saldo por cobrar'),
                'operating_expenses' => $this->money($row, 'gastos operativos'),
                'payment_status' => $this->string($row, 'estado pago'),
                'service_status' => $this->string($row, 'estado servicios'),
            ]);

            $imported++;
        }

        return $imported;
    }

    private function importOperationalExpenses(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->findHeader($rows, ['concepto', 'monto gasto ($)']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->combine($headers, $rows[$index]);
            $concept = $this->string($row, 'concepto');
            $amount = $this->money($row, 'monto gasto ($)');
            if ($concept === null && $amount === null) {
                continue;
            }

            $this->connection->prepare(
                'insert into qs_sheet_operational_expense_rows (
                    import_run_id, source_row, selected_service, expense_external_id, contract_id,
                    service_type, service_status, expense_date, concept, amount, observations,
                    expense_status, customer_name, service_name
                ) values (
                    :import_run_id, :source_row, :selected_service, :expense_external_id, :contract_id,
                    :service_type, :service_status, :expense_date, :concept, :amount, :observations,
                    :expense_status, :customer_name, :service_name
                )'
            )->execute([
                'import_run_id' => $runId,
                'source_row' => $index + 1,
                'selected_service' => $this->string($row, 'seleccionar servicio'),
                'expense_external_id' => $this->string($row, 'id'),
                'contract_id' => $this->string($row, 'id contrato'),
                'service_type' => $this->string($row, 'tipo de servicio'),
                'service_status' => $this->string($row, 'estado servicio'),
                'expense_date' => $this->date($row, 'fecha gasto'),
                'concept' => $concept,
                'amount' => $amount,
                'observations' => $this->string($row, 'observaciones'),
                'expense_status' => $this->string($row, 'estado gasto'),
                'customer_name' => $this->string($row, 'clienta'),
                'service_name' => $this->string($row, 'servicio'),
            ]);

            $imported++;
        }

        return $imported;
    }

    private function upsertServiceProjection(string $sheetName, int $sourceRow, array $row, string $serviceName): void
    {
        $this->connection->prepare(
            'insert into qs_services (
                name, category, duration_minutes, quantity, active, sale_price, total_cost, utility,
                margin_percent, margin_status, source_sheet, source_row
            ) values (
                :name, :category, null, :quantity, :active, :sale_price, :total_cost, :utility,
                :margin_percent, :margin_status, :source_sheet, :source_row
            )
            on conflict (source_sheet, source_row) where source_sheet is not null and source_row is not null
            do update set
                name = excluded.name,
                category = excluded.category,
                quantity = excluded.quantity,
                active = excluded.active,
                sale_price = excluded.sale_price,
                total_cost = excluded.total_cost,
                utility = excluded.utility,
                margin_percent = excluded.margin_percent,
                margin_status = excluded.margin_status'
        )->execute([
            'name' => $serviceName,
            'category' => $this->string($row, 'categoria'),
            'quantity' => $this->positiveInt($row, 'cantidad') ?? 1,
            'active' => $this->dbBool($this->bool($row, 'activo') ?? true),
            'sale_price' => $this->catalogMoney($row, 'precio venta'),
            'total_cost' => $this->catalogMoney($row, 'costo total'),
            'utility' => $this->catalogMoney($row, 'utilidad'),
            'margin_percent' => $this->percent($row, 'margen %'),
            'margin_status' => $this->string($row, 'estado'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    private function upsertAgendaServiceProjection(string $sheetName, int $sourceRow, array $row, string $serviceName): void
    {
        $this->connection->prepare(
            'insert into qs_services (
                name, category, duration_minutes, active, sale_price, total_cost, utility,
                source_sheet, source_row
            ) values (
                :name, :category, null, true, :sale_price, :total_cost, :utility,
                :source_sheet, :source_row
            )
            on conflict (source_sheet, source_row) where source_sheet is not null and source_row is not null
            do update set
                name = excluded.name,
                category = excluded.category,
                sale_price = excluded.sale_price,
                total_cost = excluded.total_cost,
                utility = excluded.utility'
        )->execute([
            'name' => $serviceName,
            'category' => 'agenda',
            'sale_price' => $this->money($row, 'valor'),
            'total_cost' => $this->sumMoney($row, [
                'costo maquillaje',
                'costo peinado',
                'costo prueba maquillaje',
                'costo prueba peinado',
            ]),
            'utility' => $this->money($row, 'ganancia'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    private function upsertMasterServiceProjection(
        string $sheetName,
        int $sourceRow,
        array $row,
        string $serviceId,
        string $serviceName,
    ): void {
        $statement = $this->connection->prepare(
            'update qs_services
             set sheet_external_id = :sheet_external_id,
                 category = :category,
                 quantity = :quantity,
                 active = :active,
                 sale_price = :sale_price,
                 total_cost = :total_cost,
                 utility = :utility,
                 margin_percent = :margin_percent,
                 margin_status = :margin_status,
                 source_sheet = :source_sheet,
                 source_row = :source_row
             where id = (
                 select id from qs_services
                 where lower(trim(name)) = lower(trim(:name))
                 order by case when source_sheet = :source_sheet then 0 else 1 end, id asc
                 limit 1
             )
             returning id'
        );

        $statement->execute([
            'sheet_external_id' => $serviceId,
            'name' => $serviceName,
            'category' => $this->string($row, 'categoria'),
            'quantity' => $this->positiveInt($row, 'cantidad') ?? 1,
            'active' => $this->dbBool($this->bool($row, 'activo') ?? true),
            'sale_price' => $this->money($row, 'precio_venta_clp'),
            'total_cost' => $this->money($row, 'costo_total_clp'),
            'utility' => $this->money($row, 'utilidad_clp'),
            'margin_percent' => $this->percent($row, 'margen'),
            'margin_status' => $this->string($row, 'estado_margen'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);

        if ($statement->fetchColumn() !== false) {
            return;
        }

        $this->connection->prepare(
            'insert into qs_services (
                sheet_external_id, name, category, duration_minutes, quantity, active, sale_price, total_cost,
                utility, margin_percent, margin_status, source_sheet, source_row
            ) values (
                :sheet_external_id, :name, :category, null, :quantity, :active, :sale_price, :total_cost,
                :utility, :margin_percent, :margin_status, :source_sheet, :source_row
            ) on conflict (source_sheet, source_row) where source_sheet is not null and source_row is not null do update set
                sheet_external_id = excluded.sheet_external_id,
                name = excluded.name,
                category = excluded.category,
                quantity = excluded.quantity,
                active = excluded.active,
                sale_price = excluded.sale_price,
                total_cost = excluded.total_cost,
                utility = excluded.utility,
                margin_percent = excluded.margin_percent,
                margin_status = excluded.margin_status'
        )->execute([
            'sheet_external_id' => $serviceId,
            'name' => $serviceName,
            'category' => $this->string($row, 'categoria'),
            'quantity' => $this->positiveInt($row, 'cantidad') ?? 1,
            'active' => $this->dbBool($this->bool($row, 'activo') ?? true),
            'sale_price' => $this->money($row, 'precio_venta_clp'),
            'total_cost' => $this->money($row, 'costo_total_clp'),
            'utility' => $this->money($row, 'utilidad_clp'),
            'margin_percent' => $this->percent($row, 'margen'),
            'margin_status' => $this->string($row, 'estado_margen'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
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
             set service_id = catalog_services.id
             from qs_services values_services,
                  qs_services catalog_services
             where b.service_id = values_services.id
               and values_services.source_sheet = 'Valores'
               and catalog_services.source_sheet in ('Servicios_Master', 'Servicios')
               and lower(trim(values_services.name)) = lower(trim(catalog_services.name))"
        );

        $this->connection->exec(
            "update qs_bookings b
             set service_id = master_services.id
             from qs_services old_services,
                  qs_services master_services
             where b.service_id = old_services.id
               and old_services.source_sheet in ('Valores', 'Servicios')
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
            "delete from qs_services values_services
             using qs_services catalog_services
             where values_services.source_sheet = 'Valores'
               and catalog_services.source_sheet in ('Servicios_Master', 'Servicios')
               and lower(trim(values_services.name)) = lower(trim(catalog_services.name))"
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

    private function upsertBookingProjection(string $sheetName, int $sourceRow, array $row, string $externalId): void
    {
        $scheduledFor = $this->scheduledFor($row);
        $serviceId = $this->serviceIdByName($this->string($row, 'servicio'));

        $this->connection->prepare(
            'insert into qs_bookings (
                service_id, staff_id, customer_name, customer_phone, scheduled_for, status,
                address, comuna, service_value, transfer_value, deposit_amount, total_service,
                balance_due, payment_status, service_status, contract_id, milestone, cash_group,
                calendar_event_id, agenda_reference, sheet_external_id, source_sheet, source_row,
                sheets_last_import_at
            ) values (
                :service_id, null, :customer_name, null, :scheduled_for, :status,
                :address, :comuna, :service_value, :transfer_value, :deposit_amount, :total_service,
                :balance_due, :payment_status, :service_status, :contract_id, :milestone, :cash_group,
                :calendar_event_id, :agenda_reference, :sheet_external_id, :source_sheet, :source_row,
                now()
            )
            on conflict (sheet_external_id) where sheet_external_id is not null
            do update set
                service_id = excluded.service_id,
                customer_name = excluded.customer_name,
                scheduled_for = excluded.scheduled_for,
                status = excluded.status,
                address = excluded.address,
                comuna = excluded.comuna,
                service_value = excluded.service_value,
                transfer_value = excluded.transfer_value,
                deposit_amount = excluded.deposit_amount,
                total_service = excluded.total_service,
                balance_due = excluded.balance_due,
                payment_status = excluded.payment_status,
                service_status = excluded.service_status,
                contract_id = excluded.contract_id,
                milestone = excluded.milestone,
                cash_group = excluded.cash_group,
                calendar_event_id = excluded.calendar_event_id,
                agenda_reference = excluded.agenda_reference,
                source_sheet = excluded.source_sheet,
                source_row = excluded.source_row,
                sheets_last_import_at = now()'
        )->execute([
            'service_id' => $serviceId,
            'customer_name' => $this->string($row, 'clienta'),
            'scheduled_for' => $scheduledFor,
            'status' => $this->bookingStatus($this->string($row, 'estado servicio')),
            'address' => $this->string($row, 'direccion'),
            'comuna' => $this->string($row, 'comuna'),
            'service_value' => $this->money($row, 'valor servicio'),
            'transfer_value' => $this->money($row, 'traslado'),
            'deposit_amount' => $this->money($row, 'abono'),
            'total_service' => $this->money($row, 'total servicio'),
            'balance_due' => $this->money($row, 'saldo'),
            'payment_status' => $this->string($row, 'estado pago'),
            'service_status' => $this->string($row, 'estado servicio'),
            'contract_id' => $this->string($row, 'id contrato'),
            'milestone' => $this->string($row, 'hito'),
            'cash_group' => $this->string($row, 'grupo caja'),
            'calendar_event_id' => $this->string($row, 'id calendar'),
            'agenda_reference' => $this->string($row, 'referencia agenda'),
            'sheet_external_id' => $externalId,
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    private function upsertAgendaBookingProjection(string $sheetName, int $sourceRow, array $row): void
    {
        $scheduledFor = $this->scheduledFor($row);
        $serviceName = $this->string($row, 'servicio');
        $financials = $this->agendaFinancials($row);

        $this->connection->prepare(
            'insert into qs_bookings (
                service_id, staff_id, customer_name, customer_phone, scheduled_for, status,
                address, comuna, service_value, transfer_value, deposit_amount, total_service,
                balance_due, payment_status, service_status, calendar_event_id, source_sheet,
                source_row, sheets_last_import_at
            ) values (
                :service_id, null, :customer_name, :customer_phone, :scheduled_for, :status,
                :address, :comuna, :service_value, :transfer_value, :deposit_amount, :total_service,
                :balance_due, :payment_status, :service_status, :calendar_event_id, :source_sheet,
                :source_row, now()
            )
            on conflict (source_sheet, source_row) where source_sheet is not null and source_row is not null
            do update set
                service_id = excluded.service_id,
                customer_name = excluded.customer_name,
                customer_phone = excluded.customer_phone,
                scheduled_for = excluded.scheduled_for,
                status = excluded.status,
                address = excluded.address,
                comuna = excluded.comuna,
                service_value = excluded.service_value,
                transfer_value = excluded.transfer_value,
                deposit_amount = excluded.deposit_amount,
                total_service = excluded.total_service,
                balance_due = excluded.balance_due,
                payment_status = excluded.payment_status,
                service_status = excluded.service_status,
                calendar_event_id = excluded.calendar_event_id,
                sheets_last_import_at = now()'
        )->execute([
            'service_id' => $this->serviceIdByName($serviceName),
            'customer_name' => $this->string($row, 'clienta'),
            'customer_phone' => $this->string($row, 'telefono'),
            'scheduled_for' => $scheduledFor,
            'status' => $this->bookingStatus($this->string($row, 'estado evento')),
            'address' => $this->string($row, 'direccion'),
            'comuna' => $this->string($row, 'comuna'),
            'service_value' => $financials['service_value'],
            'transfer_value' => $this->money($row, 'traslado'),
            'deposit_amount' => $this->money($row, 'abono'),
            'total_service' => $financials['total_service'],
            'balance_due' => $financials['balance_due'],
            'payment_status' => $financials['payment_status'],
            'service_status' => $this->string($row, 'estado evento'),
            'calendar_event_id' => $this->string($row, 'id evento'),
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    private function upsertWorkshopBookingProjection(
        string $sheetName,
        int $sourceRow,
        array $row,
        array $rawValues,
        string $workshopDate,
        ?string $notes,
    ): void {
        $paymentAmount = $this->workshopPaymentAmount($row, $rawValues);
        $serviceName = $this->inferWorkshopServiceName($notes);

        $this->connection->prepare(
            'insert into qs_bookings (
                service_id, staff_id, customer_name, customer_phone, scheduled_for, status,
                address, comuna, service_value, transfer_value, deposit_amount, total_service,
                balance_due, payment_status, service_status, milestone, cash_group,
                agenda_reference, source_sheet, source_row, sheets_last_import_at
            ) values (
                :service_id, null, :customer_name, :customer_phone, :scheduled_for, :status,
                null, null, null, null, :deposit_amount, :total_service,
                null, :payment_status, :service_status, :milestone, :cash_group,
                :agenda_reference, :source_sheet, :source_row, now()
            )
            on conflict (source_sheet, source_row) where source_sheet is not null and source_row is not null
            do update set
                service_id = excluded.service_id,
                customer_name = excluded.customer_name,
                customer_phone = excluded.customer_phone,
                scheduled_for = excluded.scheduled_for,
                status = excluded.status,
                deposit_amount = excluded.deposit_amount,
                total_service = excluded.total_service,
                payment_status = excluded.payment_status,
                service_status = excluded.service_status,
                milestone = excluded.milestone,
                cash_group = excluded.cash_group,
                agenda_reference = excluded.agenda_reference,
                sheets_last_import_at = now()'
        )->execute([
            'service_id' => $this->serviceIdByName($serviceName),
            'customer_name' => $this->string($row, 'nombre'),
            'customer_phone' => $this->normalizePhone($this->string($row, 'numero')),
            'scheduled_for' => $this->localTimestamp($workshopDate, '00:00:00'),
            'status' => 'confirmed',
            'deposit_amount' => $paymentAmount,
            'total_service' => $paymentAmount,
            'payment_status' => $paymentAmount !== null && $paymentAmount > 0 ? 'parcial' : 'pendiente',
            'service_status' => 'agendado',
            'milestone' => 'taller',
            'cash_group' => 'talleres',
            'agenda_reference' => $notes,
            'source_sheet' => $sheetName,
            'source_row' => $sourceRow,
        ]);
    }

    private function sourceId(string $sheetName, array $source): int
    {
        $this->connection->prepare(
            'insert into qs_sheet_sources (spreadsheet_id, spreadsheet_title, sheet_name, sheet_gid, purpose)
             values (:spreadsheet_id, :spreadsheet_title, :sheet_name, :sheet_gid, :purpose)
             on conflict (spreadsheet_id, sheet_name) do nothing'
        )->execute([
            'spreadsheet_id' => $source['spreadsheet_id'],
            'spreadsheet_title' => $source['spreadsheet_id'] === self::BITACORA_SPREADSHEET_ID
                ? 'Bitácora QS — Servicios'
                : ($source['spreadsheet_id'] === self::AGENDA_2026_SPREADSHEET_ID
                    ? 'Agenda 2026'
                    : 'Seguimiento Contable - Margen por Servicio'),
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
    }

    private function findHeader(array $rows, array $required): array
    {
        foreach ($rows as $index => $row) {
            $headers = array_map(fn (string $value): string => $this->normalizeKey($value), $row);
            $found = array_filter($required, static fn (string $field): bool => in_array($field, $headers, true));
            if (count($found) === count($required)) {
                return [$index, $headers];
            }
        }

        throw new \RuntimeException('Could not find expected header row: ' . implode(', ', $required));
    }

    private function combine(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = $values[$index] ?? '';
        }

        return $row;
    }

    private function serviceIdByName(?string $serviceName): ?int
    {
        if ($serviceName === null) {
            return null;
        }

        $statement = $this->connection->prepare('select id from qs_services where lower(name) = lower(:name) order by id asc limit 1');
        $statement->execute(['name' => $serviceName]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function scheduledFor(array $row): ?string
    {
        $date = $this->date($row, 'fecha');
        if ($date === null) {
            return null;
        }

        $time = $this->time($row, 'hora') ?? '00:00:00';

        return $this->localTimestamp($date, $time);
    }

    /**
     * Chile alterna entre -04 (invierno) y -03 (verano); un offset fijo
     * desplaza una hora las reservas de la temporada contraria.
     */
    private function localTimestamp(string $date, string $time): string
    {
        $local = new \DateTimeImmutable($date . ' ' . $time, new \DateTimeZone('America/Santiago'));

        return $local->format('Y-m-d H:i:sP');
    }

    private function bookingStatus(?string $sheetStatus): string
    {
        $status = strtolower($sheetStatus ?? '');

        return match (true) {
            str_contains($status, 'cancel') => 'cancelled',
            str_contains($status, 'complet'), str_contains($status, 'realiz'), str_contains($status, 'termin') => 'completed',
            str_contains($status, 'agend'), str_contains($status, 'confirm') => 'confirmed',
            default => 'draft',
        };
    }

    private function string(array $row, string $key): ?string
    {
        $value = trim((string) ($row[$this->normalizeKey($key)] ?? ''));

        return $value === '' ? null : $value;
    }

    private function bool(array $row, string $key): ?bool
    {
        $value = strtolower((string) ($row[$this->normalizeKey($key)] ?? ''));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['1', 'true', 'si', 'sí', 'yes', 'activo', 'x'], true);
    }

    private function dbBool(?bool $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }

    private function money(array $row, string $key): ?float
    {
        $value = $this->string($row, $key);
        if ($value === null) {
            return null;
        }

        $clean = str_replace(['$', ' ', "\u{00A0}", '.'], '', $value);
        $clean = str_replace(',', '.', $clean);

        $result = is_numeric($clean) ? (float) $clean : null;

        if ($result !== null && $result > 0 && $result < 1000) {
            $result *= 1000;
        }

        return $result;
    }

    private function catalogMoney(array $row, string $key): ?float
    {
        return $this->money($row, $key);
    }

    private function sumMoney(array $row, array $keys): ?float
    {
        $total = 0.0;
        $hasValue = false;

        foreach ($keys as $key) {
            $value = $this->money($row, $key);
            if ($value !== null) {
                $total += $value;
                $hasValue = true;
            }
        }

        return $hasValue ? $total : null;
    }

    private function positiveInt(array $row, string $key): ?int
    {
        $value = $this->string($row, $key);
        if ($value === null || !ctype_digit($value)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    private function percent(array $row, string $key): ?float
    {
        $value = $this->string($row, $key);
        if ($value === null) {
            return null;
        }

        $clean = str_replace(['%', ' '], '', $value);
        $clean = str_replace(',', '.', $clean);

        if (!is_numeric($clean)) {
            return null;
        }

        $number = (float) $clean;

        return $number > 1 ? $number / 100 : $number;
    }

    private function date(array $row, string $key): ?string
    {
        $value = $this->string($row, $key);
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function time(array $row, string $key): ?string
    {
        $value = $this->string($row, $key);
        if ($value === null) {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'G:i'] as $format) {
            $time = \DateTimeImmutable::createFromFormat($format, $value);
            if ($time !== false) {
                return $time->format('H:i:s');
            }
        }

        return null;
    }

    /**
     * @return array{service_value: ?float, total_service: ?float, balance_due: ?float, payment_status: ?string}
     */
    private function agendaFinancials(array $row): array
    {
        $serviceValue = $this->money($row, 'valor servicio');
        $transferValue = $this->money($row, 'traslado');
        $depositAmount = $this->money($row, 'abono');
        $totalService = $this->money($row, 'total servicio');

        if ($totalService === null && ($serviceValue !== null || $transferValue !== null)) {
            $totalService = ($serviceValue ?? 0.0) + ($transferValue ?? 0.0);
        }

        $balanceDue = $this->money($row, 'total por pagar');
        if ($balanceDue === null && $totalService !== null) {
            $balanceDue = max(0.0, $totalService - ($depositAmount ?? 0.0));
        }

        $paymentStatus = $this->string($row, 'estado pago');
        $isWorkshop = $this->normalizeKey($this->string($row, 'dia') ?? '') === 'taller';
        if (($paymentStatus === null || $isWorkshop) && $totalService !== null) {
            $paymentStatus = ($depositAmount ?? 0.0) >= $totalService
                ? 'Pagado'
                : (($depositAmount ?? 0.0) > 0.0 ? 'Parcial' : 'Pendiente');
        }

        return [
            'service_value' => $serviceValue,
            'total_service' => $totalService,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
        ];
    }

    private function workshopNotes(array $row, array $rawValues): ?string
    {
        $notes = [];
        $rawPayment = $this->string($row, 'pago');
        $rawPaymentDate = $this->string($row, 'fecha pago');

        if ($rawPayment !== null && $this->money($row, 'pago') === null) {
            $notes[] = 'Pago: ' . $rawPayment;
        }

        if ($rawPaymentDate !== null && $this->date($row, 'fecha pago') === null) {
            $notes[] = 'Fecha/Pago: ' . $rawPaymentDate;
        }

        foreach (array_slice($rawValues, 5) as $index => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $notes[] = 'Extra ' . ($index + 6) . ': ' . $value;
            }
        }

        return $notes === [] ? null : implode(' | ', $notes);
    }

    private function workshopPaymentAmount(array $row, array $rawValues): ?float
    {
        $direct = $this->money($row, 'pago');
        if ($direct !== null) {
            return $direct;
        }

        $candidates = [$this->string($row, 'fecha pago')];
        foreach (array_slice($rawValues, 5) as $value) {
            $candidates[] = trim((string) $value);
        }

        foreach ($candidates as $candidate) {
            $amount = $this->looseMoneyFromText($candidate);
            if ($amount !== null) {
                return $amount;
            }
        }

        return null;
    }

    private function looseMoneyFromText(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mil\b/u', $normalized, $matches) === 1) {
            return (float) str_replace(',', '.', $matches[1]) * 1000;
        }

        if (preg_match('/pagad[oa]?\s+(\d{1,3})(?![\d.,])/u', $normalized, $matches) === 1) {
            return (float) $matches[1] * 1000;
        }

        return null;
    }

    private function inferWorkshopServiceName(?string $notes): string
    {
        $value = strtolower($notes ?? '');

        if (str_contains($value, 'peinado') || str_contains($value, 'autopeinado')) {
            return 'Taller Autopeinado grupal';
        }

        return 'Taller Automaquillaje grupal';
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $clean = preg_replace('/[^\d+]/', '', $phone) ?? '';

        return $clean === '' ? null : $clean;
    }

    private function normalizeKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }
}
