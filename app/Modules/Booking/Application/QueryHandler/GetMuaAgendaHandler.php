<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\QueryHandler;

use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Application\Query\GetMuaAgenda;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;

final class GetMuaAgendaHandler
{
    public function __construct(private readonly ReservationRepository $reservationRepository)
    {
    }

    /**
     * @return array<int, ReservationDTO>
     */
    public function handle(GetMuaAgenda $query): array
    {
        return array_map(
            static fn ($reservation): ReservationDTO => new ReservationDTO($reservation),
            $this->reservationRepository->findByStaffAndDate($query->staffId, $query->date)
        );
    }
}
