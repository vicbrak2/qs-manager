<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\Query;

final class GetReservationById
{
    public function __construct(public readonly int $reservationId)
    {
    }
}
