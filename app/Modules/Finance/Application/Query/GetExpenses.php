<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\Query;

final class GetExpenses
{
    public function __construct(public readonly ?string $month = null)
    {
    }
}
