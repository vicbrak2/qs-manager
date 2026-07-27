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

    public function exists(int $id): bool;
}
