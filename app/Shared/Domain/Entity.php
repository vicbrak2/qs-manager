<?php

declare(strict_types=1);

namespace QS\Shared\Domain;

abstract class Entity
{
    public function __construct(protected readonly string|int|null $id = null)
    {
    }

    public function id(): string|int|null
    {
        return $this->id;
    }
}
