<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

final class StaffMember
{
    private function __construct(
        private readonly ?StaffId $id,
        private readonly StaffDisplayName $displayName,
        private readonly StaffRole $role,
        private readonly bool $active,
    ) {
    }

    public static function create(string $displayName, string $role): self
    {
        return new self(
            null,
            StaffDisplayName::fromString($displayName),
            StaffRole::fromString($role),
            true,
        );
    }

    public static function fromPersistence(
        int $id,
        string $displayName,
        string $role,
        bool $active,
    ): self {
        return new self(
            StaffId::fromInt($id),
            StaffDisplayName::fromString($displayName),
            StaffRole::fromString($role),
            $active,
        );
    }

    public function id(): ?StaffId
    {
        return $this->id;
    }

    public function displayName(): StaffDisplayName
    {
        return $this->displayName;
    }

    public function role(): StaffRole
    {
        return $this->role;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'display_name' => $this->displayName->value(),
            'role' => $this->role->value(),
            'active' => $this->active,
        ];
    }
}

