<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\ValueObject;

use DateTimeImmutable;

final class ReservationTimeRange
{
    public function __construct(
        private readonly string $serviceDate,
        private readonly string $startTime,
        private readonly string $endTime
    ) {
    }

    public function overlaps(self $other): bool
    {
        return $this->startAt() < $other->endAt() && $this->endAt() > $other->startAt();
    }

    public function serviceDate(): string
    {
        return $this->serviceDate;
    }

    public function startTime(): string
    {
        return $this->startTime;
    }

    public function endTime(): string
    {
        return $this->endTime;
    }

    public function startAt(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%s %s', $this->serviceDate, $this->startTime));
    }

    public function endAt(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%s %s', $this->serviceDate, $this->endTime));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->serviceDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }
}
