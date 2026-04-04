<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Domain\Entity;

use DateTimeImmutable;
use QS\Modules\Bitacora\Domain\ValueObject\ServiceAddress;

final class Bitacora
{
    /**
     * @param array<int, TravelNote> $notes
     */
    public function __construct(
        private readonly ?int $id,
        private readonly string $fechaServicio,
        private readonly string $tipoServicio,
        private readonly ?int $muaId,
        private readonly ?int $estilistaId,
        private readonly string $clientaNombre,
        private readonly ServiceAddress $serviceAddress,
        private readonly RoutePlan $routePlan,
        private readonly ?string $notasLogisticas,
        private readonly int $costoStaffClp,
        private readonly int $precioClienteClp,
        private readonly array $notes,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function fechaServicio(): string
    {
        return $this->fechaServicio;
    }

    public function tipoServicio(): string
    {
        return $this->tipoServicio;
    }

    public function muaId(): ?int
    {
        return $this->muaId;
    }

    public function estilistaId(): ?int
    {
        return $this->estilistaId;
    }

    public function clientaNombre(): string
    {
        return $this->clientaNombre;
    }

    public function serviceAddress(): ServiceAddress
    {
        return $this->serviceAddress;
    }

    public function routePlan(): RoutePlan
    {
        return $this->routePlan;
    }

    public function notasLogisticas(): ?string
    {
        return $this->notasLogisticas;
    }

    public function costoStaffClp(): int
    {
        return $this->costoStaffClp;
    }

    public function precioClienteClp(): int
    {
        return $this->precioClienteClp;
    }

    /**
     * @return array<int, TravelNote>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function hasAssignedTeam(): bool
    {
        return $this->muaId !== null || $this->estilistaId !== null;
    }

    public function projectedMarginClp(): int
    {
        return $this->precioClienteClp - $this->costoStaffClp;
    }
}
