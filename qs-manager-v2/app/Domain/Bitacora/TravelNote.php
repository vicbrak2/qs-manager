<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Nota de logistica sobre un traslado (ej. "quedaron de esperar en la
 * reja"). Plomeria requerida por Bitacora::notes() -- no estaba en la lista
 * explicita del plan de migracion, pero sin ella Bitacora no se puede
 * construir ni BitacoraPolicy se puede probar de verdad.
 */
final class TravelNote
{
    private readonly string $message;

    public function __construct(
        string $message,
        private readonly ?int $authorUserId,
        private readonly DateTimeImmutable $createdAt
    ) {
        $message = trim($message);

        if ($message === '') {
            throw new InvalidArgumentException('Travel note message is required.');
        }

        $this->message = $message;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function authorUserId(): ?int
    {
        return $this->authorUserId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
