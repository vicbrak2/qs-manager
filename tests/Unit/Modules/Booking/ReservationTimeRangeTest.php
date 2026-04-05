<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Booking;

use QS\Modules\Booking\Domain\ValueObject\ReservationTimeRange;
use QS\Shared\Testing\TestCase;

final class ReservationTimeRangeTest extends TestCase
{
    public function testOverlapsWhenRangesIntersect(): void
    {
        $first = new ReservationTimeRange('2026-04-03', '09:00:00', '10:30:00');
        $second = new ReservationTimeRange('2026-04-03', '10:00:00', '11:00:00');

        self::assertTrue($first->overlaps($second));
        self::assertTrue($second->overlaps($first));
    }

    public function testDoesNotOverlapWhenAdjacentExact(): void
    {
        $first = new ReservationTimeRange('2026-04-03', '09:00:00', '10:00:00');
        $second = new ReservationTimeRange('2026-04-03', '10:00:00', '11:00:00');

        self::assertFalse($first->overlaps($second));
        self::assertFalse($second->overlaps($first));
    }

    public function testDoesNotOverlapWhenCompletelyBefore(): void
    {
        $first = new ReservationTimeRange('2026-04-03', '08:00:00', '09:00:00');
        $second = new ReservationTimeRange('2026-04-03', '10:00:00', '11:00:00');

        self::assertFalse($first->overlaps($second));
        self::assertFalse($second->overlaps($first));
    }

    public function testDoesNotOverlapWhenCompletelyAfter(): void
    {
        $first = new ReservationTimeRange('2026-04-03', '12:00:00', '13:00:00');
        $second = new ReservationTimeRange('2026-04-03', '09:00:00', '10:00:00');

        self::assertFalse($first->overlaps($second));
    }

    public function testOverlapsWhenOneContainsTheOther(): void
    {
        $outer = new ReservationTimeRange('2026-04-03', '08:00:00', '12:00:00');
        $inner = new ReservationTimeRange('2026-04-03', '09:00:00', '11:00:00');

        self::assertTrue($outer->overlaps($inner));
        self::assertTrue($inner->overlaps($outer));
    }

    public function testOverlapsWhenIdentical(): void
    {
        $first = new ReservationTimeRange('2026-04-03', '09:00:00', '10:00:00');
        $second = new ReservationTimeRange('2026-04-03', '09:00:00', '10:00:00');

        self::assertTrue($first->overlaps($second));
    }

    public function testDoesNotOverlapAcrossDifferentDates(): void
    {
        $first = new ReservationTimeRange('2026-04-03', '09:00:00', '10:00:00');
        $second = new ReservationTimeRange('2026-04-04', '09:00:00', '10:00:00');

        self::assertFalse($first->overlaps($second));
    }

    public function testAccessors(): void
    {
        $range = new ReservationTimeRange('2026-04-03', '09:00:00', '10:30:00');

        self::assertSame('2026-04-03', $range->serviceDate());
        self::assertSame('09:00:00', $range->startTime());
        self::assertSame('10:30:00', $range->endTime());
    }

    public function testStartAtAndEndAtReturnCorrectDateTimes(): void
    {
        $range = new ReservationTimeRange('2026-04-03', '09:00:00', '10:30:00');

        self::assertSame('2026-04-03 09:00:00', $range->startAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-03 10:30:00', $range->endAt()->format('Y-m-d H:i:s'));
    }

    public function testToArray(): void
    {
        $range = new ReservationTimeRange('2026-04-03', '09:00:00', '10:30:00');

        self::assertSame([
            'date' => '2026-04-03',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ], $range->toArray());
    }
}
