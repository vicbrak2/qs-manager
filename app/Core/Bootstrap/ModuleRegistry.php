<?php

declare(strict_types=1);

namespace QS\Core\Bootstrap;

final class ModuleRegistry
{
    /**
     * @param array<string, array<string, mixed>> $modules
     */
    public function __construct(private array $modules)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function enabled(): array
    {
        return array_filter(
            $this->modules,
            static fn (array $module): bool => (bool) ($module['enabled'] ?? false)
        );
    }
}
