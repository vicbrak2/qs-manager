<?php

declare(strict_types=1);

namespace QSManager\Application\Booking;

use QSManager\Domain\Booking\Booking;

interface BookingGasGateway
{
    public function sync(Booking $booking): GasSyncResult;
}
