<?php

declare(strict_types=1);

namespace QSManager\Domain\Booking;

interface BookingRepository
{
    public function save(Booking $booking): Booking;

    /**
     * @return list<Booking>
     */
    public function findAll(): array;

    public function findById(int $id): ?Booking;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ?Booking;

    public function delete(int $id): bool;

    public function recordGasSyncResult(int $id, string $status, ?string $message): void;
}
