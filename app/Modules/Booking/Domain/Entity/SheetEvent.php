<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Entity;

use DateTimeImmutable;

/**
 * Representa una fila del Google Sheet de Qamiluna.
 * Es la fuente de verdad unificada para reservas,
 * sin importar si el origen fue el formulario WP o edición directa en el Sheet.
 */
final class SheetEvent
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $sheetName,
        private readonly int $rowIndex,
        private readonly string $encargada,
        private readonly string $dia,
        private readonly ?DateTimeImmutable $fechaServicio,
        private readonly ?string $horaInicio,
        private readonly string $servicio,
        private readonly int $cantidad,
        private readonly string $clientaNombre,
        private readonly ?string $telefono,
        private readonly ?string $direccion,
        private readonly ?string $comuna,
        private readonly string $traslado,
        private readonly int $abonoClp,
        private readonly ?DateTimeImmutable $fechaAbono,
        private readonly int $valorServicioClp,
        private readonly int $totalServicioClp,
        private readonly int $totalPorPagarClp,
        private readonly string $accion,
        private readonly string $estadoEvento,
        private readonly ?string $idEventoGcal,
        private readonly string $origen,
        private readonly DateTimeImmutable $syncedAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sheetName(): string
    {
        return $this->sheetName;
    }

    public function rowIndex(): int
    {
        return $this->rowIndex;
    }

    public function encargada(): string
    {
        return $this->encargada;
    }

    public function dia(): string
    {
        return $this->dia;
    }

    public function fechaServicio(): ?DateTimeImmutable
    {
        return $this->fechaServicio;
    }

    public function horaInicio(): ?string
    {
        return $this->horaInicio;
    }

    public function servicio(): string
    {
        return $this->servicio;
    }

    public function cantidad(): int
    {
        return $this->cantidad;
    }

    public function clientaNombre(): string
    {
        return $this->clientaNombre;
    }

    public function telefono(): ?string
    {
        return $this->telefono;
    }

    public function direccion(): ?string
    {
        return $this->direccion;
    }

    public function comuna(): ?string
    {
        return $this->comuna;
    }

    public function traslado(): string
    {
        return $this->traslado;
    }

    public function abonoClp(): int
    {
        return $this->abonoClp;
    }

    public function fechaAbono(): ?DateTimeImmutable
    {
        return $this->fechaAbono;
    }

    public function valorServicioClp(): int
    {
        return $this->valorServicioClp;
    }

    public function totalServicioClp(): int
    {
        return $this->totalServicioClp;
    }

    public function totalPorPagarClp(): int
    {
        return $this->totalPorPagarClp;
    }

    public function accion(): string
    {
        return $this->accion;
    }

    public function estadoEvento(): string
    {
        return $this->estadoEvento;
    }

    public function idEventoGcal(): ?string
    {
        return $this->idEventoGcal;
    }

    public function origen(): string
    {
        return $this->origen;
    }

    public function syncedAt(): DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function tieneSincronizacionGcal(): bool
    {
        return $this->idEventoGcal !== null && $this->idEventoGcal !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'sheet_name'          => $this->sheetName,
            'row_index'           => $this->rowIndex,
            'encargada'           => $this->encargada,
            'dia'                 => $this->dia,
            'fecha_servicio'      => $this->fechaServicio?->format('Y-m-d'),
            'hora_inicio'         => $this->horaInicio,
            'servicio'            => $this->servicio,
            'cantidad'            => $this->cantidad,
            'clienta_nombre'      => $this->clientaNombre,
            'telefono'            => $this->telefono,
            'direccion'           => $this->direccion,
            'comuna'              => $this->comuna,
            'traslado'            => $this->traslado,
            'abono_clp'           => $this->abonoClp,
            'fecha_abono'         => $this->fechaAbono?->format('Y-m-d'),
            'valor_servicio_clp'  => $this->valorServicioClp,
            'total_servicio_clp'  => $this->totalServicioClp,
            'total_por_pagar_clp' => $this->totalPorPagarClp,
            'accion'              => $this->accion,
            'estado_evento'       => $this->estadoEvento,
            'id_evento_gcal'      => $this->idEventoGcal,
            'origen'              => $this->origen,
            'synced_at'           => $this->syncedAt->format('Y-m-d H:i:s'),
        ];
    }
}
