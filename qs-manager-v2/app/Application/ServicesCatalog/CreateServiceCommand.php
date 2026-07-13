<?php

declare(strict_types=1);

namespace QSManager\Application\ServicesCatalog;

final class CreateServiceCommand
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $category,
        public readonly ?int $durationMinutes,
    ) {
    }
}

