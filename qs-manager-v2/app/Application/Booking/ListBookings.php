<?php

declare(strict_types=1);

namespace QSManager\Application\Booking;

use QSManager\Domain\Booking\BookingRepository;

final class ListBookings
{
    public function __construct(private readonly BookingRepository $bookings)
    {
    }

    /**
     * @return list<\QSManager\Domain\Booking\Booking>
     */
    public function execute(): array
    {
        return $this->bookings->findAll();
    }
}
