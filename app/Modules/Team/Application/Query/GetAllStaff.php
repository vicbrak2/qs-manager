<?php

declare(strict_types=1);

namespace QS\Modules\Team\Application\Query;

use QS\Modules\Team\Domain\ValueObject\Specialty;

final class GetAllStaff
{
    public function __construct(
        public readonly ?Specialty $specialty = null,
        public readonly bool $activeOnly = true
    ) {
    }
}
