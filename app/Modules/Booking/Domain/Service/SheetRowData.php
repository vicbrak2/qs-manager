<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Service;

use DateTimeImmutable;

/**
 * Value object que encapsula todos los campos necesarios para insertar
 * una fila en la hoja de cálculo de Qamiluna Studio.
 */
final class SheetRowData
{
    public function __construct(
        public readonly string $encargada,
        public readonly string $clientName,
        public readonly string $clientEmail,
        public readonly string $clientPhone,
        public readonly string $serviceName,
        public readonly DateTimeImmutable $startTime,
        public readonly DateTimeImmutable $endTime,
        public readonly string $direccion,
        public readonly string $comuna,
        public readonly string $traslado,
        public readonly string $valorServicio,
        public readonly int $cantidad,
        public readonly bool $abono,
        public readonly string $montoAbono,
        public readonly string $fechaAbono,
    ) {
    }
}
