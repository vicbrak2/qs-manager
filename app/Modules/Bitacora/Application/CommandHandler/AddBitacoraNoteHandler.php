<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\CommandHandler;

use DateTimeImmutable;
use QS\Modules\Bitacora\Application\Command\AddBitacoraNote;
use QS\Modules\Bitacora\Application\DTO\BitacoraDTO;
use QS\Modules\Bitacora\Domain\Entity\TravelNote;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;
use QS\Shared\Bus\CommandHandlerInterface;
use QS\Shared\Bus\CommandInterface;

final class AddBitacoraNoteHandler implements CommandHandlerInterface
{
    public function __construct(private readonly BitacoraRepository $bitacoraRepository)
    {
    }

    public function handle(CommandInterface $command): ?BitacoraDTO
    {
        assert($command instanceof AddBitacoraNote);

        $updated = $this->bitacoraRepository->addNote(
            $command->bitacoraId,
            new TravelNote(trim($command->message), $command->authorUserId, new DateTimeImmutable('now'))
        );

        return $updated !== null ? new BitacoraDTO($updated) : null;
    }
}
