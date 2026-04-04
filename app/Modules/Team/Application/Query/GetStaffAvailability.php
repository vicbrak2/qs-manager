<?php

declare(strict_types=1);

namespace QS\Modules\Team\Application\Query;

final class GetStaffAvailability
{
    public function __construct(
        public readonly int $staffId,
        public readonly string $date,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null
    ) {
    }
}
