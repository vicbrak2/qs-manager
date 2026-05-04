<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\Persistence;

use DateTimeImmutable;
use QS\Modules\Booking\Domain\Entity\SheetEvent;
use QS\Modules\Booking\Domain\Repository\SheetEventRepository;
use RuntimeException;

final class WpdbSheetEventRepository implements SheetEventRepository
{
    private string $table;

    public function __construct(private readonly \wpdb $wpdb)
    {
        $this->table = $wpdb->prefix . 'qs_sheet_events';
    }

    /**
     * @return array<int, SheetEvent>
     */
    public function findAll(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT * FROM {$this->table} ORDER BY fecha_servicio ASC, hora_inicio ASC",
            ARRAY_A
        );

        return array_map(
            fn (array $row): SheetEvent => $this->hydrate($row),
            is_array($rows) ? $rows : []
        );
    }

    /**
     * @return array<int, SheetEvent>
     */
    public function findBySheetName(string $sheetName): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE sheet_name = %s ORDER BY row_index ASC",
                $sheetName
            ),
            ARRAY_A
        );

        return array_map(
            fn (array $row): SheetEvent => $this->hydrate($row),
            is_array($rows) ? $rows : []
        );
    }

    public function findBySheetAndRow(string $sheetName, int $rowIndex): ?SheetEvent
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE sheet_name = %s AND row_index = %d",
                $sheetName,
                $rowIndex
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function upsert(SheetEvent $event): SheetEvent
    {
        $data   = $this->toRow($event);
        $format = $this->formats();

        $existing = $this->findBySheetAndRow($event->sheetName(), $event->rowIndex());

        if ($existing !== null && $existing->id() !== null) {
            $this->wpdb->update(
                $this->table,
                $data,
                ['id' => $existing->id()],
                $format,
                ['%d']
            );

            $saved = $this->findBySheetAndRow($event->sheetName(), $event->rowIndex());
        } else {
            $this->wpdb->insert($this->table, $data, $format);

            $insertedId = (int) $this->wpdb->insert_id;

            if ($insertedId <= 0) {
                throw new RuntimeException('SheetEvent insert failed: ' . $this->wpdb->last_error);
            }

            $saved = $this->findById($insertedId);
        }

        if ($saved === null) {
            throw new RuntimeException('SheetEvent not found after upsert.');
        }

        return $saved;
    }

    /**
     * @param array<int, SheetEvent> $events
     */
    public function upsertBatch(array $events): int
    {
        $count = 0;

        foreach ($events as $event) {
            $this->upsert($event);
            $count++;
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function findById(int $id): ?SheetEvent
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function hydrate(array $row): SheetEvent
    {
        return new SheetEvent(
            id:                 (int) $row['id'],
            sheetName:          (string) $row['sheet_name'],
            rowIndex:           (int) $row['row_index'],
            encargada:          (string) $row['encargada'],
            dia:                (string) $row['dia'],
            fechaServicio:      $this->parseDate((string) ($row['fecha_servicio'] ?? '')),
            horaInicio:         isset($row['hora_inicio']) && is_string($row['hora_inicio']) ? $row['hora_inicio'] : null,
            servicio:           (string) $row['servicio'],
            cantidad:           (int) $row['cantidad'],
            clientaNombre:      (string) $row['clienta_nombre'],
            telefono:           isset($row['telefono']) && is_string($row['telefono']) ? $row['telefono'] : null,
            direccion:          isset($row['direccion']) && is_string($row['direccion']) ? $row['direccion'] : null,
            comuna:             isset($row['comuna']) && is_string($row['comuna']) ? $row['comuna'] : null,
            traslado:           (string) $row['traslado'],
            abonoClp:           (int) $row['abono_clp'],
            fechaAbono:         $this->parseDate((string) ($row['fecha_abono'] ?? '')),
            valorServicioClp:   (int) $row['valor_servicio_clp'],
            totalServicioClp:   (int) $row['total_servicio_clp'],
            totalPorPagarClp:   (int) $row['total_por_pagar_clp'],
            accion:             (string) $row['accion'],
            estadoEvento:       (string) $row['estado_evento'],
            idEventoGcal:       isset($row['id_evento_gcal']) && is_string($row['id_evento_gcal']) ? $row['id_evento_gcal'] : null,
            origen:             (string) $row['origen'],
            syncedAt:           new DateTimeImmutable((string) $row['synced_at']),
            createdAt:          new DateTimeImmutable((string) $row['created_at']),
            updatedAt:          new DateTimeImmutable((string) $row['updated_at']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(SheetEvent $event): array
    {
        return [
            'sheet_name'          => $event->sheetName(),
            'row_index'           => $event->rowIndex(),
            'encargada'           => $event->encargada(),
            'dia'                 => $event->dia(),
            'fecha_servicio'      => $event->fechaServicio()?->format('Y-m-d'),
            'hora_inicio'         => $event->horaInicio(),
            'servicio'            => $event->servicio(),
            'cantidad'            => $event->cantidad(),
            'clienta_nombre'      => $event->clientaNombre(),
            'telefono'            => $event->telefono(),
            'direccion'           => $event->direccion(),
            'comuna'              => $event->comuna(),
            'traslado'            => $event->traslado(),
            'abono_clp'           => $event->abonoClp(),
            'fecha_abono'         => $event->fechaAbono()?->format('Y-m-d'),
            'valor_servicio_clp'  => $event->valorServicioClp(),
            'total_servicio_clp'  => $event->totalServicioClp(),
            'total_por_pagar_clp' => $event->totalPorPagarClp(),
            'accion'              => $event->accion(),
            'estado_evento'       => $event->estadoEvento(),
            'id_evento_gcal'      => $event->idEventoGcal(),
            'origen'              => $event->origen(),
            'synced_at'           => $event->syncedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function formats(): array
    {
        return ['%s','%d','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s','%d','%s','%d','%d','%d','%s','%s','%s','%s','%s'];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            r