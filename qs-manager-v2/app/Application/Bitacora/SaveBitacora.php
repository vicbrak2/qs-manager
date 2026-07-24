<?php

declare(strict_types=1);

namespace QSManager\Application\Bitacora;

use DateTimeImmutable;
use InvalidArgumentException;
use QSManager\Domain\Bitacora\Bitacora;
use QSManager\Domain\Bitacora\BitacoraPolicy;
use QSManager\Domain\Bitacora\BitacoraRepository;
use QSManager\Domain\Bitacora\PickupPoint;
use QSManager\Domain\Bitacora\RoutePlan;
use QSManager\Domain\Bitacora\ServiceAddress;
use QSManager\Domain\Bitacora\TravelDuration;

final class SaveBitacora
{
    public function __construct(
        private readonly BitacoraRepository $bitacoras,
        private readonly BitacoraPolicy $policy,
    ) {
    }

    /**
     * Crea (id null) o actualiza (id dado) una bitacora ya validada a nivel
     * de campos. Las reglas de negocio (equipo asignado, etc.) las aplica
     * BitacoraPolicy aca -- un rechazo sale como InvalidArgumentException
     * con los mensajes de la policy.
     *
     * @param array<string, mixed> $data
     */
    public function execute(array $data, ?int $id = null): Bitacora
    {
        $now = new DateTimeImmutable();

        $bitacora = new Bitacora(
            $id,
            $data['fecha_servicio'],
            $data['tipo_servicio'],
            $data['mua_id'],
            $data['estilista_id'],
            $data['clienta_nombre'],
            new ServiceAddress($data['direccion_servicio']),
            new RoutePlan(
                new PickupPoint($data['punto_salida']),
                $data['orden_recogida'],
                new TravelDuration($data['tiempo_traslado_min']),
                $data['hora_llegada'],
            ),
            $data['notas_logisticas'],
            $data['costo_staff_clp'],
            $data['precio_cliente_clp'],
            [],
            $now,
            $now,
        );

        $errors = $this->policy->validate($bitacora);
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return $this->bitacoras->save($bitacora);
    }
}
