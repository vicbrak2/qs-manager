<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\Query;

final class GetMuaAgenda
{
    public function __construct(
        public readonly int $staffId,
        public readonly string $date
    ) {
    }
}
