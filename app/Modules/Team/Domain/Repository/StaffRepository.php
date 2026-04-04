<?php

declare(strict_types=1);

namespace QS\Modules\Team\Domain\Repository;

use QS\Modules\Team\Domain\Entity\StaffMember;
use QS\Modules\Team\Domain\ValueObject\Specialty;

interface StaffRepository
{
    /**
     * @return array<int, StaffMember>
     */
    public function findAll(?Specialty $specialty = null, bool $activeOnly = true): array;

    public function findById(int $id): ?StaffMember;

    public function save(StaffMember $staffMember): StaffMember;
}
