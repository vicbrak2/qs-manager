<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\Command;

use DateTimeImmutable;
use QS\Shared\Bus\CommandInterface;

final class CreateReservation implements CommandInterface
{
    public function __construct(
        public readonly string $clientName,
        public readonly string $clientEmail,
        public readonly string $clientPhone,
        public readonly string $serviceName,
        public readonly DateTimeImmutable $startTime,
        public readonly DateTimeImmutable $endTime
    ) {
    }
}
