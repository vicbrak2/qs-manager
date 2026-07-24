<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

interface BitacoraRepository
{
    /**
     * @return list<Bitacora>
     */
    public function findAll(): array;

    public function findById(int $id): ?Bitacora;

    /**
     * Inserta cuando id() es null, actualiza cuando no. Las notas no se
     * tocan en el update -- se agregan solo via addNote().
     */
    public function save(Bitacora $bitacora): Bitacora;

    public function addNote(int $bitacoraId, TravelNote $note): ?Bitacora;
}
