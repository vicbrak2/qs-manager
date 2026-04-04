<?php

declare(strict_types=1);

namespace QS\Shared\ValueObjects;

use DateTimeImmutable;
use QS\Shared\Domain\ValueObject;

final class DateRange extends ValueObject
{
    public function __construct(
        private readonly DateTimeImmutable $from,
        private readonly DateTimeImmutable $to
    ) {
    }

    public function from(): DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): DateTimeImmutable
    {
        return $this->to;
    }

    protected function toPrimitives(): array
    {
        return [
            'from' => $this->from->format(DATE_ATOM),
            'to' => $this->to->format(DATE_ATOM),
        ];
    }
}
