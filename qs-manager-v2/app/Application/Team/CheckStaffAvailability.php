<?php

declare(strict_types=1);

namespace QSManager\Application\Team;

use DateTimeImmutable;
use DateTimeZone;
use QSManager\Domain\Booking\BookingRepository;
use QSManager\Domain\Team\AvailabilityChecker;
use QSManager\Domain\Team\AvailabilityWindow;

/**
 * La fuente de ocupacion son las reservas activas del staff (no existe una
 * agenda de ventanas declaradas) -- cada reserva ocupa
 * [scheduled_for, scheduled_for + duracion del servicio].
 */
final class CheckStaffAvailability
{
    private const DEFAULT_DURATION_MINUTES = 60;

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly AvailabilityChecker $checker,
    ) {
    }

    /**
     * @return array{busy: list<array{start_at: string, end_at: string, label: string}>, available: bool}
     */
    public function execute(int $staffId, string $date, ?string $time, ?int $durationMinutes): array
    {
        $utc = new DateTimeZone('UTC');
        $dayStart = new DateTimeImmutable($date . ' 00:00:00', $utc);
        $dayEnd = new DateTimeImmutable($date . ' 23:59:59', $utc);

        $slots = $this->bookings->activeSlotsForStaffBetween(
            $staffId,
            $dayStart->format(DateTimeImmutable::ATOM),
            $dayEnd->format(DateTimeImmutable::ATOM),
            self::DEFAULT_DURATION_MINUTES,
        );

        $windows = [];
        $busy = [];
        foreach ($slots as $slot) {
            $start = (new DateTimeImmutable($slot['scheduled_for']))->setTimezone($utc);
            $window = new AvailabilityWindow(
                $start,
                $start->modify(sprintf('+%d minutes', $slot['duration_minutes'])),
            );
            $windows[] = $window;
            $busy[] = $window->toArray() + ['label' => $slot['label']];
        }

        $requested = null;
        if ($time !== null) {
            $requestedStart = new DateTimeImmutable(sprintf('%s %s', $date, $time), $utc);
            $requested = new AvailabilityWindow(
                $requestedStart,
                $requestedStart->modify(sprintf('+%d minutes', $durationMinutes ?? self::DEFAULT_DURATION_MINUTES)),
            );
        }

        return [
            'busy' => $busy,
            'available' => $this->checker->isAvailable($requested, $windows),
        ];
    }
}
