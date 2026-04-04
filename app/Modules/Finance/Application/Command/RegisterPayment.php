<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\Command;

use QS\Modules\Finance\Domain\ValueObject\PaymentMethod;

final class RegisterPayment
{
    public function __construct(
        public readonly ?int $reservationId,
        public readonly ?string $concept,
        public readonly int $amountClp,
        public readonly PaymentMethod $method,
        public readonly string $status,
        public readonly string $paidAt,
        public readonly string $closingMonth
    ) {
    }
}
