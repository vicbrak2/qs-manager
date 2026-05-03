<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Service;

use DateTimeImmutable;

interface SheetsSyncGateway
{
    /**
     * Verifica si existe un conflicto de horario en la hoja del mes correspondiente.
     * Retorna el nombre del evento en conflicto, o null si no hay conflicto.
     */
    public function checkConflict(DateTimeImmutable $startTime, DateTimeImmutable $endTime): ?string;

    /**
     * Añade una fila completa con todos los datos de la reserva al Google Sheet.
     * Retorna el nombre de la hoja donde se insertó la fila.
     */
    public function appendRow(SheetRowData $data): string;
}
