<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Interfaces\Rest;

use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\Booking\Application\DTO\ReservationDTO;
use QS\Modules\Booking\Application\Query\GetMuaAgenda;
use QS\Shared\Bus\QueryBus;
use QS\Shared\DTO\RestResponse;

final class MuaAgendaController
{
    public function __construct(
        private readonly QueryBus $queryBus,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker
    ) {
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return new \WP_REST_Response((new RestResponse('error', ['message' => 'MUA not found.']))->toArray(), 404);
        }

        $date = $this->requestSanitizer->sanitizeNullableText($request->get_param('date')) ?? gmdate('Y-m-d');
        /** @var array<int, ReservationDTO> $agenda */
        $agenda = $this->queryBus->ask(new GetMuaAgenda($id, $date));

        return new \WP_REST_Response(
            (new RestResponse(
                'ok',
                [
                    'staff_id' => $id,
                    'date' => $date,
                    'reservations' => array_map(static fn ($dto): array => $dto->toArray(), $agenda),
                ]
            ))->toArray(),
            200
        );
    }

    public function canViewAgenda(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_bookings')
            || $this->capabilityChecker->currentUserCan('qs_manage_staff');
    }
}
