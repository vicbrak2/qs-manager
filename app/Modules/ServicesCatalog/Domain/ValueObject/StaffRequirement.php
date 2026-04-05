<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Domain\ValueObject;

enum StaffRequirement: string
{
    case Mua = 'mua';
    case Estilista = 'estilista';
    case Ambos = 'ambos';

    public static function fromNullable(?string $value): ?self
    {
        return $value !== null ? self::tryFrom(trim(strtolower($value))) : null;
    }
}
