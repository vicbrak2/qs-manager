<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Application\Query;

final class GetAllServices
{
    public function __construct(public readonly bool $activeOnly = true)
    {
    }
}
