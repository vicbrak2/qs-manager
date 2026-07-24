<?php

declare(strict_types=1);

namespace QSManager\Domain\Booking;

use RuntimeException;

/**
 * Portado tal cual desde V1 (Modules/Booking/Domain/Exception/BookingConflictException.php).
 * Se lanza cuando BookingTimeRange::overlaps() detecta que dos reservas
 * compiten por el mismo bloque de horario.
 */
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
