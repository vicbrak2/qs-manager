<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Team;

use QS\Modules\Team\Domain\Service\AvailabilityChecker;
use QS\Modules\Team\Domain\ValueObject\AvailabilityWindow;
use QS\Shared\Testing\TestCase;

final class AvailabilityCheckerTest extends TestCase
{
    public function testDetectsOverlapAgainstBusyWindows(): void
    {
        $checker = new AvailabilityChecker();
        $busy = [AvailabilityWindow::fromDateAndTimes('2026-04-03', '10:00:00', '11:00:00')];
        $requested = AvailabilityWindow::fromDateAndTimes('2026-04-03', '10:30:00', '11:30:00');

        self::assertFalse($checker->isAvailable($requested, $busy));
    }

    public function testAllowsNonOverlappingWindow(): void
    {
        $checker = new AvailabilityChecker();
        $busy = [AvailabilityWindow::fromDateAndTimes('2026-04-03', '10:00:00', '11:00:00')];
        $requested = AvailabilityWindow::fromDateAndTimes('2026-04-03', '11:00:00', '12:00:00');

        self::assertTrue($checker->isAvailable($requested, $busy));
        self::assertCount(1, $checker->summarize($busy));
    }
}
