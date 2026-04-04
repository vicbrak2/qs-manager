<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Interfaces\Cli;

final class GenerateMonthlySummary
{
    public function __invoke(): void
    {
        if (defined('STDOUT')) {
            fwrite(STDOUT, 'El comando mensual de Finance se implementara en una iteracion posterior.' . PHP_EOL);
        }
    }
}
