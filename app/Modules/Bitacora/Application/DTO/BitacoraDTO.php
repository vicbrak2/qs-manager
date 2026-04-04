<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\DTO;

use QS\Modules\Bitacora\Domain\Entity\Bitacora;

final class BitacoraDTO
{
    public function __construct(private readonly Bitacora $bitacora)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->bitacora->id(),
            'fecha_servicio' => $this->bitacora->fechaServicio(),
            'tipo_servicio' => $this->bitacora->tipoServicio(),
            'mua_id' => $this->bitacora->muaId(),
            'estilista_id' => $this->bitacora->estilistaId(),
            'clienta_nombre' => $this->bitacora->clientaNombre(),
            'direccion_servicio' => $this->bitacora->serviceAddress()->value(),
            'hora_llegada' => $this->bitacora->routePlan()->arrivalTime(),
            'punto_salida' => $this->bitacora->routePlan()->pickupPoint()->value(),
            'orden_recogida' => $this->bitacora->routePlan()->pickupOrder(),
            'tiempo_traslado_min' => $this->bitacora->routePlan()->travelDuration()->minutes(),
            'recommended_travel_minimum_met' => $this->bitacora->routePlan()->travelDuration()->meetsRecommendedMinimum(),
            'notas_logisticas' => $this->bitacora->notasLogisticas(),
            'costo_staff_clp' => $this->bitacora->costoStaffClp(),
            'precio_cliente_clp' => $this->bitacora->precioClienteClp(),
            'projected_margin_clp' => $this->bitacora->projectedMarginClp(),
            'notes' => array_map(
                static fn ($note): array => $note->toArray(),
                $this->bitacora->notes()
            ),
            'created_at' => $this->bitacora->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->bitacora->updatedAt()->format(DATE_ATOM),
        ];
    }
}
