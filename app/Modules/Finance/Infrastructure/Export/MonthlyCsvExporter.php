<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Infrastructure\Export;

final class MonthlyCsvExporter
{
    /**
     * @param array<int, array<string, scalar|null>> $rows
     */
    public function export(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $headers = array_keys($rows[0]);
        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                static fn ($value): string => (string) $value,
                $row
            ));
        }

        return implode(PHP_EOL, $lines);
    }
}
