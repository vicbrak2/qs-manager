<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Application\QueryHandler;

use QS\Modules\Bitacora\Application\DTO\BitacoraDTO;
use QS\Modules\Bitacora\Application\Query\GetBitacoras;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;

final class GetBitacorasHandler
{
    public function __construct(private readonly BitacoraRepository $bitacoraRepository)
    {
    }

    /**
     * @return array<int, BitacoraDTO>
     */
    public function handle(GetBitacoras $query): array
    {
        return array_map(
            static fn ($bitacora): BitacoraDTO => new BitacoraDTO($bitacora),
            $this->bitacoraRepository->findAll()
        );
    }
}
