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

    public function markServiceCompleted(int $id): ?Booking;

    /**
     * @param array{image_base64: string, mime: string, filename: string, size: int} $receipt
     */
    public function updateTransferReceipt(int $id, array $receipt): ?Booking;

    public function delete(int $id): bool;

    public function recordGasSyncResult(int $id, string $status, ?string $message): void;

    /**
     * Reservas activas (status <> cancelled) de un staff cuyo inicio cae en
     * la ventana [from, to] (ISO 8601), con la duracion en minutos de su
     * servicio (defaultDurationMinutes cuando el servicio no declara una) y
     * una etiqueta legible para reportar conflictos de horario.
     *
     * @return list<array{label: string, scheduled_for: string, duration_minutes: int}>
     */
    public function activeSlotsForStaffBetween(
        int $staffId,
        string $from,
        string $to,
        int $defaultDurationMinutes,
    ): array;
}
