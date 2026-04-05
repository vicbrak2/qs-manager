<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Infrastructure\Persistence;

use QS\Modules\Booking\Infrastructure\Wordpress\LatepointTableMap;
use QS\Modules\Finance\Domain\Repository\ServiceCostRepository;

final class WpServiceCostRepository implements ServiceCostRepository
{
    private ?bool $tablesExist = null;

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly LatepointTableMap $tableMap
    ) {
    }

    public function findAll(): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $rows = $this->wpdb->get_results(
            sprintf(
                'SELECT %s AS service_name, c.staff_cost_clp FROM %s s INNER JOIN %s c ON c.lp_service_id = s.id ORDER BY s.id ASC',
                $this->tableMap->serviceNameColumn(),
                $this->tableMap->services(),
                $this->costsTable()
            ),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        $costs = [];

        foreach ($rows as $row) {
            if (
                ! isset($row['service_name'], $row['staff_cost_clp'])
                || ! is_scalar($row['service_name'])
                || ! is_numeric($row['staff_cost_clp'])
            ) {
                continue;
            }

            $costs[$this->normalizeServiceName((string) $row['service_name'])] = (int) $row['staff_cost_clp'];
        }

        return $costs;
    }

    private function costsTable(): string
    {
        return $this->wpdb->prefix . 'qs_service_costs';
    }

    private function tablesExist(): bool
    {
        if ($this->tablesExist !== null) {
            return $this->tablesExist;
        }

        $servicesTable = $this->tableMap->services();
        $costsTable = $this->costsTable();
        $servicesExists = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $servicesTable));
        $costsExists = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $costsTable));

        $this->tablesExist = ($servicesExists === $servicesTable) && ($costsExists === $costsTable);

        return $this->tablesExist;
    }

    private function normalizeServiceName(string $serviceName): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $serviceName) ?? $serviceName));
    }
}
