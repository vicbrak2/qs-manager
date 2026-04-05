<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Interfaces\Rest;

use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\Setup\Application\Command\SetupSiteCommand;
use QS\Modules\Setup\Application\CommandHandler\SetupSiteHandler;
use QS\Modules\Setup\Infrastructure\Wordpress\AgentStatusChecker;
use QS\Shared\DTO\RestResponse;

final class SetupController
{
    public function __construct(
        private readonly SetupSiteHandler $setupSiteHandler,
        private readonly AgentStatusChecker $agentStatusChecker,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker
    ) {
    }

    public function setup(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $this->payload($request);
        $command = SetupSiteCommand::fromInput($payload, SetupSiteCommand::defaults());
        $result = $this->setupSiteHandler->handle($command);

        return $this->respond($result);
    }

    public function agentsStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $status = $this->agentStatusChecker->check();
        $httpStatus = ($status['overall_ok'] ?? false) === true ? 200 : 503;

        return $this->respond($status, $httpStatus);
    }

    public function canManageSetup(\WP_REST_Request $request): bool
    {
        return function_exists('current_user_can') && (bool) current_user_can('manage_options');
    }

    public function canViewAgentsStatus(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_agents');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(\WP_REST_Request $request): array
    {
        $payload = $this->requestSanitizer->sanitizeArray($request->get_json_params());

        if ($payload !== []) {
            return $payload;
        }

        return $this->requestSanitizer->sanitizeArray($request->get_params());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function respond(array $data, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response((new RestResponse('ok', $data))->toArray(), $status);
    }
}
