<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Infrastructure\Persistence;

use InvalidArgumentException;
use QS\Modules\Booking\Infrastructure\Wordpress\LatepointTableMap;
use QS\Modules\ServicesCatalog\Domain\Entity\Service;
use QS\Modules\ServicesCatalog\Domain\Repository\ServiceRepository;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceCategory;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServiceDuration;
use QS\Modules\ServicesCatalog\Domain\ValueObject\ServicePrice;
use QS\Modules\ServicesCatalog\Domain\ValueObject\StaffRequirement;
use QS\Shared\ValueObjects\ServiceId;

final class WpdbServiceCatalogRepository implements ServiceRepository
{
    private ?bool $tablesExist = null;

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly LatepointTableMap $tableMap
    ) {
    }

    public function findAll(bool $activeOnly = true): array
    {
        if (! $this->tablesExist()) {
            return [];
        }

        $query = $this->baseSelect()
            . ($activeOnly ? ' WHERE c.is_active = 1' : '')
            . ' ORDER BY s.id ASC';
        $rows = $this->wpdb->get_results($query, ARRAY_A);

        return $this->hydrateRows($rows);
    }

    public function findById(int $id): ?Service
    {
        if (! $this->tablesExist()) {
            return null;
        }

        /** @var literal-string $query */
        $query = $this->baseSelect() . ' WHERE s.id = %d LIMIT 1';
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($query, $id),
            ARRAY_A
        );
        $hydrated = $this->hydrateRows($rows);

        return $hydrated[0] ?? null;
    }

    private function baseSelect(): string
    {
        return sprintf(
            'SELECT s.*, c.category, c.staff_cost_clp, c.staff_required, c.is_active, c.description AS qs_description FROM %s s INNER JOIN %s c ON c.lp_service_id = s.id',
            $this->tableMap->services(),
            $this->costsTable()
        );
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

    /**
     * @param array<int, array<string, mixed>>|null $rows
     * @return array<int, Service>
     */
    private function hydrateRows(?array $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $services = [];

        foreach ($rows as $row) {
            $service = $this->hydrateRow($row);

            if ($service !== null) {
                $services[] = $service;
            }
        }

        return $services;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): ?Service
    {
        $id = $this->firstNumeric($row, ['id']);
        $name = $this->firstText($row, ['title', 'name']);
        $durationMin = $this->firstNumeric($row, ['duration', 'duration_min', 'duration_minutes']);
        $priceClp = $this->firstNumeric($row, ['charge_amount', 'price', 'price_min', 'amount', 'precio_base_clp']);
        $category = ServiceCategory::fromNullable($this->firstText($row, ['category']));
        $staffRequired = StaffRequirement::fromNullable($this->firstText($row, ['staff_required']));

        if ($id === null || $name === null || $durationMin === null || $priceClp === null || $category === null || $staffRequired === null) {
            return null;
        }

        $bufferMin = $this->firstNumeric($row, ['buffer_min']);

        if ($bufferMin === null) {
            $bufferMin = ($this->firstNumeric($row, ['buffer_before']) ?? 0)
                + ($this->firstNumeric($row, ['buffer_after']) ?? 0);
        }

        $staffCostClp = $this->firstNumeric($row, ['staff_cost_clp']) ?? 0;
        $active = ($this->firstNumeric($row, ['is_active']) ?? 0) === 1;
        $description = $this->firstText($row, ['qs_description', 'description', 'short_description']);

        try {
            return new Service(
                new ServiceId($id),
                $name,
                $category,
                new ServiceDuration($durationMin, $bufferMin),
                new ServicePrice($priceClp),
                $staffCostClp,
                $staffRequired,
                $active,
                $description
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $keys
     */
    private function firstNumeric(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || ! is_numeric($row[$key])) {
                continue;
            }

            return (int) $row[$key];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $keys
     */
    private function firstText(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || ! is_scalar($row[$key])) {
                continue;
            }

            $value = trim((string) $row[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
