<?php

declare(strict_types=1);

namespace QSManager\Domain\Team;

/**
 * Las planillas registran la encargada de un servicio en UN solo campo, con
 * las dos profesionales juntas y en orden fijo: primero la maquilladora,
 * despues la estilista (ej. "Cami - Paz"). Este value object separa ese
 * texto para que el resto del sistema pueda trabajar con personas y no con
 * un string opaco -- antes de esto la reserva quedaba sin nadie asignado.
 */
final class StaffAssignment
{
    /**
     * @param list<string> $names
     */
    private function __construct(private readonly array $names)
    {
    }

    public static function fromSheetValue(?string $value): self
    {
        if ($value === null || trim($value) === '') {
            return new self([]);
        }

        $parts = preg_split('/\s*[-\/,]\s*|\s+y\s+/ui', trim($value)) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $names[] = $part;
            }
        }

        return new self($names);
    }

    public function mua(): ?string
    {
        return $this->names[0] ?? null;
    }

    public function estilista(): ?string
    {
        return $this->names[1] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }

    public function isEmpty(): bool
    {
        return $this->names === [];
    }
}
