<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

final class AvailabilityChecker
{
    /**
     * @param list<AvailabilityWindow> $busyWindows
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
     * @param list<AvailabilityWindow> $busyWindows
     * @return list<array{start_at: string, end_at: string}>
     */
    public function summarize(array $busyWindows): array
    {
        return array_map(
            static fn (AvailabilityWindow $window): array => $window->toArray(),
            $busyWindows
        );
    }
}
