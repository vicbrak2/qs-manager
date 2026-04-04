<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\Persistence;

use QS\Modules\Booking\Domain\Entity\Reservation;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Service\ReservationNormalizer;
use QS\Modules\Booking\Infrastructure\Wordpress\LatepointTableMap;

final class WpdbLatepointRepository implements ReservationRepository
{
    public function __construct(
        private readonly LatepointTableMap $tableMap,
        private readonly ReservationNormalizer $normalizer
    ) {
    }

    public function findAll(): array
    {
        global $wpdb;

        if (! isset($wpdb) || ! $this->bookingTableExists()) {
            return [];
        }

        $rows = $wpdb->get_results($this->baseSelect() . ' ORDER BY b.start_date DESC, b.start_time DESC', ARRAY_A);

        return $this->normalizeRows($rows);
    }

    public function findToday(): array
    {
        $today = function_exists('current_time') ? (string) current_time('Y-m-d') : gmdate('Y-m-d');

        return $this->findByDate($today);
    }

    public function findById(int $id): ?Reservation
    {
        global $wpdb;

        if (! isset($wpdb) || ! $this->bookingTableExists()) {
            return null;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare($this->baseSelect() . ' WHERE b.id = %d LIMIT 1', $id),
            ARRAY_A
        );
        $normalized = $this->normalizeRows($rows);

        return $normalized[0] ?? null;
    }

    public function findByStaffAndDate(int $staffId, string $date): array
    {
        global $wpdb;

        if (! isset($wpdb) || ! $this->bookingTableExists()) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                $this->baseSelect() . ' WHERE b.agent_id = %d AND b.start_date = %s ORDER BY b.start_time ASC',
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
        global $wpdb;

        if (! isset($wpdb) || ! $this->bookingTableExists()) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                $this->baseSelect() . ' WHERE b.start_date = %s ORDER BY b.start_time ASC',
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
        global $wpdb;

        if (! isset($wpdb)) {
            return false;
        }

        $table = $this->tableMap->bookings();
        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $result === $table;
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
