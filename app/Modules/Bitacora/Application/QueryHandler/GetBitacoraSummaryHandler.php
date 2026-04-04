<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\QueryHandler;

use QS\Modules\Bitacora\Application\Query\GetBitacoraSummary;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;

final class GetBitacoraSummaryHandler
{
    public function __construct(private readonly BitacoraRepository $bitacoraRepository)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function handle(GetBitacoraSummary $query): ?array
    {
        $bitacora = $this->bitacoraRepository->findById($query->id);

        if ($bitacora === null) {
            return null;
        }

        return [
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
        ];
    }
}
