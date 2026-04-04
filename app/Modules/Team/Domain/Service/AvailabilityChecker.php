<?php

declare(strict_types=1);

namespace QS\Modules\Team\Domain\Service;

use QS\Modules\Team\Domain\ValueObject\AvailabilityWindow;

final class AvailabilityChecker
{
    /**
     * @param array<int, AvailabilityWindow> $busyWindows
     */
    public function isAvailable(?AvailabilityWindow $requestedWindow, array $busyWindows): bool
    {
        if ($requestedWindow === null) {
            return count($busyWindows) === 0;
        }

        foreach ($busyWindows as $busyWindow) {
            if ($busyWindow->overlaps($requestedWindow)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, AvailabilityWindow> $busyWindows
     * @return array<int, array<string, string>>
     */
    public function summarize(array $busyWindows): array
    {
        return array_map(
            static fn (AvailabilityWindow $window): array => $window->toArray(),
            $busyWindows
        );
    }
}
