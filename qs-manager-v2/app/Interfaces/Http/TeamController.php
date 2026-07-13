<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QSManager\Application\Team\CreateStaffMember;
use QSManager\Application\Team\CreateStaffMemberCommand;
use QSManager\Application\Team\ListStaffMembers;
use QSManager\Interfaces\Http\Validation\TeamRequestValidator;
use QSManager\Interfaces\Http\Validation\ValidationException;
use Slim\App;

final class TeamController
{
    public function __construct(
        private readonly CreateStaffMember $createStaffMember,
        private readonly ListStaffMembers $listStaffMembers,
        private readonly TeamRequestValidator $validator = new TeamRequestValidator(),
    ) {
    }

    public function register(App $app): void
    {
        $app->get('/api/v1/team', [$this, 'index']);
        $app->post('/api/v1/team', [$this, 'store']);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $staff = array_map(
            static fn ($staffMember): array => $staffMember->toArray(),
            $this->listStaffMembers->execute(),
        );

        return $this->json($response, ['staff' => $staff]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }

        try {
            $validated = $this->validator->validate($body);
            $staffMember = $this->createStaffMember->execute(new CreateStaffMemberCommand(
                $validated['display_name'],
                $validated['role'],
            ));
        } catch (ValidationException $exception) {
            return $this->validationError($response, $exception->errors());
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        }

        return $this->json($response, ['staff_member' => $staffMember->toArray()], 201);
    }

    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function validationError(ResponseInterface $response, array $errors): ResponseInterface
    {
        return $this->json($response, [
            'error' => 'Validation failed.',
            'errors' => $errors,
        ], 422);
    }
}
