<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Interfaces\Rest;

use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\Booking\Application\Query\GetAllReservations;
use QS\Modules\Booking\Application\Query\GetReservationById;
use QS\Modules\Booking\Application\Query\GetTodayReservations;
use QS\Modules\Booking\Application\QueryHandler\GetAllReservationsHandler;
use QS\Modules\Booking\Application\QueryHandler\GetReservationByIdHandler;
use QS\Modules\Booking\Application\QueryHandler\GetTodayReservationsHandler;
use QS\Shared\DTO\RestResponse;

final class ReservationsController
{
    public function __construct(
        private readonly GetAllReservationsHandler $getAllReservationsHandler,
        private readonly GetTodayReservationsHandler $getTodayReservationsHandler,
        private readonly GetReservationByIdHandler $getReservationByIdHandler,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker
    ) {
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $reservations = $this->getAllReservationsHandler->handle(new GetAllReservations());

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $reservations));
    }

    public function today(\WP_REST_Request $request): \WP_REST_Response
    {
        $reservations = $this->getTodayReservationsHandler->handle(new GetTodayReservations());

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $reservations));
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Reservation not found.');
        }

        $reservation = $this->getReservationByIdHandler->handle(new GetReservationById($id));

        if ($reservation === null) {
            return $this->notFound('Reservation not found.');
        }

        return $this->respond($reservation->toArray());
    }

    public function canViewBookings(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_bookings')
            || $this->capabilityChecker->currentUserCan('qs_manage_staff');
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $data
     */
    private function respond(array $data, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response((new RestResponse('ok', $data))->toArray(), $status);
    }

    private function notFound(string $message): \WP_REST_Response
    {
        return new \WP_REST_Response((new RestResponse('error', ['message' => $message]))->toArray(), 404);
    }
}
