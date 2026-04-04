<?php

declare(strict_types=1);

namespace QS\Modules\IdentityAccess\Application\Command;

use QS\Modules\IdentityAccess\Domain\ValueObject\QsRole;
use QS\Shared\Bus\CommandInterface;

final class AssignQsRole implements CommandInterface
{
    public function __construct(
        public readonly int $userId,
        public readonly QsRole $role
    ) {
    }
}
