<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\CommandHandler;

use QS\Modules\Booking\Application\Command\CreateReservation;
use QS\Modules\Booking\Domain\Entity\Reservation;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Service\CalendarGateway;
use QS\Modules\Booking\Domain\ValueObject\ReservationId;
use QS\Modules\Booking\Domain\ValueObject\ReservationStatus;
use QS\Modules\Booking\Domain\ValueObject\ReservationTimeRange;

final class CreateReservationHandler
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private CalendarGateway $calendarGateway
    ) {
    }

    public function handle(CreateReservation $command): string
    {
        $title = "Reserva: {$command->serviceName} - {$command->clientName}";
        $description = "Email: {$command->clientEmail}\nTel: {$command->clientPhone}";
        
        $googleEventId = $this->calendarGateway->createEvent(
            $title,
            $description,
            $command->startTime,
            $command->endTime
        );

        $reservation = new Reservation(
            new ReservationId(0),
            null,
            null,
            null,
            null,
            $command->serviceName,
            null,
            $command->clientName,
            $command->clientEmail,
            $command->clientPhone,
            ReservationStatus::APPROVED,
            null,
            new ReservationTimeRange($command->startTime, $command->endTime),
            null,
            "Google Event ID: " . $googleEventId
        );

        $this->reservationRepository->save($reservation);

        return $googleEventId;
    }
}
