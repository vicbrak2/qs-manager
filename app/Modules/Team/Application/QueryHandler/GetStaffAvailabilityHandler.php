<?php

declare(strict_types=1);

namespace QS\Modules\Team\Application\QueryHandler;

use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Team\Application\DTO\StaffDTO;
use QS\Modules\Team\Application\Query\GetStaffAvailability;
use QS\Modules\Team\Domain\Repository\StaffRepository;
use QS\Modules\Team\Domain\Service\AvailabilityChecker;
use QS\Modules\Team\Domain\ValueObject\AvailabilityWindow;

final class GetStaffAvailabilityHandler
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly ReservationRepository $reservationRepository,
        private readonly AvailabilityChecker $availabilityChecker
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handle(GetStaffAvailability $query): ?array
    {
        $staff = $this->staffRepository->findById($query->staffId);

        if ($staff === null) {
            return null;
        }

        $reservations = $this->reservationRepository->findByStaffAndDate($query->staffId, $query->date);
        $busyWindows = array_map(
            static fn ($reservation): AvailabilityWindow => AvailabilityWindow::fromDateAndTimes(
                $reservation->timeRange()->serviceDate(),
                $reservation->timeRange()->startTime(),
                $reservation->timeRange()->endTime()
            ),
            $reservations
        );

        $requestedWindow = null;

        if ($query->startTime !== null && $query->endTime !== null) {
            $requestedWindow = AvailabilityWindow::fromDateAndTimes($query->date, $query->startTime, $query->endTime);
        }

        return [
            'staff' => (new StaffDTO($staff))->toArray(),
            'date' => $query->date,
            'busy_windows' => $this->availabilityChecker->summarize($busyWindows),
            'is_available' => $this->availabilityChecker->isAvailable($requestedWindow, $busyWindows),
        ];
    }
}
