<?php

declare(strict_types=1);

namespace QS\Shared\Bus;

final class CommandBus
{
    /**
     * @param callable(CommandInterface): mixed $handler
     */
    public function dispatch(CommandInterface $command, callable $handler): mixed
    {
        return $handler($command);
    }
}
