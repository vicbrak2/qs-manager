<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

interface StaffRepository
{
    public function save(StaffMember $staffMember): StaffMember;

    /**
     * @return list<StaffMember>
     */
    public function findAll(): array;

    public function findById(int $id): ?StaffMember;

    public function update(int $id, StaffMember $staffMember): ?StaffMember;

    public function delete(int $id): bool;

    /**
     * Servicios futuros y sin cerrar en los que la profesional esta asignada
     * (como maquilladora o estilista). Un servicio ya pasado no cuenta: el
     * trabajo se hizo y su historial no impide dar de baja a la persona.
     *
     * @return list<array{scheduled_for: string, customer_name: ?string}>
     */
    public function pendingServices(int $staffId): array;

    public function exists(int $id): bool;
}
