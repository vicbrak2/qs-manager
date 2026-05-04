<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Repository;

use QS\Modules\Booking\Domain\Entity\SheetEvent;

interface SheetEventRepository
{
    /**
     * @return array<int, SheetEvent>
     */
    public function findAll(): array;

    /**
     * @return array<int, SheetEvent>
     */
    public function findBySheetName(string $sheetName): array;

    public function findBySheetAndRow(string $sheetName, int $rowIndex): ?SheetEvent;

    /**
     * Upsert por (sheet_name, row_index).
     * Si existe → actualiza. Si no → inserta.
     */
    public function upsert(SheetEvent $event): SheetEvent;

    /**
     * Upsert masivo — misma lógica aplicada a cada elemento.
     *
     * @param array<int, SheetEvent> $events
     * @return int Número de filas procesadas
     */
    public function upsertBatch(array $events): int;
}
