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
     