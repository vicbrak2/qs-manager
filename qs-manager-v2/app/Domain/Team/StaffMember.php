<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

final class StaffMember
{
    /**
     * @param list<string> $aliases
     */
    private function __construct(
        private readonly ?StaffId $id,
        private readonly StaffDisplayName $displayName,
        private readonly StaffRole $role,
        private readonly bool $active,
        private readonly ?string $phone,
        private readonly ?string $comunaBase,
        private readonly array $aliases,
    ) {
    }

    /**
     * @param list<string> $aliases
     */
    public static function create(
        string $displayName,
        string $role,
        ?string $phone = null,
        ?string $comunaBase = null,
        array $aliases = [],
        bool $active = true,
    ): self {
        return new self(
            null,
            StaffDisplayName::fromString($displayName),
            StaffRole::fromString($role),
            $active,
            self::normalize($phone),
            self::normalize($comunaBase),
            $aliases,
        );
    }

    /**
     * @param list<string> $aliases
     */
    public static function fromPersistence(
        int $id,
        string $displayName,
        string $role,
        bool $active,
        ?string $phone = null,
        ?string $comunaBase = null,
        array $aliases = [],
    ): self {
        return new self(
            StaffId::fromInt($id),
            StaffDisplayName::fromString($displayName),
            StaffRole::fromString($role),
            $active,
            self::normalize($phone),
            self::normalize($comunaBase),
            $aliases,
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

    public function phone(): ?string
    {
        return $this->phone;
    }

    /**
     * Comuna donde se la recoge habitualmente: al elegirla en un tramo de
     * la bitacora, el destino se completa con esta comuna.
     */
    public function comunaBase(): ?string
    {
        return $this->comunaBase;
    }

    /**
     * Otras formas en que las planillas escriben su nombre (ej. Yeimy/Yeimi),
     * para que el sync no cree una profesional duplicada por cada ortografia.
     *
     * @return list<string>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'display_name' => $this->displayName->value(),
            'role' => $this->role->value(),
            'active' => $this->active,
            'phone' => $this->phone,
            'comuna_base' => $this->comunaBase,
            'aliases' => $this->aliases,
        ];
    }

    private static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
