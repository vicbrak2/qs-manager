<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Domain\Repository;

use QS\Modules\Bitacora\Domain\Entity\Bitacora;
use QS\Modules\Bitacora\Domain\Entity\TravelNote;

interface BitacoraRepository
{
    /**
     * @return array<int, Bitacora>
     */
    public function findAll(): array;

    public function findById(int $id): ?Bitacora;

    public function save(Bitacora $bitacora): Bitacora;

    public function addNote(int $bitacoraId, TravelNote $note): ?Bitacora;
}
