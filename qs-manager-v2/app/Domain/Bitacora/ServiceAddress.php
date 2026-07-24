<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use InvalidArgumentException;

final class ServiceAddress
{
    private readonly string $value;

    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Service address is required.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Un ServiceAddress siempre representa un servicio a domicilio (el
     * caso "sin domicilio" se modela con ausencia de ServiceAddress, no con
     * un valor vacio -- este metodo existe para que el codigo consumidor
     * exprese la intencion sin repetir la comprobacion, igual que en V1.
     */
    public function isDomicilio(): bool
    {
        return $this->value !== '';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
