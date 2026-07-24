<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

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

    public function startAt(): DateTimeImmutable
    {
        return $this->startAt;
    }

    public function endAt(): DateTimeImmutable
    {
        return $this->endAt;
    }

    public function overlaps(self $other): bool
    {
        return $this->startAt < $other->endAt && $this->endAt > $other->startAt;
    }

    /**
     * @return array{start_at: string, end_at: string}
     */
    public function toArray(): array
    {
        return [
            'start_at' => $this->startAt->format(DATE_ATOM),
            'end_at' => $this->endAt->format(DATE_ATOM),
        ];
    }
}
