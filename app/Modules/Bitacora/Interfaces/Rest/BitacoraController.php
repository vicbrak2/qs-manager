<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Interfaces\Rest;

use InvalidArgumentException;
use QS\Core\Security\CapabilityChecker;
use QS\Core\Security\RequestSanitizer;
use QS\Modules\Bitacora\Application\Command\AddBitacoraNote;
use QS\Modules\Bitacora\Application\Command\CreateBitacora;
use QS\Modules\Bitacora\Application\Command\UpdateBitacora;
use QS\Modules\Bitacora\Application\CommandHandler\AddBitacoraNoteHandler;
use QS\Modules\Bitacora\Application\CommandHandler\CreateBitacoraHandler;
use QS\Modules\Bitacora\Application\CommandHandler\UpdateBitacoraHandler;
use QS\Modules\Bitacora\Application\Query\GetBitacoraById;
use QS\Modules\Bitacora\Application\Query\GetBitacoras;
use QS\Modules\Bitacora\Application\Query\GetBitacoraSummary;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacoraByIdHandler;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacorasHandler;
use QS\Modules\Bitacora\Application\QueryHandler\GetBitacoraSummaryHandler;
use QS\Shared\DTO\RestResponse;

final class BitacoraController
{
    public function __construct(
        private readonly GetBitacorasHandler $getBitacorasHandler,
        private readonly GetBitacoraByIdHandler $getBitacoraByIdHandler,
        private readonly GetBitacoraSummaryHandler $getBitacoraSummaryHandler,
        private readonly CreateBitacoraHandler $createBitacoraHandler,
        private readonly UpdateBitacoraHandler $updateBitacoraHandler,
        private readonly AddBitacoraNoteHandler $addBitacoraNoteHandler,
        private readonly RequestSanitizer $requestSanitizer,
        private readonly CapabilityChecker $capabilityChecker
    ) {
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $bitacoras = $this->getBitacorasHandler->handle(new GetBitacoras());

        return $this->respond(array_map(static fn ($dto): array => $dto->toArray(), $bitacoras));
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Bitacora not found.');
        }

        $bitacora = $this->getBitacoraByIdHandler->handle(new GetBitacoraById($id));

        return $bitacora !== null
            ? $this->respond($bitacora->toArray())
            : $this->notFound('Bitacora not found.');
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $bitacora = $this->createBitacoraHandler->handle($this->createCommand($this->payload($request)));

            return $this->respond($bitacora->toArray(), 201);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Bitacora not found.');
        }

        try {
            $bitacora = $this->updateBitacoraHandler->handle($this->updateCommand($id, $this->payload($request)));

            return $bitacora !== null
                ? $this->respond($bitacora->toArray())
                : $this->notFound('Bitacora not found.');
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    public function summary(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Bitacora not found.');
        }

        $summary = $this->getBitacoraSummaryHandler->handle(new GetBitacoraSummary($id));

        return $summary !== null
            ? $this->respond($summary)
            : $this->notFound('Bitacora not found.');
    }

    public function addNote(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $this->requestSanitizer->sanitizeInt($request['id']);

        if ($id === null) {
            return $this->notFound('Bitacora not found.');
        }

        $payload = $this->payload($request);
        $message = $this->requestSanitizer->sanitizeNullableText($payload['message'] ?? $payload['detalle'] ?? null);

        if ($message === null) {
            return $this->error('Bitacora note message is required.', 422);
        }

        $authorUserId = function_exists('get_current_user_id') ? (int) get_current_user_id() : null;
        $bitacora = $this->addBitacoraNoteHandler->handle(new AddBitacoraNote($id, $message, $authorUserId ?: null));

        return $bitacora !== null
            ? $this->respond($bitacora->toArray(), 201)
            : $this->notFound('Bitacora not found.');
    }

    public function canViewBitacoras(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_bitacoras')
            || $this->capabilityChecker->currentUserCan('qs_manage_bookings');
    }

    public function canManageBitacoras(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_bitacoras');
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

        $raw = $request->get_params();

        return $raw;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createCommand(array $payload): CreateBitacora
    {
        return new CreateBitacora(
            $this->requestSanitizer->sanitizeText($payload['fecha_servicio'] ?? ''),
            $this->requestSanitizer->sanitizeText($payload['tipo_servicio'] ?? ''),
            $this->requestSanitizer->sanitizeInt($payload['mua_id'] ?? null),
            $this->requestSanitizer->sanitizeInt($payload['estilista_id'] ?? null),
            $this->requestSanitizer->sanitizeText($payload['clienta_nombre'] ?? ''),
            $this->requestSanitizer->sanitizeText($payload['direccion_servicio'] ?? ''),
            $this->requestSanitizer->sanitizeNullableText($payload['hora_llegada'] ?? null),
            $this->requestSanitizer->sanitizeText($payload['punto_salida'] ?? ''),
            $this->requestSanitizer->sanitizeNullableText($payload['orden_recogida'] ?? null),
            $this->requestSanitizer->sanitizeInt($payload['tiempo_traslado_min'] ?? null),
            $this->requestSanitizer->sanitizeNullableText($payload['notas_logisticas'] ?? null),
            $this->requestSanitizer->sanitizeInt($payload['costo_staff_clp'] ?? null),
            $this->requestSanitizer->sanitizeInt($payload['precio_cliente_clp'] ?? null)
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updateCommand(int $id, array $payload): UpdateBitacora
    {
        $command = $this->createCommand($payload);

        return new UpdateBitacora(
            $id,
            $command->fechaServicio,
            $command->tipoServicio,
            $command->muaId,
            $command->estilistaId,
            $command->clientaNombre,
            $command->direccionServicio,
            $command->horaLlegada,
            $command->puntoSalida,
            $command->ordenRecogida,
            $command->tiempoTrasladoMin,
            $command->notasLogisticas,
            $command->costoStaffClp,
            $command->precioClienteClp
        );
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

    private function error(string $message, int $status): \WP_REST_Response
    {
        return new \WP_REST_Response((new RestResponse('error', ['message' => $message]))->toArray(), $status);
    }
}
