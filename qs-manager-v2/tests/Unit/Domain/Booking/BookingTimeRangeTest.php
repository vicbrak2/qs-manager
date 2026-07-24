<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Booking;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Booking\BookingTimeRange;

final class BookingTimeRangeTest extends TestCase
{
    public function testExposesFields(): void
    {
        $range = new BookingTimeRange('2026-08-01', '09:00:00', '11:00:00');

        $this->assertSame('2026-08-01', $range->serviceDate());
        $this->assertSame('09:00:00', $range->startTime());
        $this->assertSame('11:00:00', $range->endTime());
    }

    public function testStartAtAndEndAtCombineDateAndTime(): void
    {
        $range = new BookingTimeRange('2026-08-01', '09:00:00', '11:00:00');

        $this->assertSame('2026-08-01 09:00:00', $range->startAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-01 11:00:00', $range->endAt()->format('Y-m-d H:i:s'));
    }

    public function testDoesNotOverlapWhenAdjacent(): void
    {
        $first = new BookingTimeRange('2026-08-01', '09:00:00', '10:00:00');
        $second = new BookingTimeRange('2026-08-01', '10:00:00', '11:00:00');

        $this->assertFalse($first->overlaps($second));
        $this->assertFalse($second->overlaps($first));
    }

    public function testOverlapsWhenRangesIntersect(): void
    {
        $a = new BookingTimeRange('2026-08-01', '09:00:00', '11:00:00');
        $b = new BookingTimeRange('2026-08-01', '10:00:00', '12:00:00');

        $this->assertTrue($a->overlaps($b));
        $this->assertTrue($b->overlaps($a));
    }

    public function testDoesNotOverlapOnDifferentDates(): void
    {
        $day1 = new BookingTimeRange('2026-08-01', '09:00:00', '11:00:00');
        $day2 = new BookingTimeRange('2026-08-02', '09:00:00', '11:00:00');

        $this->assertFalse($day1->overlaps($day2));
    }

    public function testToArray(): void
    {
        $range = new BookingTimeRange('2026-08-01', '09:00:00', '11:00:00');

        $this->assertSame([
            'date' => '2026-08-01',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ], $range->toArray());
    }
}
