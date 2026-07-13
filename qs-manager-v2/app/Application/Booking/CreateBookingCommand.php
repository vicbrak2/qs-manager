<?php

declare(strict_types=1);

namespace QSManager\Application\Booking;

final class CreateBookingCommand
{
    public function __construct(
        public readonly ?int $serviceId,
        public readonly ?int $staffId,
        public readonly ?string $customerName,
        public readonly ?string $customerPhone,
        public readonly ?string $scheduledFor,
        public readonly string $status = 'draft',
        public readonly ?string $address = null,
        public readonly ?string $comuna = null,
        public readonly ?float $serviceValue = null,
        public readonly ?float $transferValue = null,
        public readonly ?float $depositAmount = null,
        public readonly ?float $totalService = null,
        public readonly ?float $balanceDue = null,
        public readonly ?string $paymentStatus = null,
        public readonly ?string $serviceStatus = null,
        public readonly ?string $contractId = null,
        public readonly ?string $milestone = null,
        public readonly ?string $cashGroup = null,
    ) {
    }
}
