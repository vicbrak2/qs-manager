<?php

declare(strict_types=1);

namespace QS\Core\Events;

interface EventInterface
{
    public function name(): string;
}
