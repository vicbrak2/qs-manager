<?php

declare(strict_types=1);

namespace QS\Modules\Team\Interfaces\Rest;

use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\Team\Application\Query\GetAllStaff;
use QS\Modules\Team\Application\Query\GetStaffAvailability;
use QS\Modules\Team\Application\Query\GetStaffById;
use QS\Modules\Team\Application\QueryHandler\GetAllStaffHandler;
use QS\Modules\Team\Application\QueryHandler\GetStaffAvailabilityHandler;
use QS\Modules\Team\Application\QueryHandler\GetStaffByIdHandler;
use QS\Modules\Team\Domain\ValueObject\Specialty;
use QS\Shared\DTO\RestResponse;

final class StaffController
{
    public function __construct(
        private readonly GetAllStaffHandler $getAllStaffHandler,
        private readonly GetStaffByIdHandler $getStaffByIdHandler,
        private readonly GetStaffAvailabilityHandler $getStaffAvailabilityHandler,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker
    ) {
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $specialty = Specialty::fromNullable($this->requestSanitizer->sanitizeNullableText($request->get_param('specialty')));
        $activeOnly = $this->requestSanitizer->sanitizeBool($request->get_param('active_only') ?? true);
        $staff = $this->getAllStaffHandler->handle(new GetAllStaff($specialty, $activeOnly));

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $staff));
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Staff member not found.');
        }

        $staff = $this->getStaffByIdHandler->handle(new GetStaffById($id));

        if ($staff !== null) {
            return $this->respond($staff->toArray());
        }

        return $this->notFound('Staff member not found.');
    }

    public function availability(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Staff member not found.');
        }

        $date = $this->requestSanitizer->sanitizeNullableText($request->get_param('date')) ?? gmdate('Y-m-d');
        $startTime = $this->requestSanitizer->sanitizeNullableText($request->get_param('start_time'));
        $endTime = $this->requestSanitizer->sanitizeNullableText($request->get_param('end_time'));
        $availability = $this->getStaffAvailabilityHandler->handle(new GetStaffAvailability($id, $date, $startTime, $endTime));

        if ($availability === null) {
            return $this->notFound('Staff member not found.');
        }

        return $this->respond($availability);
    }

    public function muas(\WP_REST_Request $request): \WP_REST_Response
    {
        $staff = $this->getAllStaffHandler->handle(new GetAllStaff(Specialty::Mua, true));

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $staff));
    }

    public function mua(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = $this->show($request);
        $payload = $response->get_data();

        if (! is_array($payload) || ! isset($payload['data']['especialidad']) || $payload['data']['especialidad'] !== Specialty::Mua->value) {
            return $this->notFound('MUA not found.');
        }

        return $response;
    }

    public function canViewStaff(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_staff')
            || $this->capabilityChecker->currentUserCan('qs_manage_bookings');
    }

    public function canViewAvailability(\WP_REST_Request $request): bool
    {
        return $this->canViewStaff($request);
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
