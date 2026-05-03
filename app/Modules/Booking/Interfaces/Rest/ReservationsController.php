<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Interfaces\Rest;

use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\Booking\Application\Command\CreateReservation;
use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Application\Query\GetAllReservations;
use QS\Modules\Booking\Application\Query\GetReservationById;
use QS\Modules\Booking\Application\Query\GetTodayReservations;
use QS\Shared\Bus\CommandBus;
use QS\Shared\Bus\QueryBus;
use QS\Shared\DTO\RestResponse;
use DateTimeImmutable;

final class ReservationsController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly CommandBus $commandBus,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker
    ) {
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        /** @var array<int, ReservationDTO> $reservations */
        $reservations = $this->queryBus->ask(new GetAllReservations());

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $reservations));
    }

    public function today(\WP_REST_Request $request): \WP_REST_Response
    {
        /** @var array<int, ReservationDTO> $reservations */
        $reservations = $this->queryBus->ask(new GetTodayReservations());

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $reservations));
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Reservation not found.');
        }

        /** @var ReservationDTO|null $reservation */
        $reservation = $this->queryBus->ask(new GetReservationById($id));

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

    public function canManageBookings(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_bookings');
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $data = $request->get_json_params();
            
            $command = new CreateReservation(
                $this->requestSanitizer->sanitizeString($data['clientName'] ?? ''),
                $this->requestSanitizer->sanitizeEmail($data['clientEmail'] ?? ''),
                $this->requestSanitizer->sanitizeString($data['clientPhone'] ?? ''),
                $this->requestSanitizer->sanitizeString($data['serviceName'] ?? ''),
                new DateTimeImmutable($data['startTime']),
                new DateTimeImmutable($data['endTime'])
            );

            $eventId = $this->commandBus->dispatch($command);

            return $this->respond(['google_event_id' => $eventId, 'message' => 'Reservation created']);
        } catch (\Throwable $e) {
            return new \WP_REST_Response((new RestResponse('error', ['message' => $e->getMessage()]))->toArray(), 400);
        }
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
