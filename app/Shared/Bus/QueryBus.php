<?php

declare(strict_types=1);

namespace QS\Shared\Bus;

final class QueryBus
{
    /**
     * @param callable(object): mixed $handler
     */
    public function ask(object $query, callable $handler): mixed
    {
        return $handler($query);
    }
}
