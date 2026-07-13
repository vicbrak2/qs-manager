<?php

declare(strict_types=1);

namespace QSManager\Application\Booking;

use InvalidArgumentException;
use QSManager\Domain\Booking\BookingRepository;

final class SyncBookingToGas
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly BookingGasGateway $gasGateway,
    ) {
    }

    public function execute(int $bookingId): GasSyncResult
    {
        $booking = $this->bookings->findById($bookingId);
        if ($booking === null) {
            throw new InvalidArgumentException('Booking not found.');
        }

        $result = $this->gasGateway->sync($booking);
        $this->bookings->recordGasSyncResult($bookingId, $result->status(), $result->message());

        return $result;
    }
}
