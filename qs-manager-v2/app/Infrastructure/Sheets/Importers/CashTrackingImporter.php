<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets\Importers;

use PDO;
use QSManager\Infrastructure\Sheets\SheetRowMapper;

/**
 * Importa "Seguimiento Caja". Extraido de
 * PostgresSheetReplicaImporter::importCashTracking (Fase 4).
 */
final class CashTrackingImporter
{
    public function __construct(
        private readonly PDO $connection,
        private readonly SheetRowMapper $mapper,
    ) {
    }

    /**
     * @param list<list<mixed>> $rows
     */
    public function import(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->mapper->findHeader($rows, ['id servicios', 'fecha', 'clienta']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            if ($this->mapper->isCashTrackingFooterRow($rows[$index])) {
                break;
            }
            $row = $this->mapper->combine($headers, $rows[$index]);
            $externalId = $this->mapper->string($row, 'id servicios');
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
                'service_date' => $this->mapper->date($row, 'fecha'),
                'service_names' => $this->mapper->string($row, 'servicio(s)'),
                'customer_name' => $this->mapper->string($row, 'clienta'),
                'comuna' => $this->mapper->string($row, 'comuna'),
                'deposit_amount' => $this->mapper->money($row, 'abono reserva'),
                'total_services' => $this->mapper->money($row, 'total servicios'),
                'balance_due' => $this->mapper->money($row, 'saldo por cobrar'),
                'operating_expenses' => $this->mapper->money($row, 'gastos operativos'),
                'payment_status' => $this->mapper->string($row, 'estado pago'),
                'service_status' => $this->mapper->string($row, 'estado servicios'),
            ]);

            $imported++;
        }

        return $imported;
    }
}
