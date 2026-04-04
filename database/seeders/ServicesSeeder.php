<?php

declare(strict_types=1);

namespace QS\Database\Seeders;

final class ServicesSeeder
{
    /**
     * @return array<int, array<string, int|string|null>>
     */
    public function defaults(): array
    {
        return [
            [
                'name' => 'Maquillaje Social',
                'duration_min' => 60,
                'buffer_min' => 15,
                'precio_base_clp' => 70000,
            ],
            [
                'name' => 'Combo Social M+P',
                'duration_min' => 90,
                'buffer_min' => 15,
                'precio_base_clp' => 90000,
            ],
        ];
    }
}
