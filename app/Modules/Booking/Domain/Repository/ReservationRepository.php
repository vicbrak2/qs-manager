<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Repository;

use QS\Modules\Booking\Domain\Entity\Reservation;

interface ReservationRepository
{
    /**
     * @return array<int, Reservation>
     */
    public function findAll(): array;

    /**
     * @return array<int, Reservation>
     */
    public function findToday(): array;

    public function findById(int $id): ?Reservation;

    /**
     * @return array<int, Reservation>
     */
    public function findByStaffAndDate(int $staffId, string $date): array;
}
