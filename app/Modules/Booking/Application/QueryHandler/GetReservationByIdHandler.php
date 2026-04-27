<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\QueryHandler;

use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Application\Query\GetReservationById;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Shared\Bus\QueryHandlerInterface;

final class GetReservationByIdHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ReservationRepository $reservationRepository)
    {
    }

    public function handle(object $query): ?ReservationDTO
    {
        assert($query instanceof GetReservationById);

        $reservation = $this->reservationRepository->findById($query->reservationId);

        return $reservation === null ? null : new ReservationDTO($reservation);
    }
}
