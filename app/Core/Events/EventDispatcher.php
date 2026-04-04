<?php

declare(strict_types=1);

namespace QS\Core\Events;

final class EventDispatcher
{
    /**
     * @var array<string, array<int, callable>>
     */
    private array $listeners = [];

    public function listen(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $eventName, array $payload = []): void
    {
        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            $listener($payload);
        }
    }
}
