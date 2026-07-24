<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Team;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Team\AvailabilityChecker;
use QSManager\Domain\Team\AvailabilityWindow;

final class AvailabilityCheckerTest extends TestCase
{
    private AvailabilityChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new AvailabilityChecker();
    }

    public function testAvailableWhenNoBusyWindows(): void
    {
        $requested = AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '10:00');

        $this->assertTrue($this->checker->isAvailable($requested, []));
    }

    public function testAvailableWhenBusyWindowsDoNotOverlap(): void
    {
        $requested = AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '10:00');
        $busy = [AvailabilityWindow::fromDateAndTimes('2026-08-01', '14:00', '15:00')];

        $this->assertTrue($this->checker->isAvailable($requested, $busy));
    }

    public function testUnavailableWhenAnyBusyWindowOverlaps(): void
    {
        $requested = AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '11:00');
        $busy = [
            AvailabilityWindow::fromDateAndTimes('2026-08-01', '14:00', '15:00'),
            AvailabilityWindow::fromDateAndTimes('2026-08-01', '10:00', '10:30'),
        ];

        $this->assertFalse($this->checker->isAvailable($requested, $busy));
    }

    public function testNullRequestedWindowMeansCheckingIfCompletelyFree(): void
    {
        $this->assertTrue($this->checker->isAvailable(null, []));

        $busy = [AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '10:00')];
        $this->assertFalse($this->checker->isAvailable(null, $busy));
    }

    public function testSummarizeMapsWindowsToArrays(): void
    {
        $busy = [
            AvailabilityWindow::fromDateAndTimes('2026-08-01', '09:00', '10:00'),
            AvailabilityWindow::fromDateAndTimes('2026-08-01', '14:00', '15:00'),
        ];

        $summary = $this->checker->summarize($busy);

        $this->assertCount(2, $summary);
        $this->assertArrayHasKey('start_at', $summary[0]);
        $this->assertArrayHasKey('end_at', $summary[0]);
    }

    public function testSummarizeOfEmptyListIsEmptyArray(): void
    {
        $this->assertSame([], $this->checker->summarize([]));
    }
}
