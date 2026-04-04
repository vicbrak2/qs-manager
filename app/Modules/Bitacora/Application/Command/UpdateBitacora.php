<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\Command;

final class UpdateBitacora
{
    public function __construct(
        public readonly int $id,
        public readonly string $fechaServicio,
        public readonly string $tipoServicio,
        public readonly ?int $muaId,
        public readonly ?int $estilistaId,
        public readonly string $clientaNombre,
        public readonly string $direccionServicio,
        public readonly ?string $horaLlegada,
        public readonly string $puntoSalida,
        public readonly ?string $ordenRecogida,
        public readonly ?int $tiempoTrasladoMin,
        public readonly ?string $notasLogisticas,
        public readonly ?int $costoStaffClp,
        public readonly ?int $precioClienteClp
    ) {
    }
}
