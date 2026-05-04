<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Interfaces\Rest;

use DateTimeImmutable;
use QS\Core\Logging\Logger;
use QS\Core\Security\CapabilityChecker;
use QS\Modules\Booking\Domain\Entity\SheetEvent;
use QS\Modules\Booking\Domain\Repository\SheetEventRepository;
use QS\Shared\DTO\RestResponse;

/**
 * Endpoints para la tabla qs_sheet_events:
 *
 * GET  /qs/v1/sheet-events              → listar todos
 * GET  /qs/v1/sheet-events?sheet=Mayo   → filtrar por mes
 * POST /qs/v1/sheet-events/upsert       → upsert masivo desde n8n (sin auth WP, usa secret)
 */
final class SheetEventsController
{
    public function __construct(
        private readonly SheetEventRepository $repository,
        private readonly CapabilityChecker $capabilityChecker,
        private readonly Logger $logger,
    ) {
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $sheet = $request->get_param('sheet');

        $events = is_string($sheet) && $sheet !== ''
            ? $this->repository->findBySheetName($sheet)
            : $this->repository->findAll();

        return $this->ok(array_map(
            static fn (SheetEvent $e): array => $e->toArray(),
            $events
        ));
    }

    public function canView(\WP_REST_Request $request): bool
    {
        return $this->capabilityChecker->currentUserCan('qs_manage_bookings')
            || $this->capabilityChecker->currentUserCan('qs_manage_staff');
    }

    /**
     * Upsert masivo recibido desde n8n.
     *
     * Payload esperado:
     * {
     *   "secret": "qs_sync_secret_xxx",
     *   "rows": [
     *     {
     *       "sheet_name": "Mayo", "row_index": 2,
     *       "encargada": "Camila", "dia": "Lunes",
     *       "fecha": "05/05/2026", "hora": "10:00",
     *       "servicio": "Maquillaje Social", "cantidad": 1,
     *       "clienta": "María González", "telefono": "...",
     *       "direccion": "...", "comuna": "...", "traslado": "No",
     *       "abono": 10000, "fecha_abono": "01/05/2026",
     *       "valor_servicio": 35000, "total_servicio": 35000,
     *       "total_por_pagar": 25000, "accion": "",
     *       "estado_evento": "Pendiente", "id_evento": ""
     *     }, ...
     *   ]
     * }
     */
    public function upsert(\WP_REST_Request $request): \WP_REST_Response
    {
        $secret = (string) get_option('qs_sync_secret', '');

        if ($secret === '') {
            return $this->error('qs_sync_secret no configurado en WordPress.', 500);
        }

        /** @var mixed $body */
        $body = $request->get_json_params();

        if (! is_array($body)) {
            return $this->error('Invalid JSON body.', 422);
        }

        $bodySecret = isset($body['secret']) && is_string($body['secret']) ? $body['secret'] : '';

        if (! hash_equals($secret, $bodySecret)) {
            $this->logger->warning('SheetEventsController: upsert rechazado — secret inválido.');

            return $this->error('Unauthorized.', 401);
        }

        $rawRows = $body['rows'] ?? [];

        if (! is_array($rawRows) || count($rawRows) === 0) {
            return $this->error('No rows provided.', 422);
        }

        $events  = [];
        $skipped = 0;

        foreach ($rawRows as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            /** @var array<string, mixed> $row */
            $event = $this->hydrateRow($row);

            if ($event === null) {
                $skipped++;
                continue;
            }

            $events[] = $event;
        }

        $processed = $this->repository->upsertBatch($events);

        $this->logger->info("SheetEventsController: upsert — {$processed} filas procesadas, {$skipped} omitidas.");

        return $this->ok([
            'processed' => $processed,
            'skipped'   => $skipped,
        ]);
    }

    /**
     * Permission callback para upsert — siempre permite (auth por secret en body).
     */
    public function allowUpsert(\WP_REST_Request $request): bool
    {
        return true;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateRow(array $row): ?SheetEvent
    {
        $sheetName = (string) ($row['sheet_name'] ?? '');
        $rowIndex  = (int) ($row['row_index'] ?? 0);

        if ($sheetName === '' || $rowIndex <= 0) {
            return null;
        }

        $now = new DateTimeImmutable('now');

        return new SheetEvent(
            id:                 null,
            sheetName:          $sheetName,
            rowIndex:           $rowIndex,
            encargada:          (string) ($row['encargada'] ?? ''),
            dia:                (string) ($row['dia'] ?? ''),
            fechaServicio:      $this->parseChileanDate((string) ($row['fecha'] ?? '')),
            horaInicio:         isset($row['hora']) && is_string($row['hora']) && $row['hora'] !== '' ? $row['hora'] : null,
            servicio:           (string) ($row['servicio'] ?? ''),
            cantidad:           max(1, (int) ($row['cantidad'] ?? 1)),
            clientaNombre:      (string) ($row['clienta'] ?? ''),
            telefono:           $this->nullable((string) ($row['telefono'] ?? '')),
            direccion:          $this->nullable((string) ($row['direccion'] ?? '')),
            comuna:             $this->nullable((string) ($row['comuna'] ?? '')),
            traslado:           (string) ($row['traslado'] ?? 'No'),
            abonoClp:           (int) ($row['abono'] ?? 0),
            fechaAbono:         $this->parseChileanDate((string) ($row['fecha_abono'] ?? '')),
            valorServicioClp:   (int) ($row['valor_servicio'] ?? 0),
            totalServicioClp:   (int) ($row['total_servicio'] ?? 0),
            totalPorPagarClp:   (int) ($row['total_por_pagar'] ?? 0),
            accion:             (string) ($row['accion'] ?? ''),
            estadoEvento:       (string) ($row['estado_evento'] ?? 'Pendiente'),
            idEventoGcal:       $this->nullable((string) ($row['id_evento'] ?? '')),
            origen:             'sheet',
            syncedAt:           $now,
            createdAt:          $now,
            updatedAt:          $now,
        );
    }

    /**
     * Parsea fecha chilena "d/m/Y" → DateTimeImmutable|null.
     */
    private function parseChileanDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat('d/m/Y', $value);

        return $dt instanceof DateTimeImmutable ? $dt : null;
    }

    private function nullable(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $data
     */
    private function ok(array $data, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response((new RestResponse('ok', $data)