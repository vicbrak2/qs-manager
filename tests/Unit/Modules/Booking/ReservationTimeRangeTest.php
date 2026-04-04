<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Booking;

use QS\Modules\Booking\Domain\ValueObject\ReservationTimeRange;
use QS\Shared\Testing\TestCase;

final class ReservationTimeRangeTest extends TestCase
{
    public function testTimeRangesOverlapWhenIntervalsIntersect(): void
    {
        $first = new ReservationTimeRange('2026-04-03', '09:00:00', '10:00:00');
        $second = new ReservationTimeRange('2026-04-03', '09:30:00', '10:30:00');

        self::assertTrue($first->overlaps($second));
    }

    public function testTimeRangesDoNotOverlapWhenBoundaryTouchesOnly(): void
    {
        $first = new ReservationTimeRange('2026-04-03', '09:00:00', '10:00:00');
        $second = new ReservationTimeRange('2026-04-03', '10:00:00', '11:00:00');

        self::assertFalse($first->overlaps($second));
    }
}
