<?php

declare(strict_types=1);

namespace QSManager\Domain\ServicesCatalog;

interface ServiceRepository
{
    public function save(Service $service): Service;

    /**
     * @return list<Service>
     */
    public function findAll(): array;

    public function findById(int $id): ?Service;

    public function exists(int $id): bool;

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): ?Service;

    public function delete(int $id): bool;
}
