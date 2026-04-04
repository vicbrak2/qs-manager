<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Application\CommandHandler;

use QS\Modules\IdentityAccess\Application\Command\AssignQsRole;
use QS\Modules\IdentityAccess\Domain\Repository\UserRepository;

final class AssignQsRoleHandler
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function handle(AssignQsRole $command): void
    {
        $this->userRepository->assignRole($command->userId, $command->role);
    }
}
