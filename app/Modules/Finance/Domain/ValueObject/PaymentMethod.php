<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\ValueObject;

enum PaymentMethod: string
{
    case Transferencia = 'transferencia';
    case Efectivo = 'efectivo';
    case Otro = 'otro';

    public static function fromNullable(?string $value): self
    {
        return match ($value) {
            self::Transferencia->value => self::Transferencia,
            self::Efectivo->value => self::Efectivo,
            default => self::Otro,
        };
    }
}
