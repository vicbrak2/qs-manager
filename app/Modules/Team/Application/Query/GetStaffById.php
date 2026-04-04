<?php

declare(strict_types=1);

namespace QS\Modules\Team\Application\Query;

final class GetStaffById
{
    public function __construct(public readonly int $staffId)
    {
    }
}
