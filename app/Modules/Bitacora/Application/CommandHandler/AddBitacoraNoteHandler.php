<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\CommandHandler;

use DateTimeImmutable;
use QS\Modules\Bitacora\Application\Command\AddBitacoraNote;
use QS\Modules\Bitacora\Application\DTO\BitacoraDTO;
use QS\Modules\Bitacora\Domain\Entity\TravelNote;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;

final class AddBitacoraNoteHandler
{
    public function __construct(private readonly BitacoraRepository $bitacoraRepository)
    {
    }

    public function handle(AddBitacoraNote $command): ?BitacoraDTO
    {
        $updated = $this->bitacoraRepository->addNote(
            $command->bitacoraId,
            new TravelNote(trim($command->message), $command->authorUserId, new DateTimeImmutable('now'))
        );

        return $updated !== null ? new BitacoraDTO($updated) : null;
    }
}
