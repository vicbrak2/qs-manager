<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\Persistence;

use QS\Modules\Booking\Domain\Entity\Reservation;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Booking\Infrastructure\Wordpress\LatepointTableMap;

final class WpdbLatepointRepository implements ReservationRepository
{
    private ?bool $tableExists = null;

    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly LatepointTableMap $tableMap,
        private readonly ReservationNormalizer $normalizer
    ) {
    }

    public function findAll(): array
    {
        if (! $this->bookingTableExists()) {
            return [];
        }

        $rows = $this->wpdb->get_results($this->baseSelect() . ' ORDER BY b.start_date DESC, b.start_time DESC', ARRAY_A);

        return $this->normalizeRows($rows);
    }

    public function findToday(): array
    {
        $today = function_exists('current_time') ? (string) current_time('Y-m-d') : gmdate('Y-m-d');

        return $this->findByDate($today);
    }

    public function findById(int $id): ?Reservation
    {
        if (! $this->bookingTableExists()) {
            return null;
        }

        /** @var literal-string $query */
        $query = $this->baseSelect() . ' WHERE b.id = %d LIMIT 1';
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($query, $id),
            ARRAY_A
        );
        $normalized = $this->normalizeRows($rows);

        return $normalized[0] ?? null;
    }

    public function findByStaffAndDate(int $staffId, string $date): array
    {
        if (! $this->bookingTableExists()) {
            return [];
        }

        /** @var literal-string $query */
        $query = $this->baseSelect() . ' WHERE b.agent_id = %d AND b.start_date = %s ORDER BY b.start_time ASC';
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                $query,
                $staffId,
                $date
            ),
            ARRAY_A
        );

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<int, Reservation>
     */
    private function findByDate(string $date): array
    {
        if (! $this->bookingTableExists()) {
            return [];
        }

        /** @var literal-string $query */
        $query = $this->baseSelect() . ' WHERE b.start_date = %s ORDER BY b.start_time ASC';
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                $query,
                $date
            ),
            ARRAY_A
        );

        return $this->normalizeRows($rows);
    }

    private function baseSelect(): string
    {
        return sprintf(
            'SELECT b.id, b.order_id, b.customer_id, b.agent_id, b.service_id, b.start_date, b.start_time, b.end_time, b.status, b.price, b.payment_method, b.notes, c.first_name AS customer_first_name, c.last_name AS customer_last_name, c.email, c.phone, s.title AS service_name, CONCAT(a.first_name, " ", a.last_name) AS agent_name FROM %s b LEFT JOIN %s c ON b.customer_id = c.id LEFT JOIN %s s ON b.service_id = s.id LEFT JOIN %s a ON b.agent_id = a.id',
            $this->tableMap->bookings(),
            $this->tableMap->customers(),
            $this->tableMap->services(),
            $this->tableMap->agents()
        );
    }

    private function bookingTableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        $table = $this->tableMap->bookings();
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );
        $this->tableExists = ($result === $table);

        return $this->tableExists;
    }

    /**
     * @param array<int, array<string, mixed>>|null $rows
     * @return array<int, Reservation>
     */
    private function normalizeRows(?array $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_map(
            fn (array $row): Reservation => $this->normalizer->fromRow($row),
            $rows
        );
    }
}
