<?php

declare(strict_types=1);

namespace QSManager\Application\Bitacora;

use DateTimeImmutable;
use InvalidArgumentException;
use QSManager\Domain\Bitacora\Bitacora;
use QSManager\Domain\Bitacora\BitacoraBriefing;
use QSManager\Domain\Bitacora\BitacoraPolicy;
use QSManager\Domain\Bitacora\BitacoraRepository;
use QSManager\Domain\Bitacora\PickupPoint;
use QSManager\Domain\Bitacora\RoutePlan;
use QSManager\Domain\Bitacora\ServiceAddress;
use QSManager\Domain\Bitacora\TravelDuration;
use QSManager\Domain\Bitacora\TravelItinerary;
use QSManager\Domain\Bitacora\TravelPlanCalculator;
use QSManager\Domain\Booking\BookingRepository;

final class SaveBitacora
{
    /**
     * Punto de salida habitual del estudio. Se usa cuando el usuario no
     * indica otro (alternativas conocidas: estudio Huerfanos 1044).
     */
    public const PUNTO_SALIDA_HABITUAL = 'Metro Macul';

    public function __construct(
        private readonly BitacoraRepository $bitacoras,
        private readonly BookingRepository $bookings,
        private readonly BitacoraPolicy $policy,
        private readonly BitacoraBriefing $briefing = new BitacoraBriefing(),
        private readonly TravelPlanCalculator $calculator = new TravelPlanCalculator(),
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
        $bookingExternalId = null;

        if ($data['booking_id'] !== null) {
            if ($id === null) {
                $existing = $this->bitacoras->findByBookingId($data['booking_id']);
                if ($existing !== null) {
                    throw new InvalidArgumentException(
                        sprintf('La reserva #%d ya tiene bitácora (#%d).', $data['booking_id'], $existing->id())
                    );
                }
            }

            $booking = $this->bookings->findById($data['booking_id']);
            $bookingData = $booking?->toArray() ?? [];
            if (($bookingData['source_sheet'] ?? null) !== null) {
                $bookingExternalId = $bookingData['sheet_external_id'] ?? null;
            }
        }

        // Los tramos son la fuente de verdad del tiempo de viaje cuando
        // existen: el total legacy (tiempo_traslado_min) se deriva de ellos.
        $itinerario = TravelItinerary::fromArray($data['tramos']);
        $tiempoTrasladoMin = $itinerario->isEmpty()
            ? $data['tiempo_traslado_min']
            : $itinerario->totalMinutes();

        // Campos que el usuario no tiene por que escribir: se completan
        // solos y quedan editables si quiere ajustarlos.
        $puntoSalida = $data['punto_salida'] ?? null;
        if ($puntoSalida === null || trim((string) $puntoSalida) === '') {
            $puntoSalida = self::PUNTO_SALIDA_HABITUAL;
        }

        $objetivo = $data['objetivo'];
        if ($objetivo === null || trim($objetivo) === '') {
            $objetivo = $this->briefing->objetivo($data['tipo_servicio']);
        }

        $consideraciones = $data['consideraciones'];
        if ($consideraciones === null || trim($consideraciones) === '') {
            $consideraciones = $this->briefing->consideraciones(
                $this->calculator->salidaSugerida($data['hora_inicio_servicio'], $itinerario),
                $itinerario->totalMinutes(),
                $data['direccion_servicio'],
                $this->calculator->pickupSchedule($data['hora_inicio_servicio'], $itinerario),
            );
        }

        $bitacora = new Bitacora(
            $id,
            $data['booking_id'],
            $bookingExternalId,
            $data['fecha_servicio'],
            $data['tipo_servicio'],
            $data['mua_id'],
            $data['estilista_id'],
            $data['clienta_nombre'],
            new ServiceAddress($data['direccion_servicio']),
            new RoutePlan(
                new PickupPoint($puntoSalida),
                $data['orden_recogida'],
                new TravelDuration($tiempoTrasladoMin),
                $data['hora_llegada'],
            ),
            $data['hora_inicio_servicio'],
            $data['hora_fin_servicio'],
            $itinerario,
            $objetivo,
            $consideraciones,
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
