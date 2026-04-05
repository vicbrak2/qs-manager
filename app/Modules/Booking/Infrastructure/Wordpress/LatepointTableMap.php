<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\Wordpress;

final class LatepointTableMap
{
    /** @var array<string, string> */
    private array $resolvedTables = [];

    /** @var array<string, string> */
    private array $resolvedColumns = [];

    public function __construct(private readonly \wpdb $wpdb)
    {
    }

    public function bookings(): string
    {
        return $this->resolveTable('bookings');
    }

    public function customers(): string
    {
        return $this->resolveTable('customers');
    }

    public function agents(): string
    {
        return $this->resolveTable('agents');
    }

    public function services(): string
    {
        return $this->resolveTable('services');
    }

    public function serviceNameColumn(string $alias = 's'): string
    {
        return sprintf('%s.%s', $alias, $this->resolveServiceNameColumn());
    }

    private function resolveTable(string $suffix): string
    {
        if (isset($this->resolvedTables[$suffix])) {
            return $this->resolvedTables[$suffix];
        }

        $candidates = [
            $this->wpdb->prefix . 'latepoint_' . $suffix,
            $this->wpdb->prefix . 'lp_' . $suffix,
        ];

        foreach ($candidates as $candidate) {
            $result = $this->wpdb->get_var(
                $this->wpdb->prepare('SHOW TABLES LIKE %s', $candidate)
            );

            if ($result === $candidate) {
                $this->resolvedTables[$suffix] = $candidate;

                return $candidate;
            }
        }

        $this->resolvedTables[$suffix] = $candidates[1];

        return $this->resolvedTables[$suffix];
    }

    private function resolveServiceNameColumn(): string
    {
        if (isset($this->resolvedColumns['services.name'])) {
            return $this->resolvedColumns['services.name'];
        }

        $servicesTable = $this->services();
        $nameColumn = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
                $servicesTable,
                'name'
            )
        );

        $this->resolvedColumns['services.name'] = $nameColumn === 'name' ? 'name' : 'title';

        return $this->resolvedColumns['services.name'];
    }
}
