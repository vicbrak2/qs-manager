<?php

declare(strict_types=1);

namespace QS\Shared\Bus;

interface QueryHandlerInterface
{
    public function handle(object $query): mixed;
}
