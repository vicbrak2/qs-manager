<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use InvalidArgumentException;

/**
 * Un tramo del traslado con su tiempo real registrado por la operacion
 * (ej. "Estudio -> Metro Macul", 10 min). La holgura por trafico NO se
 * guarda por tramo: la aplica TravelPlanCalculator al calcular la salida.
 */
final class TravelLeg
{
    private readonly string $nombre;

    public function __construct(string $nombre, private readonly int $minutos)
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('Travel leg name is required.');
        }
        if ($minutos < 0) {
            throw new InvalidArgumentException('Travel leg minutes can not be negative.');
        }
        $this->nombre = $nombre;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function minutos(): int
    {
        return $this->minutos;
    }

    /**
     * @return array{nombre: string, minutos: int}
     */
    public function toArray(): array
    {
        return ['nombre' => $this->nombre, 'minutos' => $this->minutos];
    }
}
