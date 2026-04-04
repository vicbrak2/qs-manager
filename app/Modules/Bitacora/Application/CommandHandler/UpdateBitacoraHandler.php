<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\CommandHandler;

use DateTimeImmutable;
use InvalidArgumentException;
use QS\Modules\Bitacora\Application\Command\UpdateBitacora;
use QS\Modules\Bitacora\Application\DTO\BitacoraDTO;
use QS\Modules\Bitacora\Domain\Entity\Bitacora;
use QS\Modules\Bitacora\Domain\Entity\RoutePlan;
use QS\Modules\Bitacora\Domain\Policy\BitacoraPolicy;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;
use QS\Modules\Bitacora\Domain\ValueObject\PickupPoint;
use QS\Modules\Bitacora\Domain\ValueObject\ServiceAddress;
use QS\Modules\Bitacora\Domain\ValueObject\TravelDuration;
use RuntimeException;

final class UpdateBitacoraHandler
{
    public function __construct(
        private readonly BitacoraRepository $bitacoraRepository,
        private readonly BitacoraPolicy $bitacoraPolicy
    ) {
    }

    public function handle(UpdateBitacora $command): ?BitacoraDTO
    {
        $existing = $this->bitacoraRepository->findById($command->id);

        if ($existing === null) {
            return null;
        }

        $bitacora = new Bitacora(
            $existing->id(),
            trim($command->fechaServicio),
            trim($command->tipoServicio),
            $command->muaId,
            $command->estilistaId,
            trim($command->clientaNombre),
            new ServiceAddress($command->direccionServicio),
            new RoutePlan(
                new PickupPoint($command->puntoSalida),
                $this->nullableString($command->ordenRecogida),
                new TravelDuration($command->tiempoTrasladoMin ?? 0),
                $this->nullableString($command->horaLlegada)
            ),
            $this->nullableString($command->notasLogisticas),
            max(0, $command->costoStaffClp ?? 0),
            max(0, $command->precioClienteClp ?? 0),
            $existing->notes(),
            $existing->createdAt(),
            new DateTimeImmutable('now')
        );

        $errors = $this->bitacoraPolicy->validate($bitacora);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $saved = $this->bitacoraRepository->save($bitacora);

        if ($saved->id() === null) {
            throw new RuntimeException('Bitacora was not persisted correctly.');
        }

        return new BitacoraDTO($saved);
    }

    private function nullableString(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
