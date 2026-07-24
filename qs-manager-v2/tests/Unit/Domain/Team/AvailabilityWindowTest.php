<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Team;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QSManager\Domain\Team\AvailabilityWindow;

final class AvailabilityWindowTest extends TestCase
{
    public function testFromDateAndTimesBuildsCorrectBoundaries(): void
    {
        $window = AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '11:00');

        $this->assertSame('2026-08-01 09:00:00', $window->startAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-01 11:00:00', $window->endAt()->format('Y-m-d H:i:s'));
    }

    public function testDoesNotOverlapWhenAdjacent(): void
    {
        // 09:00-10:00 y 10:00-11:00 se tocan en el borde, no se superponen.
        $morning = AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '10:00');
        $afternoon = AvailabilityWindow::fromDateAndTimes('2026-08-01', '10:00', '11:00');

        $this->assertFalse($morning->overlaps($afternoon));
        $this->assertFalse($afternoon->overlaps($morning));
    }

    public function testOverlapsWhenRangesIntersect(): void
    {
        $a = AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '11:00');
        $b = AvailabilityWindow::fromDateAndTimes('2026-08-01', '10:00', '12:00');

        $this->assertTrue($a->overlaps($b));
        $this->assertTrue($b->overlaps($a));
    }

    public function testOverlapsWhenOneContainsTheOther(): void
    {
        $wide = AvailabilityWindow::fromDateAndTimes('2026-08-01', '08:00', '18:00');
        $narrow = AvailabilityWindow::fromDateAndTimes('2026-08-01', '10:00', '11:00');

        $this->assertTrue($wide->overlaps($narrow));
        $this->assertTrue($narrow->overlaps($wide));
    }

    public function testDoesNotOverlapOnDifferentDays(): void
    {
        $day1 = AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '11:00');
        $day2 = AvailabilityWindow::fromDateAndTimes('2026-08-02', '09:00', '11:00');

        $this->assertFalse($day1->overlaps($day2));
    }

    public function testToArrayFormatsAsDateAtom(): void
    {
        $window = new AvailabilityWindow(
            new DateTimeImmutable('2026-08-01T09:00:00+00:00'),
            new DateTimeImmutable('2026-08-01T11:00:00+00:00')
        );

        $array = $window->toArray();

        $this->assertSame('2026-08-01T09:00:00+00:00', $array['start_at']);
        $this->assertSame('2026-08-01T11:00:00+00:00', $array['end_at']);
    }
}
