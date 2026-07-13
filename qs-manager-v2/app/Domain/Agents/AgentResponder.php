<?php

declare(strict_types=1);

namespace QSManager\Domain\Agents;

interface AgentResponder
{
    public function reply(string $message): string;
}

