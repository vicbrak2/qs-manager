<?php

declare(strict_types=1);

namespace QSManager\Application\Team;

final class CreateStaffMemberCommand
{
    public function __construct(
        public readonly string $displayName,
        public readonly string $role,
    ) {
    }
}

