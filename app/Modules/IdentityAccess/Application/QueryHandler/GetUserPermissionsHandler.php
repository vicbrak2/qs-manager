<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Application\QueryHandler;

use QS\Modules\IdentityAccess\Application\DTO\UserPermissionsDTO;
use QS\Modules\IdentityAccess\Application\Query\GetUserPermissions;
use QS\Modules\IdentityAccess\Domain\Repository\UserRepository;

final class GetUserPermissionsHandler
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function handle(GetUserPermissions $query): ?UserPermissionsDTO
    {
        $user = $this->userRepository->findById($query->userId);

        if ($user === null) {
            return null;
        }

        return new UserPermissionsDTO(
            $query->userId,
            $user->qsRole(),
            $this->userRepository->permissionsFor($query->userId)
        );
    }
}
