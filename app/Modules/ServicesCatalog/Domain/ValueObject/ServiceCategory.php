<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Domain\ValueObject;

enum ServiceCategory: string
{
    case Maquillaje = 'maquillaje';
    case Peinado = 'peinado';
    case Combo = 'combo';
    case Taller = 'taller';
    case Novia = 'novia';

    public static function fromNullable(?string $value): ?self
    {
        return $value !== null ? self::tryFrom(trim(strtolower($value))) : null;
    }
}
