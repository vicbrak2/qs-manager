<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Domain\Policy;

use QS\Modules\Bitacora\Domain\Entity\Bitacora;

final class BitacoraPolicy
{
    /**
     * @return array<int, string>
     */
    public function validate(Bitacora $bitacora): array
    {
        $errors = [];

        if (trim($bitacora->fechaServicio()) === '') {
            $errors[] = 'La fecha de servicio es obligatoria.';
        }

        if (trim($bitacora->tipoServicio()) === '') {
            $errors[] = 'El tipo de servicio es obligatorio.';
        }

        if (! $bitacora->hasAssignedTeam()) {
            $errors[] = 'La bitacora requiere equipo asignado.';
        }

        if ($bitacora->serviceAddress()->isDomicilio() && trim($bitacora->routePlan()->pickupPoint()->value()) === '') {
            $errors[] = 'El punto de salida es obligatorio para servicios a domicilio.';
        }

        return $errors;
    }

    public function isSatisfiedBy(Bitacora $bitacora): bool
    {
        return $this->validate($bitacora) === [];
    }
}
