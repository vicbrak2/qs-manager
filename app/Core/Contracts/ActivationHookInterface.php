<?php

declare(strict_types=1);

namespace QS\Core\Contracts;

interface ActivationHookInterface
{
    public function run(): void;
}
