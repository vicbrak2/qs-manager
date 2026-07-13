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

    public function exists(int $id): bool;
}
