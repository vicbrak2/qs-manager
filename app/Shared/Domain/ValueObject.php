<?php

declare(strict_types=1);

namespace QS\Shared\Domain;

abstract class ValueObject
{
    /**
     * @return array<string, scalar|null>
     */
    abstract protected function toPrimitives(): array;

    public function equals(self $other): bool
    {
        return $this->toPrimitives() === $other->toPrimitives();
    }
}
