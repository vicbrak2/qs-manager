<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Service;

use DateTimeImmutable;

interface CalendarGateway
{
    /**
     * Retorna un arreglo de horarios ocupados o disponibles para una fecha dada.
     *
     * @return array<int|string, mixed>
     */
    public function getAvailabilityForDate(DateTimeImmutable $date): array;

    /**
     * Crea un evento en el calendario y retorna el ID del evento de Google.
     */
    public function createEvent(
        string $title,
        string $description,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime
    ): string;
}
