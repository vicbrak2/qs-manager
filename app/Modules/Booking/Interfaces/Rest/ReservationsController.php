<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Interfaces\Rest;

use DateTimeImmutable;
use QS\Core\Logging\Logger;
use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\Booking\Application\Command\CreateReservation;
use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Application\Query\GetAllReservations;
use QS\Modules\Booking\Application\Query\GetReservationById;
use QS\Modules\Booking\Application\Query\GetTodayReservations;
use QS\Modules\Booking\Domain\Exception\BookingConflictException;
use QS\Shared\Bus\CommandBus;
use QS\Shared\Bus\QueryBus;
use QS\Shared\DTO\RestResponse;

final class ReservationsController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly CommandBus $commandBus,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker,
        private readonly Logger $logger
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

            $this->logger->info('ReservationsController: Incoming store request. Data: ' . (string) wp_json_encode($data));

            $command = new CreateReservation(
                clientName:    $this->requestSanitizer->sanitizeText($data['clientName'] ?? ''),
                clientEmail:   $this->requestSanitizer->sanitizeEmail($data['clientEmail'] ?? '') ?? '',
                clientPhone:   $this->requestSanitizer->sanitizeText($data['clientPhone'] ?? ''),
                serviceName:   $this->requestSanitizer->sanitizeText($data['serviceName'] ?? ''),
                startTime:     new DateTimeImmutable($data['startTime']),
                endTime:       new DateTimeImmutable($data['endTime']),
                encargada:     $this->requestSanitizer->sanitizeText($data['encargada'] ?? ''),
                direccion:     $this->requestSanitizer->sanitizeText($data['direccion'] ?? ''),
                comuna:        $this->requestSanitizer->sanitizeText($data['comuna'] ?? ''),
                traslado:      $this->requestSanitizer->sanitizeText($data['traslado'] ?? 'No'),
                valorServicio: $this->requestSanitizer->sanitizeText($data['valorServicio'] ?? ''),
                cantidad:      (int) ($data['cantidad'] ?? 1),
                abono:         (bool) ($data['abono'] ?? false),
                montoAbono:    $this->requestSanitizer->sanitizeText($data['montoAbono'] ?? ''),
                fechaAbono:    $this->requestSanitizer->sanitizeText($data['fechaAbono'] ?? ''),
            );

            $sheetName = $this->commandBus->dispatch($command);

            $this->logger->info('ReservationsController: Reserva creada en hoja → ' . $sheetName);

            return $this->respond([
                'sheet_name' => $sheetName,
                'message'    => 'Reserva registrada. El evento en Google Calendar será creado automáticamente por la planilla.',
            ]);
        } catch (BookingConflictException $e) {
            $this->logger->warning('ReservationsController: Conflicto de horario → ' . $e->getMessage());

            return new \WP_REST_Response(
                (new RestResponse('error', [
                    'message'           => $e->getMessage(),
                    'conflicting_event' => $e->getConflictingEvent(),
                ]))->toArray(),
                409
            );
        } catch (\Throwable $e) {
            $this->logger->error('ReservationsController: Error → ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return new \WP_REST_Response(
                (new RestResponse('error', ['message' => $e->getMessage()]))->toArray(),
                400
            );
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
