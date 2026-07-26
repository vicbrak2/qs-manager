<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http;

use DateTimeImmutable;
use InvalidArgumentException;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QSManager\Application\Bitacora\SaveBitacora;
use QSManager\Domain\Bitacora\Bitacora;
use QSManager\Domain\Bitacora\BitacoraRepository;
use QSManager\Domain\Bitacora\TravelNote;
use QSManager\Interfaces\Http\Validation\BitacoraRequestValidator;
use QSManager\Interfaces\Http\Validation\ValidationException;
use Slim\App;

/**
 * Reemplazo nativo del BitacoraController de V1 (WordPress). Mismos casos
 * de uso: CRUD, resumen logistico y notas de traslado.
 */
final class BitacoraController
{
    public function __construct(
        private readonly SaveBitacora $saveBitacora,
        private readonly BitacoraRequestValidator $validator,
        private readonly BitacoraRepository $bitacoras,
    ) {
    }

    public function register(App $app): void
    {
        $app->get('/api/v1/bitacoras', [$this, 'index']);
        $app->post('/api/v1/bitacoras', [$this, 'store']);
        $app->get('/api/v1/bitacoras/{id}', [$this, 'show']);
        $app->put('/api/v1/bitacoras/{id}', [$this, 'update']);
        $app->get('/api/v1/bitacoras/{id}/summary', [$this, 'summary']);
        $app->post('/api/v1/bitacoras/{id}/notes', [$this, 'addNote']);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $bitacoras = array_map(
            static fn (Bitacora $bitacora): array => $bitacora->toArray(),
            $this->bitacoras->findAll()
        );

        return $this->json($response, ['bitacoras' => $bitacoras]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bitacora = $this->find($args);
        if ($bitacora === null) {
            return $this->json($response, ['error' => 'Bitacora not found.'], 404);
        }

        return $this->json($response, ['bitacora' => $bitacora->toArray()]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }

        try {
            $bitacora = $this->saveBitacora->execute($this->validator->validate($body));
        } catch (ValidationException $exception) {
            return $this->validationError($response, $exception->errors());
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                return $this->json($response, ['error' => 'La reserva ya tiene una bitácora vinculada.'], 422);
            }
            throw $exception;
        }

        return $this->json($response, ['bitacora' => $bitacora->toArray()], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $existing = $this->find($args);
        if ($existing === null) {
            return $this->json($response, ['error' => 'Bitacora not found.'], 404);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->json($response, ['error' => 'Invalid JSON body.'], 400);
        }

        try {
            $bitacora = $this->saveBitacora->execute($this->validator->validate($body), $existing->id());
        } catch (ValidationException $exception) {
            return $this->validationError($response, $exception->errors());
        } catch (InvalidArgumentException $exception) {
            return $this->json($response, ['error' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                return $this->json($response, ['error' => 'La reserva ya tiene una bitácora vinculada.'], 422);
            }
            throw $exception;
        }

        return $this->json($response, ['bitacora' => $bitacora->toArray()]);
    }

    public function summary(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bitacora = $this->find($args);
        if ($bitacora === null) {
            return $this->json($response, ['error' => 'Bitacora not found.'], 404);
        }

        return $this->json($response, ['summary' => [
            'id' => $bitacora->id(),
            'fecha_servicio' => $bitacora->fechaServicio(),
            'tipo_servicio' => $bitacora->tipoServicio(),
            'clienta_nombre' => $bitacora->clientaNombre(),
            'direccion_servicio' => $bitacora->serviceAddress()->value(),
            'team' => [
                'mua_id' => $bitacora->muaId(),
                'estilista_id' => $bitacora->estilistaId(),
            ],
            'route_plan' => $bitacora->routePlan()->toArray(),
            'pricing' => [
                'costo_staff_clp' => $bitacora->costoStaffClp(),
                'precio_cliente_clp' => $bitacora->precioClienteClp(),
                'projected_margin_clp' => $bitacora->projectedMarginClp(),
            ],
            'notes_count' => count($bitacora->notes()),
            'updated_at' => $bitacora->updatedAt()->format(DATE_ATOM),
        ]]);
    }

    public function addNote(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bitacora = $this->find($args);
        if ($bitacora === null) {
            return $this->json($response, ['error' => 'Bitacora not found.'], 404);
        }

        $body = $request->getParsedBody();
        $message = is_array($body) ? ($body['message'] ?? $body['detalle'] ?? null) : null;
        $message = is_string($message) ? trim($message) : null;

        if ($message === null || $message === '') {
            return $this->validationError($response, ['message' => ['Bitacora note message is required.']]);
        }

        $updated = $this->bitacoras->addNote(
            (int) $bitacora->id(),
            new TravelNote($message, null, new DateTimeImmutable()),
        );

        if ($updated === null) {
            return $this->json($response, ['error' => 'Bitacora not found.'], 404);
        }

        return $this->json($response, ['bitacora' => $updated->toArray()], 201);
    }

    private function find(array $args): ?Bitacora
    {
        $id = filter_var($args['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || (int) $id <= 0) {
            return null;
        }

        return $this->bitacoras->findById((int) $id);
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
