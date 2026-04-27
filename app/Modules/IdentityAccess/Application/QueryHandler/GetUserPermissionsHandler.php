<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Application\QueryHandler;

use QS\Modules\IdentityAccess\Application\DTO\UserPermissionsDTO;
use QS\Modules\IdentityAccess\Application\Query\GetUserPermissions;
use QS\Modules\IdentityAccess\Domain\Repository\UserRepository;
use QS\Shared\Bus\QueryHandlerInterface;

final class GetUserPermissionsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function handle(object $query): ?UserPermissionsDTO
    {
        assert($query instanceof GetUserPermissions);

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
