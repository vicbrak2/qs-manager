<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\ValueObject;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public static function fromNullable(?string $value): self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        return self::Pending;
    }
}
