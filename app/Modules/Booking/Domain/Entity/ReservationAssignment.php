<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Entity;

use QS\Modules\Booking\Domain\ValueObject\ReservationId;
use QS\Modules\Team\Domain\ValueObject\StaffId;

final class ReservationAssignment
{
    public function __construct(
        private readonly ReservationId $reservationId,
        private readonly StaffId $staffId
    ) {
    }
}
