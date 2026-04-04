<?php

declare(strict_types=1);

namespace QS\Modules\Team\Domain\Entity;

use DateTimeImmutable;
use QS\Modules\Team\Domain\ValueObject\StaffId;

final class RoleAssignment
{
    public function __construct(
        private readonly StaffId $staffId,
        private readonly string $role,
        private readonly DateTimeImmutable $assignedFrom,
        private readonly ?DateTimeImmutable $assignedUntil
    ) {
    }

    public function staffId(): StaffId
    {
        return $this->staffId;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function assignedFrom(): DateTimeImmutable
    {
        return $this->assignedFrom;
    }

    public function assignedUntil(): ?DateTimeImmutable
    {
        return $this->assignedUntil;
    }
}
