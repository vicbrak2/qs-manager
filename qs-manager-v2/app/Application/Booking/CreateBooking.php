<?php

declare(strict_types=1);

namespace QSManager\Application\Booking;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use QSManager\Domain\Booking\Booking;
use QSManager\Domain\Booking\BookingConflictException;
use QSManager\Domain\Booking\BookingRepository;
use QSManager\Domain\Booking\BookingTimeRange;
use QSManager\Domain\ServicesCatalog\ServiceRepository;

final class CreateBooking
{
    /**
     * Bloque asumido cuando el servicio no declara duracion. Tambien es el
     * fallback para las reservas existentes sin servicio asociado.
     */
    private const DEFAULT_DURATION_MINUTES = 60;

    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly ServiceRepository $services,
    ) {
    }

    public function execute(CreateBookingCommand $command): Booking
    {
        try {
            $scheduledFor = null;
            if ($command->scheduledFor !== null && trim($command->scheduledFor) !== '') {
                $scheduledFor = new DateTimeImmutable($command->scheduledFor);
            }
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('Invalid scheduled date and time format.');
        }

        if ($scheduledFor !== null && $command->staffId !== null) {
            $this->ensureSlotIsFree($command->staffId, $command->serviceId, $scheduledFor);
        }

        $booking = Booking::create(
            $command->serviceId,
            $command->staffId,
            $command->customerName,
            $command->customerPhone,
            $scheduledFor,
            $command->status,
            $command->address,
            $command->comuna,
            $command->serviceValue,
            $command->transferValue,
            $command->depositAmount,
            $command->totalService,
            $command->balanceDue,
            $command->paymentStatus,
            $command->serviceStatus,
            $command->contractId,
            $command->milestone,
            $command->cashGroup,
        );

        return $this->bookings->save($booking);
    }

    /**
     * Verifica que el bloque horario del staff este libre antes de confirmar
     * la reserva. Sin staff asignado no hay chequeo posible (dos reservas
     * simultaneas con equipos distintos son validas).
     *
     * @throws BookingConflictException
     */
    private function ensureSlotIsFree(int $staffId, ?int $serviceId, DateTimeImmutable $scheduledFor): void
    {
        $start = $scheduledFor->setTimezone(new DateTimeZone('UTC'));

        $duration = self::DEFAULT_DURATION_MINUTES;
        if ($serviceId !== null) {
            $duration = $this->services->findById($serviceId)?->duration()?->minutes()
                ?? self::DEFAULT_DURATION_MINUTES;
        }

        $candidate = $this->rangeFrom($start, $duration);

        $slots = $this->bookings->activeSlotsForStaffBetween(
            $staffId,
            $start->modify('-1 day')->format(DateTimeImmutable::ATOM),
            $start->modify('+1 day')->format(DateTimeImmutable::ATOM),
            self::DEFAULT_DURATION_MINUTES,
        );

        foreach ($slots as $slot) {
            $slotStart = (new DateTimeImmutable($slot['scheduled_for']))->setTimezone(new DateTimeZone('UTC'));
            $range = $this->rangeFrom($slotStart, $slot['duration_minutes']);

            if ($candidate->overlaps($range)) {
                throw new BookingConflictException($slot['label']);
            }
        }
    }

    /**
     * BookingTimeRange modela un rango dentro de un mismo dia; si la duracion
     * cruza medianoche se recorta a 23:59:59 para no invertir el rango.
     */
    private function rangeFrom(DateTimeImmutable $start, int $durationMinutes): BookingTimeRange
    {
        $end = $start->modify(sprintf('+%d minutes', $durationMinutes));
        if ($end->format('Y-m-d') !== $start->format('Y-m-d')) {
            $end = new DateTimeImmutable($start->format('Y-m-d') . ' 23:59:59', $start->getTimezone());
        }

        return new BookingTimeRange(
            $start->format('Y-m-d'),
            $start->format('H:i:s'),
            $end->format('H:i:s'),
        );
    }
}
