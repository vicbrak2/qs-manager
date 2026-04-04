<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Domain\Repository;

use QS\Modules\IdentityAccess\Domain\Entity\QsUser;
use QS\Modules\IdentityAccess\Domain\ValueObject\QsRole;

interface UserRepository
{
    public function findById(int $userId): ?QsUser;

    public function assignRole(int $userId, QsRole $role): void;

    /**
     * @return array<int, string>
     */
    public function permissionsFor(int $userId): array;
}
