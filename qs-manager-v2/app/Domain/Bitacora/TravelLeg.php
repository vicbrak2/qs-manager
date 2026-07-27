<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use InvalidArgumentException;

/**
 * Un tramo es "viajar hasta un punto": el destino (comuna o lugar), cuanto
 * demora llegar ahi, y opcionalmente a quien se recoge en ese punto.
 *
 * El destino se modela como comuna/lugar y no como direccion: la bitacora la
 * lee todo el equipo, y la regla del estudio es publicar solo la comuna en
 * las recogidas. Encadenando los destinos desde el punto de salida sale el
 * orden de traslado sin tener que interpretar texto libre.
 */
final class TravelLeg
{
    private readonly string $destino;
    private readonly ?string $recoge;

    public function __construct(string $destino, private readonly int $minutos, ?string $recoge = null)
    {
        $destino = trim($destino);
        if ($destino === '') {
            throw new InvalidArgumentException('Travel leg destination is required.');
        }
        if ($minutos < 0) {
            throw new InvalidArgumentException('Travel leg minutes can not be negative.');
        }

        $this->destino = $destino;
        $this->recoge = $recoge !== null && trim($recoge) !== '' ? trim($recoge) : null;
    }

    public function destino(): string
    {
        return $this->destino;
    }

    public function minutos(): int
    {
        return $this->minutos;
    }

    public function recoge(): ?string
    {
        return $this->recoge;
    }

    public function isPickup(): bool
    {
        return $this->recoge !== null;
    }

    /**
     * Como aparece el destino en el orden de traslado: "Las Condes (Cami)"
     * cuando hay recogida, "La Florida" cuando es solo de paso o destino.
     */
    public function stopLabel(): string
    {
        return $this->recoge === null ? $this->destino : sprintf('%s (%s)', $this->destino, $this->recoge);
    }

    /**
     * @return array{destino: string, minutos: int, recoge?: string}
     */
    public function toArray(): array
    {
        $data = ['destino' => $this->destino, 'minutos' => $this->minutos];
        if ($this->recoge !== null) {
            $data['recoge'] = $this->recoge;
        }

        return $data;
    }
}
