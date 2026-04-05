<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Application\Query;

final class GetServiceById
{
    public function __construct(public readonly int $id)
    {
    }
}
