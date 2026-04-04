<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Domain\Policy;

use QS\Modules\IdentityAccess\Domain\Entity\QsUser;
use QS\Modules\IdentityAccess\Domain\ValueObject\QsRole;

final class AccessPolicy
{
    public function allows(QsUser $user, string $capability): bool
    {
        if (in_array($capability, $user->capabilities(), true)) {
            return true;
        }

        return match ($user->qsRole()) {
            QsRole::Admin => true,
            QsRole::Coordinadora => in_array($capability, [
                'qs_manage_staff',
                'qs_view_finance',
                'qs_manage_bitacoras',
                'qs_manage_bookings',
            ], true),
            QsRole::Staff => $capability === 'read',
            null => false,
        };
    }
}
