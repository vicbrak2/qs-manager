<?php

declare(strict_types=1);

namespace QS\Modules\Team\Domain\ValueObject;

enum Specialty: string
{
    case Mua = 'mua';
    case Estilista = 'estilista';
    case Coordinadora = 'coordinadora';

    public static function fromNullable(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        return null;
    }
}
