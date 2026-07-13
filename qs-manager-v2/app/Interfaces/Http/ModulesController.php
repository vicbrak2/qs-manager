<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QSManager\Domain\Agents\AgentResponder;
use Slim\App;

final class ModulesController
{
    public function __construct(private readonly AgentResponder $agentResponder)
    {
    }

    public function register(App $app): void
    {
        $app->get('/api/v1/modules', [$this, 'index']);
        $app->post('/api/v1/agents/chat', [$this, 'stubAgentChat']);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = [
            'modules' => [
                ['name' => 'Booking', 'status' => 'planned', 'external_dependencies' => []],
                ['name' => 'Team', 'status' => 'planned', 'external_dependencies' => []],
                ['name' => 'ServicesCatalog', 'status' => 'planned', 'external_dependencies' => []],
                ['name' => 'Finance', 'status' => 'planned', 'external_dependencies' => []],
                ['name' => 'Bitacora', 'status' => 'planned', 'external_dependencies' => []],
                ['name' => 'CRM', 'status' => 'stubbed', 'external_dependencies' => []],
                ['name' => 'Agents', 'status' => 'stubbed', 'external_dependencies' => []],
                ['name' => 'IdentityAccess', 'status' => 'planned', 'external_dependencies' => []],
            ],
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function stubAgentChat(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $message = is_array($body) && isset($body['message']) ? (string) $body['message'] : '';

        $payload = [
            'reply' => $this->agentResponder->reply($message),
            'mode' => 'local_stub',
            'billable_external_calls' => 0,
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

