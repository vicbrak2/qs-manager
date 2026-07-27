<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

final class TravelItinerary
{
    /**
     * @param list<TravelLeg> $legs
     */
    public function __construct(private readonly array $legs = [])
    {
    }

    /**
     * @param list<array{destino: string, minutos: int, recoge?: ?string}> $rows
     */
    public static function fromArray(array $rows): self
    {
        return new self(array_map(
            static fn (array $row): TravelLeg => new TravelLeg(
                (string) $row['destino'],
                (int) $row['minutos'],
                isset($row['recoge']) ? (string) $row['recoge'] : null,
            ),
            $rows
        ));
    }

    /**
     * Orden de traslado completo: "Metro Macul → Las Condes (Cami) →
     * La Reina (Paz) → La Florida".
     */
    public function routeFrom(string $puntoSalida): string
    {
        $stops = array_map(static fn (TravelLeg $leg): string => $leg->stopLabel(), $this->legs);

        return implode(' → ', [$puntoSalida, ...$stops]);
    }

    /**
     * Solo los lugares, sin las personas, para la ruta estimada.
     *
     * @return list<string>
     */
    public function stops(): array
    {
        return array_map(static fn (TravelLeg $leg): string => $leg->destino(), $this->legs);
    }

    /**
     * @return list<TravelLeg>
     */
    public function legs(): array
    {
        return $this->legs;
    }

    public function isEmpty(): bool
    {
        return $this->legs === [];
    }

    public function totalMinutes(): int
    {
        return array_sum(array_map(static fn (TravelLeg $leg): int => $leg->minutos(), $this->legs));
    }

    /**
     * @return list<array{nombre: string, minutos: int}>
     */
    public function toArray(): array
    {
        return array_map(static fn (TravelLeg $leg): array => $leg->toArray(), $this->legs);
    }
}
