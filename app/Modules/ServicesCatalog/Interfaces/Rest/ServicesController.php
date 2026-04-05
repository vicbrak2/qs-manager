<?php

declare(strict_types=1);

namespace QS\Modules\ServicesCatalog\Interfaces\Rest;

use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\ServicesCatalog\Application\Query\GetAllServices;
use QS\Modules\ServicesCatalog\Application\Query\GetServiceById;
use QS\Modules\ServicesCatalog\Application\QueryHandler\GetAllServicesHandler;
use QS\Modules\ServicesCatalog\Application\QueryHandler\GetServiceByIdHandler;
use QS\Shared\DTO\RestResponse;

final class ServicesController
{
    public function __construct(
        private readonly GetAllServicesHandler $getAllServicesHandler,
        private readonly GetServiceByIdHandler $getServiceByIdHandler,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker
    ) {
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $activeOnly = $this->requestSanitizer->sanitizeBool($request->get_param('active_only') ?? true);
        $services = $this->getAllServicesHandler->handle(new GetAllServices($activeOnly));

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $services));
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Service not found.');
        }

        $service = $this->getServiceByIdHandler->handle(new GetServiceById($id));

        return $service !== null
            ? $this->respond($service->toArray())
            : $this->notFound('Service not found.');
    }

    public function canViewServices(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_bookings')
            || $this->capabilityChecker->currentUserCan('qs_manage_staff')
            || $this->capabilityChecker->currentUserCan('qs_view_finance');
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
