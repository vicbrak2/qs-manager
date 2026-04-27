<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\QueryHandler;

use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Application\Query\GetAllReservations;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Shared\Bus\QueryHandlerInterface;

final class GetAllReservationsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ReservationRepository $reservationRepository)
    {
    }

    /**
     * @return array<int, ReservationDTO>
     */
    public function handle(object $query): array
    {
        assert($query instanceof GetAllReservations);

        return array_map(
            static fn ($reservation): ReservationDTO => new ReservationDTO($reservation),
            $this->reservationRepository->findAll()
        );
    }
}
