<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\CommandHandler;

use DateTimeImmutable;
use InvalidArgumentException;
use QS\Modules\Bitacora\Application\Command\CreateBitacora;
use QS\Modules\Bitacora\Application\DTO\BitacoraDTO;
use QS\Modules\Bitacora\Domain\Entity\Bitacora;
use QS\Modules\Bitacora\Domain\Entity\RoutePlan;
use QS\Modules\Bitacora\Domain\Policy\BitacoraPolicy;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;
use QS\Modules\Bitacora\Domain\ValueObject\PickupPoint;
use QS\Modules\Bitacora\Domain\ValueObject\ServiceAddress;
use QS\Modules\Bitacora\Domain\ValueObject\TravelDuration;

final class CreateBitacoraHandler
{
    public function __construct(
        private readonly BitacoraRepository $bitacoraRepository,
        private readonly BitacoraPolicy $bitacoraPolicy
    ) {
    }

    public function handle(CreateBitacora $command): BitacoraDTO
    {
        $now = new DateTimeImmutable('now');
        $bitacora = new Bitacora(
            null,
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
            [],
            $now,
            $now
        );

        $errors = $this->bitacoraPolicy->validate($bitacora);

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return new BitacoraDTO($this->bitacoraRepository->save($bitacora));
    }

    private function nullableString(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
