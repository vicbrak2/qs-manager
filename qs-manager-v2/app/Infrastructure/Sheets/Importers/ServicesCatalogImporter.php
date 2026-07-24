<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets\Importers;

use PDO;
use QSManager\Infrastructure\Sheets\BookingProjectionWriter;
use QSManager\Infrastructure\Sheets\SheetRowMapper;

/**
 * Importa la hoja "Servicios" (catalogo de servicios "vivo", distinto de
 * Servicios_Master). Extraido de PostgresSheetReplicaImporter::importServices
 * (Fase 4). Mismo SQL y logica exactos.
 */
final class ServicesCatalogImporter
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
        [$headerIndex, $headers] = $this->mapper->findHeader($rows, ['servicio', 'precio venta']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->mapper->combine($headers, $rows[$index]);
            $serviceName = $this->mapper->string($row, 'servicio');
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
                'active' => $this->mapper->dbBool($this->mapper->bool($row, 'activo')),
                'category' => $this->mapper->string($row, 'categoria'),
                'service_name' => $serviceName,
                'quantity' => $this->mapper->positiveInt($row, 'cantidad') ?? 1,
                'sale_price' => $this->mapper->catalogMoney($row, 'precio venta'),
                'payment_mua' => $this->mapper->catalogMoney($row, 'pago mua'),
                'payment_stylist' => $this->mapper->catalogMoney($row, 'pago estilista'),
                'trial_mua' => $this->mapper->catalogMoney($row, 'prueba mua'),
                'trial_stylist' => $this->mapper->catalogMoney($row, 'prueba estilista'),
                'materials' => $this->mapper->catalogMoney($row, 'materiales'),
                'logistics' => $this->mapper->catalogMoney($row, 'traslado / logistica'),
                'transfer_value' => $this->mapper->catalogMoney($row, 'valor traslado'),
                'other_costs' => $this->mapper->catalogMoney($row, 'otros costos'),
                'total_cost' => $this->mapper->catalogMoney($row, 'costo total'),
                'utility' => $this->mapper->catalogMoney($row, 'utilidad'),
                'margin_percent' => $this->mapper->percent($row, 'margen %'),
                'margin_status' => $this->mapper->string($row, 'estado'),
                'observations' => $this->mapper->string($row, 'observaciones'),
            ]);

            $this->projections->upsertServiceProjection($sheetName, $sourceRow, $row, $serviceName);
            $imported++;
        }

        return $imported;
    }
}
