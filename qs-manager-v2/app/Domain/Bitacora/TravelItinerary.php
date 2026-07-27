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
     * @param list<array{nombre: string, minutos: int, recoge?: ?string, comuna?: ?string}> $rows
     */
    public static function fromArray(array $rows): self
    {
        return new self(array_map(
            static fn (array $row): TravelLeg => new TravelLeg(
                (string) $row['nombre'],
                (int) $row['minutos'],
                isset($row['recoge']) ? (string) $row['recoge'] : null,
                isset($row['comuna']) ? (string) $row['comuna'] : null,
            ),
            $rows
        ));
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
