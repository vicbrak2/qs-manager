<?php

declare(strict_types=1);

namespace QS\Core\Bootstrap;

use QS\Core\Contracts\HookableInterface;

final class HookLoader
{
    /**
     * @param array<int, HookableInterface> $hookables
     */
    public function register(array $hookables): void
    {
        foreach ($hookables as $hookable) {
            $hookable->register();
        }
    }
}
