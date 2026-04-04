<?php

declare(strict_types=1);

namespace QS\Modules\Team\Domain\ValueObject;

use DateTimeImmutable;

final class AvailabilityWindow
{
    public function __construct(
        private readonly DateTimeImmutable $startAt,
        private readonly DateTimeImmutable $endAt
    ) {
    }

    public static function fromDateAndTimes(string $date, string $startTime, string $endTime): self
    {
        return new self(
            new DateTimeImmutable(sprintf('%s %s', $date, $startTime)),
            new DateTimeImmutable(sprintf('%s %s', $date, $endTime))
        );
    }

    public function overlaps(self $other): bool
    {
        return $this->startAt < $other->endAt && $this->endAt > $other->startAt;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'start_at' => $this->startAt->format(DATE_ATOM),
            'end_at' => $this->endAt->format(DATE_ATOM),
        ];
    }
}
