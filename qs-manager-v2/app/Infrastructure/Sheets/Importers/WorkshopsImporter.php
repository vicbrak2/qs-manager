<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets\Importers;

use PDO;
use QSManager\Infrastructure\Sheets\SheetRowMapper;

/**
 * Importa "Talleres". Extraido de PostgresSheetReplicaImporter::importWorkshops
 * (Fase 4). No proyecta a qs_bookings: los talleres proyectaban a bookings
 * hasta el commit 03fe53d, donde esa llamada se elimino deliberadamente
 * (quedan solo en su tabla replica qs_sheet_workshop_rows).
 */
final class WorkshopsImporter
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
        [$headerIndex, $headers] = $this->mapper->findHeader($rows, ['fecha', 'nombre', 'pago']);
        $imported = 0;
        $currentWorkshopDate = null;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->mapper->combine($headers, $rows[$index]);
            $rawValues = $rows[$index];
            $date = $this->mapper->date($row, 'fecha') ?? $currentWorkshopDate;
            if ($date !== null && $this->mapper->date($row, 'fecha') !== null) {
                $currentWorkshopDate = $date;
            }

            $customerName = $this->mapper->string($row, 'nombre');
            if ($customerName === null) {
                continue;
            }

            $notes = $this->mapper->workshopNotes($row, $rawValues);
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
                'customer_phone' => $this->mapper->string($row, 'numero'),
                'payment_amount' => $this->mapper->workshopPaymentAmount($row, $rawValues),
                'payment_date' => $this->mapper->date($row, 'fecha pago'),
                'notes' => $notes,
            ]);

            $imported++;
        }

        return $imported;
    }
}
