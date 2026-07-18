<?php

declare(strict_types=1);

namespace QSManager\Domain\Finance;

use DateTimeImmutable;
use InvalidArgumentException;

final class FinancePeriod
{
    private function __construct(
        private readonly DateTimeImmutable $from,
        private readonly DateTimeImmutable $to
    ) {
        if ($from > $to) {
            throw new InvalidArgumentException('FinancePeriod: "from" date cannot be after "to" date.');
        }
    }

    public static function create(string $from, string $to): self
    {
        $fromDate = DateTimeImmutable::createFromFormat('Y-m-d', $from);
        $toDate = DateTimeImmutable::createFromFormat('Y-m-d', $to);

        if ($fromDate === false || $fromDate->format('Y-m-d') !== $from) {
            throw new InvalidArgumentException('FinancePeriod: "from" must be a valid date in Y-m-d format.');
        }

        if ($toDate === false || $toDate->format('Y-m-d') !== $to) {
            throw new InvalidArgumentException('FinancePeriod: "to" must be a valid date in Y-m-d format.');
        }

        return new self($fromDate, $toDate);
    }

    public function from(): DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): DateTimeImmutable
    {
        return $this->to;
    }
}
