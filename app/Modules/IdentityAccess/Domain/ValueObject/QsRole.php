<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Domain\ValueObject;

enum QsRole: string
{
    case Admin = 'qs_admin';
    case Coordinadora = 'qs_coordinadora';
    case Staff = 'qs_staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'QS Admin',
            self::Coordinadora => 'QS Coordinadora',
            self::Staff => 'QS Staff',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases()
        );
    }

    public static function fromWordPressRole(string $role): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $role) {
                return $case;
            }
        }

        return null;
    }
}
