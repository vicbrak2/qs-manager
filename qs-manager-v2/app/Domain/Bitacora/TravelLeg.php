<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use InvalidArgumentException;

/**
 * Un tramo del traslado con su tiempo real registrado por la operacion
 * (ej. "Metro Macul -> Providencia", 20 min). La holgura por trafico NO se
 * guarda por tramo: la aplica TravelPlanCalculator al calcular la salida.
 *
 * Un tramo puede terminar en una recogida de profesional. En ese caso se
 * guarda a quien se recoge y en que comuna -- **solo la comuna**, nunca la
 * direccion exacta, porque la bitacora se comparte con todo el equipo.
 */
final class TravelLeg
{
    private readonly string $nombre;
    private readonly ?string $recoge;
    private readonly ?string $comuna;

    public function __construct(
        string $nombre,
        private readonly int $minutos,
        ?string $recoge = null,
        ?string $comuna = null,
    ) {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('Travel leg name is required.');
        }
        if ($minutos < 0) {
            throw new InvalidArgumentException('Travel leg minutes can not be negative.');
        }

        $this->nombre = $nombre;
        $this->recoge = $recoge !== null && trim($recoge) !== '' ? trim($recoge) : null;
        $this->comuna = $comuna !== null && trim($comuna) !== '' ? trim($comuna) : null;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function minutos(): int
    {
        return $this->minutos;
    }

    public function recoge(): ?string
    {
        return $this->recoge;
    }

    public function comuna(): ?string
    {
        return $this->comuna;
    }

    public function isPickup(): bool
    {
        return $this->recoge !== null;
    }

    /**
     * Etiqueta de la recogida para la bitacora: nombre y comuna, sin
     * direccion. Ej. "Paz (Providencia)".
     */
    public function pickupLabel(): ?string
    {
        if ($this->recoge === null) {
            return null;
        }

        return $this->comuna === null ? $this->recoge : sprintf('%s (%s)', $this->recoge, $this->comuna);
    }

    /**
     * @return array{nombre: string, minutos: int, recoge?: string, comuna?: string}
     */
    public function toArray(): array
    {
        $data = ['nombre' => $this->nombre, 'minutos' => $this->minutos];
        if ($this->recoge !== null) {
            $data['recoge'] = $this->recoge;
        }
        if ($this->comuna !== null) {
            $data['comuna'] = $this->comuna;
        }

        return $data;
    }
}
