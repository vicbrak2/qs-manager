<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Exception;

use RuntimeException;

final class BookingConflictException extends RuntimeException
{
    public function __construct(private readonly string $conflictingEvent)
    {
        parent::__construct(
            'Conflicto de horario: ya existe una reserva en ese bloque. Evento: ' . $conflictingEvent
        );
    }

    public function getConflictingEvent(): string
    {
        return $this->conflictingEvent;
    }
}
