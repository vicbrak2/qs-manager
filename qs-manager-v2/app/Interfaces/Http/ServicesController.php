<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QSManager\Application\ServicesCatalog\CreateService;
use QSManager\Application\ServicesCatalog\CreateServiceCommand;
use QSManager\Application\ServicesCatalog\ListServices;
use QSManager\Domain\ServicesCatalog\ServiceRepository;
use QSManager\Interfaces\Http\Validation\ServiceRequestValidator;
use QSManager\Interfaces\Http\Validation\ValidationException;
use Slim\App;

final class ServicesController
{
    public function __construct(
        private readonly CreateService $createService,
        private readonly ListServices $listServices,
        private readonly ServiceRepository $services,
        private readonly ServiceRequestValidator $validator = new ServiceRequestValidator(),
    ) {
    }

    public function register(App $app): void
    {
        $app->get('/api/v1/services', [$this, 'index']);
        $app->post('/api/v1/services', [$this, 'store']);
        $app->put('/api/v1/services/{id}', [$this, 'update']);
        $app->delete('/api/v1/services/{id}', [$this, 'delete']);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $services = array_map(
            static fn ($service): array => $service->toArray(),
            $this->listServices->execute(),
        );

        return $this->json($response, ['services' => $services]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }

        try {
            $validated = $this->validator->validate($body);
            $service = $this->createService->execute(new CreateServiceCommand(
                $validated['name'],
                $validated['category'],
                $validated['duration_minutes'],
            ));
        } catch (ValidationException $exception) {
            return $this->validationError($response, $exception->errors());
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        }

        return $this->json($response, ['service' => $service->toArray()], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return $this->json($response, ['error' => 'Service id must be a positive integer.'], 422);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }

        $existing = $this->services->findById($id);
        if ($existing === null) {
            return $this->json($response, ['error' => 'Service not found.'], 404);
        }

        try {
            $validated = $this->validateUpdatePayload($body);
            $service = $this->services->update($id, $validated);
        } catch (ValidationException $exception) {
            return $this->validationError($response, $exception->errors());
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        }

        return $this->json($response, ['service' => $service?->toArray()]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return $this->json($response, ['error' => 'Service id must be a positive integer.'], 422);
        }

        try {
            if (!$this->services->delete($id)) {
                return $this->json($response, ['error' => 'Service not found.'], 404);
            }
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23503') {
                return $this->json($response, [
                    'error' => 'Service has related records and cannot be deleted.',
                ], 409);
            }
            throw $exception;
        }

        return $this->json($response, ['deleted' => true]);
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

    private function validateUpdatePayload(array $body): array
    {
        $errors = [];

        $name = $this->stringField($body, 'name', true, $errors);
        if ($name !== null && (mb_strlen($name) < 3 || mb_strlen($name) > 160)) {
            $errors['name'][] = 'Name must be between 3 and 160 characters.';
        }

        $category = $this->stringField($body, 'category', false, $errors);
        if ($category !== null && mb_strlen($category) > 80) {
            $errors['category'][] = 'Category cannot exceed 80 characters.';
        }

        $duration = $this->optionalPositiveInt($body, 'duration_minutes', 'Duration minutes', $errors);
        $active = $this->optionalBool($body, 'active', $errors) ?? true;
        $salePrice = $this->optionalMoney($body, 'sale_price', 'Sale price', $errors);
        $totalCost = $this->optionalMoney($body, 'total_cost', 'Total cost', $errors);
        $utility = $this->optionalMoney($body, 'utility', 'Utility', $errors);
        $marginPercent = $this->optionalPercent($body, 'margin_percent', $errors);
        $marginStatus = $this->stringField($body, 'margin_status', false, $errors);
        if ($marginStatus !== null && mb_strlen($marginStatus) > 40) {
            $errors['margin_status'][] = 'Margin status cannot exceed 40 characters.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name ?? '',
            'category' => $category,
            'duration_minutes' => $duration,
            'active' => $active,
            'sale_price' => $salePrice,
            'total_cost' => $totalCost,
            'utility' => $utility,
            'margin_percent' => $marginPercent,
            'margin_status' => $marginStatus,
        ];
    }

    private function positiveId(mixed $value): ?int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function stringField(array $body, string $field, bool $required, array &$errors): ?string
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            if ($required) {
                $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
            return null;
        }

        if (!is_string($body[$field])) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must be a string.';
            return null;
        }

        $value = trim($body[$field]);

        return $value === '' ? null : $value;
    }

    private function optionalPositiveInt(array $body, string $field, string $label, array &$errors): ?int
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (filter_var($body[$field], FILTER_VALIDATE_INT) === false || (int) $body[$field] <= 0) {
            $errors[$field][] = $label . ' must be a positive integer.';
            return null;
        }

        return (int) $body[$field];
    }

    private function optionalBool(array $body, string $field, array &$errors): ?bool
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (!is_bool($body[$field])) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must be a boolean.';
            return null;
        }

        return $body[$field];
    }

    private function optionalMoney(array $body, string $field, string $label, array &$errors): ?float
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (!is_numeric($body[$field]) || (float) $body[$field] < 0) {
            $errors[$field][] = $label . ' must be a non-negative number.';
            return null;
        }

        return (float) $body[$field];
    }

    private function optionalPercent(array $body, string $field, array &$errors): ?float
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (!is_numeric($body[$field])) {
            $errors[$field][] = 'Margin percent must be numeric.';
            return null;
        }

        return (float) $body[$field];
    }
}
