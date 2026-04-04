<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Application\DTO;

use QS\Modules\IdentityAccess\Domain\ValueObject\QsRole;

final class UserPermissionsDTO
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(
        private readonly int $userId,
        private readonly ?QsRole $role,
        private readonly array $permissions
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'qs_role' => $this->role?->value,
            'permissions' => $this->permissions,
        ];
    }
}
