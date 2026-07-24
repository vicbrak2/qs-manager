<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets\Importers;

use PDO;
use QSManager\Infrastructure\Sheets\BookingProjectionWriter;
use QSManager\Infrastructure\Sheets\SheetRowMapper;

/**
 * Importa "Servicios_Master" (catalogo maestro/autoritativo de servicios).
 * Extraido de PostgresSheetReplicaImporter::importServicesMaster (Fase 4).
 */
final class ServicesMasterImporter
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
        // Clear previous mappings for this sheet to prevent unique constraint violations when row numbers shift
        $this->connection->prepare('update qs_services set source_row = null, source_sheet = null where source_sheet = :sheet')
            ->execute(['sheet' => $sheetName]);

        [$headerIndex, $headers] = $this->mapper->findHeader($rows, ['service_id', 'nombre_canonico']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->mapper->combine($headers, $rows[$index]);
            $serviceId = $this->mapper->string($row, 'service_id');
            $serviceName = $this->mapper->string($row, 'nombre_canonico');
            if ($serviceId === null || $serviceName === null) {
                continue;
            }

            $sourceRow = $index + 1;
            $this->projections->upsertMasterServiceProjection($sheetName, $sourceRow, $row, $serviceId, $serviceName);
            $imported++;
        }

        return $imported;
    }
}
