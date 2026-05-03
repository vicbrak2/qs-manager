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
use QS\Shared\Bus\CommandHandlerInterface;
use QS\Shared\Bus\CommandInterface;

final class CreateReservationHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly CalendarGateway $calendarGateway,
        private readonly \QS\Core\Logging\Logger $logger
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateReservation);
        
        $this->logger->info('CreateReservationHandler: Starting process for ' . $command->clientName);

        $title = "Reserva: {$command->serviceName} - {$command->clientName}";
        $description = "Email: {$command->clientEmail}\nTel: {$command->clientPhone}";

        $googleEventId = $this->calendarGateway->createEvent(
            $title,
            $description,
            $command->startTime,
            $command->endTime
        );
        
        $this->logger->info('CreateReservationHandler: Calendar event creation result: ' . $googleEventId);

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
            ReservationStatus::Approved,
            null,
            new ReservationTimeRange(
                $command->startTime->format('Y-m-d'),
                $command->startTime->format('H:i:s'),
                $command->endTime->format('H:i:s')
            ),
            null,
            'Google Event ID: ' . $googleEventId
        );

        $this->reservationRepository->save($reservation);
        
        $this->logger->info('CreateReservationHandler: Reservation saved locally.');

        return $googleEventId;
    }
}
