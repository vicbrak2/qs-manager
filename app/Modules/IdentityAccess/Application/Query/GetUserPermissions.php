<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Application\Query;

final class GetUserPermissions
{
    public function __construct(public readonly int $userId)
    {
    }
}
