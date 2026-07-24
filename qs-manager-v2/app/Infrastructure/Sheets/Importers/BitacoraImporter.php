<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets\Importers;

use PDO;
use QSManager\Domain\Finance\PaymentMethod;
use QSManager\Infrastructure\Sheets\BookingProjectionWriter;
use QSManager\Infrastructure\Sheets\SheetRowMapper;

/**
 * Importa "Bitácora QS — Servicios". Extraido de
 * PostgresSheetReplicaImporter::importBitacora (Fase 4).
 */
final class BitacoraImporter
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
        [$headerIndex, $headers] = $this->mapper->findHeader($rows, ['id', 'fecha', 'servicio']);
        $imported = 0;

        $this->connection->prepare('delete from qs_bookings where source_sheet = :source_sheet')
            ->execute(['source_sheet' => $sheetName]);

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->mapper->combine($headers, $rows[$index]);
            $externalId = $this->mapper->string($row, 'id');
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
                'service_date' => $this->mapper->date($row, 'fecha'),
                'service_time' => $this->mapper->time($row, 'hora'),
                'type' => $this->mapper->string($row, 'tipo'),
                'staff_name' => $this->mapper->string($row, 'encargada'),
                'service_name' => $this->mapper->string($row, 'servicio'),
                'service_type' => $this->mapper->string($row, 'tipo de servicio'),
                'customer_name' => $this->mapper->string($row, 'clienta'),
                'comuna' => $this->mapper->string($row, 'comuna'),
                'transfer_value' => $this->mapper->money($row, 'traslado'),
                'deposit_amount' => $this->mapper->money($row, 'abono'),
                'service_value' => $this->mapper->money($row, 'valor servicio'),
                'total_service' => $this->mapper->money($row, 'total servicio'),
                'balance_due' => $this->mapper->money($row, 'saldo'),
                'payment_method' => PaymentMethod::fromNullable($this->mapper->string($row, 'forma de pago'))->value,
                'payment_status' => $this->mapper->string($row, 'estado pago'),
                'service_status' => $this->mapper->string($row, 'estado servicio'),
                'observations' => $this->mapper->string($row, 'observaciones'),
                'address' => $this->mapper->string($row, 'direccion'),
                'calendar_event_id' => $this->mapper->string($row, 'id calendar'),
                'agenda_reference' => $this->mapper->string($row, 'referencia agenda'),
                'contract_id' => $this->mapper->string($row, 'id contrato'),
                'milestone' => $this->mapper->string($row, 'hito'),
                'cash_group' => $this->mapper->string($row, 'grupo caja'),
                'contract_status' => $this->mapper->string($row, 'estado contrato'),
                'contract_reserve' => $this->mapper->money($row, 'reserva contrato'),
            ]);

            if ($this->mapper->shouldProjectBitacoraBooking($row)) {
                $this->projections->upsertBookingProjection($sheetName, $sourceRow, $row, $externalId);
            }
            $imported++;
        }

        return $imported;
    }
}
