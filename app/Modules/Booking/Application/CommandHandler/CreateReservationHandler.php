<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\CommandHandler;

use DateTimeImmutable;
use QS\Core\Logging\Logger;
use QS\Modules\Booking\Application\Command\CreateReservation;
use QS\Modules\Booking\Domain\Entity\Reservation;
use QS\Modules\Booking\Domain\Entity\SheetEvent;
use QS\Modules\Booking\Domain\Exception\BookingConflictException;
use QS\Modules\Booking\Domain\Repository\ReservationRepository;
use QS\Modules\Booking\Domain\Repository\SheetEventRepository;
use QS\Modules\Booking\Domain\Service\SheetRowData;
use QS\Modules\Booking\Domain\Service\SheetsSyncGateway;
use QS\Modules\Booking\Domain\ValueObject\ReservationId;
use QS\Modules\Booking\Domain\ValueObject\ReservationStatus;
use QS\Modules\Booking\Domain\ValueObject\ReservationTimeRange;
use QS\Shared\Bus\CommandHandlerInterface;
use QS\Shared\Bus\CommandInterface;

final class CreateReservationHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly SheetEventRepository $sheetEventRepository,
        private readonly SheetsSyncGateway $sheetsSyncGateway,
        private readonly Logger $logger
    ) {
    }

    /**
     * Procesa la creación de una reserva:
     * 1. Verifica conflicto de horario vía webhook check-conflict.
     * 2. Si hay conflicto lanza BookingConflictException (→ HTTP 409).
     * 3. Inserta la fila completa en el Google Sheet vía webhook append-row.
     *    La planilla se encarga de crear el evento en Google Calendar automáticamente.
     * 4. Persiste localmente en qs_sheet_events con origen='form' (fuente de verdad DB).
     * 5. Guarda snapshot en qs_bookings (tabla legacy LatePoint-compatible).
     *
     * @return string Nombre de la pestaña del Sheet donde se insertó la fila.
     */
    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateReservation);

        $this->logger->info('CreateReservationHandler: Iniciando para ' . $command->clientName);

        // 1. Verificar conflicto de horario
        $conflictingEvent = $this->sheetsSyncGateway->checkConflict(
            $command->startTime,
            $command->endTime
        );

        if ($conflictingEvent !== null) {
            $this->logger->warning('CreateReservationHandler: Conflicto detectado → ' . $conflictingEvent);
            throw new BookingConflictException($conflictingEvent);
        }

        // 2. Insertar fila en el Google Sheet (dispara creación del evento GCal)
        $sheetRowData = new SheetRowData(
            encargada:     $command->encargada,
            clientName:    $command->clientName,
            clientEmail:   $command->clientEmail,
            clientPhone:   $command->clientPhone,
            serviceName:   $command->serviceName,
            startTime:     $command->startTime,
            endTime:       $command->endTime,
            direccion:     $command->direccion,
            comuna:        $command->comuna,
            traslado:      $command->traslado,
            valorServicio: $command->valorServicio,
            cantidad:      $command->cantidad,
            abono:         $command->abono,
            montoAbono:    $command->montoAbono,
            fechaAbono:    $command->fechaAbono,
        );

        $sheetName = $this->sheetsSyncGateway->appendRow($sheetRowData);

        $this->logger->info('CreateReservationHandler: Fila insertada en hoja → ' . $sheetName);

        // 3. Persistir en qs_sheet_events con origen=form
        //    row_index = 0 → pendiente de número real (se asigna en el próximo sync desde n8n)
        $valorNum      = (int) preg_replace('/\D/', '', $command->valorServicio);
        $montoAbonoNum = $command->abono ? (int) preg_replace('/\D/', '', $command->montoAbono) : 0;
        $totalServicio = $valorNum * $command->cantidad;
        $totalPorPagar = $totalServicio - $montoAbonoNum;

        $diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $dia        = $diasSemana[(int) $command->startTime->format('N')];

        $fechaAbono = $command->abono && $command->fechaAbono !== ''
            ? DateTimeImmutable::createFromFormat('Y-m-d', $command->fechaAbono) ?: null
            : null;

        $now = new DateTimeImmutable('now');

        $sheetEvent = new SheetEvent(
            id:                 null,
            sheetName:          $sheetName,
            rowIndex:           0,
            encargada:          $command->encargada,
            dia:                $dia,
            fechaServicio:      $command->startTime,
            horaInicio:         $command->startTime->format('H:i'),
            servicio:           $command->serviceName,
            cantidad:           $command->cantidad,
            clientaNombre:      $command->clientName,
            telefono:           $command->clientPhone !== '' ? $command->clientPhone : null,
            direccion:          $command->direccion !== '' ? $command->direccion : null,
            comuna:             $command->comuna !== '' ? $command->comuna : null,
            traslado:           $command->traslado,
            abonoClp:           $montoAbonoNum,
            fechaAbono:         $fechaAbono instanceof DateTimeImmutable ? $fechaAbono : null,
            valorServicioClp:   $valorNum,
            totalServicioClp:   $totalServicio,
            totalPorPagarClp:   $totalPorPagar,
            accion:             '',
            estadoEvento:       'Pendiente',
            idEventoGcal:       null,
            origen:             'form',
            syncedAt:           $now,
            createdAt:          $now,
            updatedAt:          $now,
        );

        $this->sheetEventRepository->upsert($sheetEvent);

        $this->logger->info('CreateReservationHandler: SheetEvent guardado en DB con origen=form.');

        // 4. Persistir snapshot legacy
        $reservation = new Reservation(
            new ReservationId(0),
            null,
            null,
            null,
            null,
            $command->serviceName,
            null,
            $command->clientName,
            $command->clientEmail,
            $command->clientPhone,
            ReservationStatus::Approved,
            null,
            new ReservationTimeRange(
                $command->startTime->format('Y-m-d'),
                $command->startTime->format('H:i:s'),
                $command->endTime->format('H:i:s')
            ),
            null,
            'Sheet: ' . $sheetName . ' | Encargada: ' . $command->encargada
        );

        $this->reservationRepository->save($reservation);

        $this->logger->info('CreateReservationHandler: Reserva guardada localmente.');

        return $sheetName;
    }
}
