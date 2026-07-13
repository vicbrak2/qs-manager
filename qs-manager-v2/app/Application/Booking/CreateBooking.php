<?php

declare(strict_types=1);

namespace QSManager\Application\Booking;

use DateTimeImmutable;
use InvalidArgumentException;
use QSManager\Domain\Booking\Booking;
use QSManager\Domain\Booking\BookingRepository;

final class CreateBooking
{
    public function __construct(private readonly BookingRepository $bookings)
    {
    }

    public function execute(CreateBookingCommand $command): Booking
    {
        try {
            $scheduledFor = null;
            if ($command->scheduledFor !== null && trim($command->scheduledFor) !== '') {
                $scheduledFor = new DateTimeImmutable($command->scheduledFor);
            }
        } catch (\Exception $exception) {
            throw new InvalidArgumentException('Invalid scheduled date and time format.');
        }

        $booking = Booking::create(
            $command->serviceId,
            $command->staffId,
            $command->customerName,
            $command->customerPhone,
            $scheduledFor,
            $command->status,
            $command->address,
            $command->comuna,
            $command->serviceValue,
            $command->transferValue,
            $command->depositAmount,
            $command->totalService,
            $command->balanceDue,
            $command->paymentStatus,
            $command->serviceStatus,
            $command->contractId,
            $command->milestone,
            $command->cashGroup,
        );

        return $this->bookings->save($booking);
    }
}
