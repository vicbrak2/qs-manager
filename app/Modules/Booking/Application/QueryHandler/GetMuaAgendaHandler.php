<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\QueryHandler;

use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Application\Query\GetMuaAgenda;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Shared\Bus\QueryHandlerInterface;

final class GetMuaAgendaHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ReservationRepository $reservationRepository)
    {
    }

    /**
     * @return array<int, ReservationDTO>
     */
    public function handle(object $query): array
    {
        assert($query instanceof GetMuaAgenda);

        return array_map(
            static fn ($reservation): ReservationDTO => new ReservationDTO($reservation),
            $this->reservationRepository->findByStaffAndDate($query->staffId, $query->date)
        );
    }
}
