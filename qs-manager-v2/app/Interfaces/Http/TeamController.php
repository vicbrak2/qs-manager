<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QSManager\Application\Team\CheckStaffAvailability;
use QSManager\Application\Team\DeleteStaffMember;
use QSManager\Application\Team\ListStaffMembers;
use QSManager\Application\Team\SaveStaffMember;
use QSManager\Domain\Team\StaffRepository;
use QSManager\Interfaces\Http\Validation\TeamRequestValidator;
use QSManager\Interfaces\Http\Validation\ValidationException;
use Slim\App;

final class TeamController
{
    public function __construct(
        private readonly SaveStaffMember $saveStaffMember,
        private readonly ListStaffMembers $listStaffMembers,
        private readonly StaffRepository $staff,
        private readonly CheckStaffAvailability $availability,
        private readonly DeleteStaffMember $deleteStaffMember,
        private readonly TeamRequestValidator $validator = new TeamRequestValidator(),
    ) {
    }

    public function register(App $app): void
    {
        $app->get('/api/v1/team', [$this, 'index']);
        $app->post('/api/v1/team', [$this, 'store']);
        $app->put('/api/v1/team/{id}', [$this, 'update']);
        $app->delete('/api/v1/team/{id}', [$this, 'delete']);
        $app->get('/api/v1/team/{id}/availability', [$this, 'availability']);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return $this->json($response, ['error' => 'Staff id must be a positive integer.'], 422);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }

        try {
            $staffMember = $this->saveStaffMember->execute($this->validator->validate($body), $id);
        } catch (ValidationException $exception) {
            return $this->validationError($response, $exception->errors());
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        }

        if ($staffMember === null) {
            return $this->json($response, ['error' => 'Staff member not found.'], 404);
        }

        return $this->json($response, ['staff_member' => $staffMember->toArray()]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return $this->json($response, ['error' => 'Staff id must be a positive integer.'], 422);
        }

        $result = $this->deleteStaffMember->execute($id);

        if (!$result['found']) {
            return $this->json($response, ['error' => 'Staff member not found.'], 404);
        }

        if ($result['pending'] !== []) {
            return $this->json($response, [
                'error' => DeleteStaffMember::pendingMessage($result['pending']),
                'pending_services' => $result['pending'],
            ], 409);
        }

        return $this->json($response, ['deleted' => true]);
    }

    private function positiveId(mixed $value): ?int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        return (int) $value > 0 ? (int) $value : null;
    }

    public function availability(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = filter_var($args['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || (int) $id <= 0 || !$this->staff->exists((int) $id)) {
            return $this->json($response, ['error' => 'Staff member not found.'], 404);
        }

        $params = $request->getQueryParams();

        $date = is_string($params['date'] ?? null) ? trim($params['date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->validationError($response, ['date' => ['Date is required in YYYY-MM-DD format.']]);
        }

        $time = null;
        if (isset($params['time']) && $params['time'] !== '') {
            if (!is_string($params['time']) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $params['time'])) {
                return $this->validationError($response, ['time' => ['Time must use HH:MM format.']]);
            }
            $time = $params['time'];
        }

        $duration = null;
        if (isset($params['duration_minutes']) && $params['duration_minutes'] !== '') {
            if (filter_var($params['duration_minutes'], FILTER_VALIDATE_INT) === false
                || (int) $params['duration_minutes'] <= 0) {
                return $this->validationError($response, ['duration_minutes' => ['Duration minutes must be a positive integer.']]);
            }
            $duration = (int) $params['duration_minutes'];
        }

        $result = $this->availability->execute((int) $id, $date, $time, $duration);

        return $this->json($response, [
            'staff_id' => (int) $id,
            'date' => $date,
            'requested_time' => $time,
            'available' => $result['available'],
            'busy' => $result['busy'],
        ]);
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
            $staffMember = $this->saveStaffMember->execute($this->validator->validate($body));
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
