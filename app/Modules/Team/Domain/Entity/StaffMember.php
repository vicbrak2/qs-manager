<?php

declare(strict_types=1);

namespace QS\Modules\Team\Domain\Entity;

use DateTimeImmutable;
use QS\Modules\Team\Domain\ValueObject\Specialty;
use QS\Modules\Team\Domain\ValueObject\StaffId;

final class StaffMember
{
    public function __construct(
        private readonly StaffId $id,
        private readonly string $nombre,
        private readonly string $apellido,
        private readonly Specialty $specialty,
        private readonly int $costoHoraClp,
        private readonly ?string $contactoWhatsapp,
        private readonly string $estado,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt
    ) {
    }

    public function id(): StaffId
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function apellido(): string
    {
        return $this->apellido;
    }

    public function specialty(): Specialty
    {
        return $this->specialty;
    }

    public function costoHoraClp(): int
    {
        return $this->costoHoraClp;
    }

    public function contactoWhatsapp(): ?string
    {
        return $this->contactoWhatsapp;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function fullName(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    public function isActive(): bool
    {
        return $this->estado === 'activo';
    }

    public function isMua(): bool
    {
        return $this->specialty === Specialty::Mua;
    }
}
