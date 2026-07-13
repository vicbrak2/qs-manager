<?php

declare(strict_types=1);

namespace QSManager\Application\Team;

use QSManager\Domain\Team\StaffMember;
use QSManager\Domain\Team\StaffRepository;

final class CreateStaffMember
{
    public function __construct(private readonly StaffRepository $staff)
    {
    }

    public function execute(CreateStaffMemberCommand $command): StaffMember
    {
        $staffMember = StaffMember::create(
            $command->displayName,
            $command->role,
        );

        return $this->staff->save($staffMember);
    }
}

