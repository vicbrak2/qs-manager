<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\QueryHandler;

use QS\Modules\Bitacora\Application\DTO\BitacoraDTO;
use QS\Modules\Bitacora\Application\Query\GetBitacoraById;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;

final class GetBitacoraByIdHandler
{
    public function __construct(private readonly BitacoraRepository $bitacoraRepository)
    {
    }

    public function handle(GetBitacoraById $query): ?BitacoraDTO
    {
        $bitacora = $this->bitacoraRepository->findById($query->id);

        return $bitacora !== null ? new BitacoraDTO($bitacora) : null;
    }
}
