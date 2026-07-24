<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets\Importers;

use PDO;
use QSManager\Infrastructure\Sheets\BookingProjectionWriter;
use QSManager\Infrastructure\Sheets\SheetRowMapper;

/**
 * Importa una hoja mensual de Agenda 2026 (Enero..Diciembre). Extraido de
 * PostgresSheetReplicaImporter::importAgendaMonth (Fase 4).
 */
final class AgendaMonthImporter
{
    public function __construct(
        private readonly PDO $connection,
        private readonly SheetRowMapper $mapper,
        private readonly BookingProjectionWriter $projections,
    ) {
    }

    /**
     * @param list<list<mixed>> $rows
     */
    public function import(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->mapper->findHeader($rows, ['encargada', 'fecha', 'servicio']);
        $imported = 0;

        $this->connection->prepare('delete from qs_bookings where source_sheet = :source_sheet')
            ->execute(['source_sheet' => $sheetName]);

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->mapper->combine($headers, $rows[$index]);
            $serviceName = $this->mapper->string($row, 'servicio');
            $customerName = $this->mapper->string($row, 'clienta');
            $serviceDate = $this->mapper->date($row, 'fecha');

            if ($serviceName === null && $customerName === null && $serviceDate === null) {
                continue;
            }

            $sourceRow = $index + 1;
            $financials = $this->mapper->agendaFinancials($row);
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
                'staff_name' => $this->mapper->string($row, 'encargada'),
                'day_label' => $this->mapper->string($row, 'dia'),
                'service_date' => $serviceDate,
                'service_time' => $this->mapper->time($row, 'hora'),
                'service_name' => $serviceName,
                'quantity' => $this->mapper->positiveInt($row, 'cantidad'),
                'customer_name' => $customerName,
                'customer_phone' => $this->mapper->string($row, 'telefono'),
                'address' => $this->mapper->string($row, 'direccion'),
                'comuna' => $this->mapper->string($row, 'comuna'),
                'trial_date' => $this->mapper->date($row, 'fecha prueba'),
                'trial_time' => $this->mapper->time($row, 'hora prueba'),
                'trial_status' => $this->mapper->string($row, 'estado prueba'),
                'transfer_value' => $this->mapper->money($row, 'traslado'),
                'deposit_amount' => $this->mapper->money($row, 'abono'),
                'deposit_date' => $this->mapper->date($row, 'fecha abono'),
                'service_value' => $financials['service_value'],
                'total_service' => $financials['total_service'],
                'balance_due' => $financials['balance_due'],
                'action' => $this->mapper->string($row, 'accion'),
                'event_status' => $this->mapper->string($row, 'estado evento'),
                'calendar_event_id' => $this->mapper->string($row, 'id evento'),
                'payment_status' => $financials['payment_status'],
            ]);

            if ($serviceName !== null && $customerName !== null) {
                $this->projections->upsertAgendaBookingProjection($sheetName, $sourceRow, $row);
            }
            $imported++;
        }

        return $imported;
    }
}
