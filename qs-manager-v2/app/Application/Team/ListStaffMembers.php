<?php

declare(strict_types=1);

namespace QSManager\Application\Team;

use QSManager\Domain\Team\StaffRepository;

final class ListStaffMembers
{
    public function __construct(private readonly StaffRepository $staff)
    {
    }

    public function execute(): array
    {
        return $this->staff->findAll();
    }
}

