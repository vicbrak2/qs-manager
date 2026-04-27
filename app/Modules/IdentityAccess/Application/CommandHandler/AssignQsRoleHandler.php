<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Application\CommandHandler;

use QS\Modules\IdentityAccess\Application\Command\AssignQsRole;
use QS\Modules\IdentityAccess\Domain\Repository\UserRepository;
use QS\Shared\Bus\CommandHandlerInterface;
use QS\Shared\Bus\CommandInterface;

final class AssignQsRoleHandler implements CommandHandlerInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof AssignQsRole);

        $this->userRepository->assignRole($command->userId, $command->role);

        return null;
    }
}
