<?php

declare(strict_types=1);

namespace QS\Shared\Bus;

interface CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed;
}
