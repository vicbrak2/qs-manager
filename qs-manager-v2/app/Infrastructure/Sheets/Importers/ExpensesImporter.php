<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets\Importers;

use PDO;
use QSManager\Infrastructure\Sheets\SheetRowMapper;

/**
 * Importa "Gastos Operativos" y "Gastos_Fijos" -- dos hojas distintas pero
 * agrupadas en un solo importer porque el plan de migracion (Fase 4) lista
 * un unico "ExpensesImporter.php" para ambas. Extraido de
 * PostgresSheetReplicaImporter::importOperationalExpenses /
 * ::importFixedExpenses.
 */
final class ExpensesImporter
{
    public function __construct(
        private readonly PDO $connection,
        private readonly SheetRowMapper $mapper,
    ) {
    }

    /**
     * @param list<list<mixed>> $rows
     */
    public function importOperational(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->mapper->findHeader($rows, ['concepto', 'monto gasto ($)']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->mapper->combine($headers, $rows[$index]);
            $concept = $this->mapper->string($row, 'concepto');
            $amount = $this->mapper->money($row, 'monto gasto ($)');
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
                'selected_service' => $this->mapper->string($row, 'seleccionar servicio'),
                'expense_external_id' => $this->mapper->string($row, 'id'),
                'contract_id' => $this->mapper->string($row, 'id contrato'),
                'service_type' => $this->mapper->string($row, 'tipo de servicio'),
                'service_status' => $this->mapper->string($row, 'estado servicio'),
                'expense_date' => $this->mapper->date($row, 'fecha gasto'),
                'concept' => $concept,
                'amount' => $amount,
                'observations' => $this->mapper->string($row, 'observaciones'),
                'expense_status' => $this->mapper->string($row, 'estado gasto'),
                'customer_name' => $this->mapper->string($row, 'clienta'),
                'service_name' => $this->mapper->string($row, 'servicio'),
            ]);

            $imported++;
        }

        return $imported;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    public function importFixed(int $runId, string $sheetName, array $rows): int
    {
        [$headerIndex, $headers] = $this->mapper->findHeader($rows, ['concepto', 'periodicidad', 'estado']);
        $imported = 0;

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            $row = $this->mapper->combine($headers, $rows[$index]);
            $concept = $this->mapper->string($row, 'concepto');
            if ($concept === null) {
                continue;
            }

            $this->connection->prepare(
                'insert into qs_sheet_fixed_expense_rows (
                    import_run_id, source_row, concept, category, amount, expense_type,
                    periodicity, expense_status, notes, base_period
                ) values (
                    :import_run_id, :source_row, :concept, :category, :amount, :expense_type,
                    :periodicity, :expense_status, :notes, :base_period
                )'
            )->execute([
                'import_run_id' => $runId,
                'source_row' => $index + 1,
                'concept' => $concept,
                'category' => $this->mapper->string($row, 'categoria'),
                'amount' => $this->mapper->money($row, 'monto clp'),
                'expense_type' => $this->mapper->string($row, 'tipo'),
                'periodicity' => $this->mapper->string($row, 'periodicidad'),
                'expense_status' => $this->mapper->string($row, 'estado'),
                'notes' => $this->mapper->string($row, 'notas'),
                'base_period' => $this->mapper->string($row, 'periodo base'),
            ]);
            $imported++;
        }

        return $imported;
    }
}
