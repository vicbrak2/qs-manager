<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\Query;

final class GetBitacoraById
{
    public function __construct(public readonly int $id)
    {
    }
}
