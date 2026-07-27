<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use DateTimeImmutable;

/**
 * Aggregate root de un registro de bitacora (servicio + logistica de
 * traslado + equipo asignado). Plomeria requerida por BitacoraPolicy -- no
 * estaba en la lista explicita del plan, pero la policy no se puede migrar
 * ni probar sin el objeto que valida.
 */
final class Bitacora
{
    /**
     * @param list<TravelNote> $notes
     */
    public function __construct(
        private readonly ?int $id,
        private readonly ?int $bookingId,
        private readonly ?string $bookingExternalId,
        private readonly string $fechaServicio,
        private readonly string $tipoServicio,
        private readonly ?int $muaId,
        private readonly ?int $estilistaId,
        private readonly string $clientaNombre,
        private readonly ServiceAddress $serviceAddress,
        private readonly RoutePlan $routePlan,
        private readonly ?string $horaInicioServicio,
        private readonly ?string $horaFinServicio,
        private readonly TravelItinerary $itinerario,
        private readonly ?string $objetivo,
        private readonly ?string $consideraciones,
        private readonly ?string $notasLogisticas,
        private readonly int $costoStaffClp,
        private readonly int $precioClienteClp,
        private readonly array $notes,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function bookingId(): ?int
    {
        return $this->bookingId;
    }

    public function bookingExternalId(): ?string
    {
        return $this->bookingExternalId;
    }

    public function fechaServicio(): string
    {
        return $this->fechaServicio;
    }

    public function tipoServicio(): string
    {
        return $this->tipoServicio;
    }

    public function muaId(): ?int
    {
        return $this->muaId;
    }

    public function estilistaId(): ?int
    {
        return $this->estilistaId;
    }

    public function clientaNombre(): string
    {
        return $this->clientaNombre;
    }

    public function serviceAddress(): ServiceAddress
    {
        return $this->serviceAddress;
    }

    public function routePlan(): RoutePlan
    {
        return $this->routePlan;
    }

    public function horaInicioServicio(): ?string
    {
        return $this->horaInicioServicio;
    }

    public function horaFinServicio(): ?string
    {
        return $this->horaFinServicio;
    }

    public function itinerario(): TravelItinerary
    {
        return $this->itinerario;
    }

    public function objetivo(): ?string
    {
        return $this->objetivo;
    }

    public function consideraciones(): ?string
    {
        return $this->consideraciones;
    }

    public function notasLogisticas(): ?string
    {
        return $this->notasLogisticas;
    }

    public function costoStaffClp(): int
    {
        return $this->costoStaffClp;
    }

    public function precioClienteClp(): int
    {
        return $this->precioClienteClp;
    }

    /**
     * @return list<TravelNote>
     */
    public function notes(): array
    {
        return $this->notes;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function hasAssignedTeam(): bool
    {
        return $this->muaId !== null || $this->estilistaId !== null;
    }

    public function projectedMarginClp(): int
    {
        return $this->precioClienteClp - $this->costoStaffClp;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $calculadora = new TravelPlanCalculator();

        return [
            'id' => $this->id,
            'booking_id' => $this->bookingId,
            'booking_external_id' => $this->bookingExternalId,
            'fecha_servicio' => $this->fechaServicio,
            'tipo_servicio' => $this->tipoServicio,
            'mua_id' => $this->muaId,
            'estilista_id' => $this->estilistaId,
            'clienta_nombre' => $this->clientaNombre,
            'direccion_servicio' => $this->serviceAddress->value(),
            'route_plan' => $this->routePlan->toArray(),
            'hora_inicio_servicio' => $this->horaInicioServicio,
            'hora_fin_servicio' => $this->horaFinServicio,
            'tramos' => $this->itinerario->toArray(),
            'hora_llegada_objetivo' => $calculadora->llegadaObjetivo($this->horaInicioServicio),
            'hora_salida_sugerida' => $calculadora->salidaSugerida($this->horaInicioServicio, $this->itinerario),
            'recogidas' => $calculadora->pickupSchedule($this->horaInicioServicio, $this->itinerario),
            'orden_traslado' => $this->itinerario->isEmpty()
                ? null
                : $this->itinerario->routeFrom($this->routePlan->pickupPoint()->value()),
            'objetivo' => $this->objetivo,
            'consideraciones' => $this->consideraciones,
            'notas_logisticas' => $this->notasLogisticas,
            'costo_staff_clp' => $this->costoStaffClp,
            'precio_cliente_clp' => $this->precioClienteClp,
            'projected_margin_clp' => $this->projectedMarginClp(),
            'notes' => array_map(static fn (TravelNote $note): array => [
                'message' => $note->message(),
                'author_user_id' => $note->authorUserId(),
                'created_at' => $note->createdAt()->format(DATE_ATOM),
            ], $this->notes),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
