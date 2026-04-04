<?php

declare(strict_types=1);

namespace QS\Shared\Domain;

abstract class AggregateRoot extends Entity
{
    /**
     * @var array<int, object>
     */
    private array $recordedEvents = [];

    protected function recordEvent(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return array<int, object>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
