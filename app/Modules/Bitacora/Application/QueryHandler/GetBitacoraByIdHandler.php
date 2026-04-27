<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\QueryHandler;

use QS\Modules\Bitacora\Application\DTO\BitacoraDTO;
use QS\Modules\Bitacora\Application\Query\GetBitacoraById;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;
use QS\Shared\Bus\QueryHandlerInterface;

final class GetBitacoraByIdHandler implements QueryHandlerInterface
{
    public function __construct(private readonly BitacoraRepository $bitacoraRepository)
    {
    }

    public function handle(object $query): ?BitacoraDTO
    {
        assert($query instanceof GetBitacoraById);

        $bitacora = $this->bitacoraRepository->findById($query->id);

        return $bitacora !== null ? new BitacoraDTO($bitacora) : null;
    }
}
