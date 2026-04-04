<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Domain\Entity;

use QS\Modules\IdentityAccess\Domain\ValueObject\QsRole;
use QS\Modules\IdentityAccess\Domain\ValueObject\UserId;

final class QsUser
{
    /**
     * @param array<int, string> $wordpressRoles
     * @param array<int, string> $capabilities
     */
    public function __construct(
        private readonly UserId $id,
        private readonly string $email,
        private readonly string $displayName,
        private readonly array $wordpressRoles,
        private readonly array $capabilities,
        private readonly ?QsRole $qsRole = null
    ) {
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    /**
     * @return array<int, string>
     */
    public function wordpressRoles(): array
    {
        return $this->wordpressRoles;
    }

    /**
     * @return array<int, string>
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function qsRole(): ?QsRole
    {
        return $this->qsRole;
    }
}
