<?php

declare(strict_types=1);

namespace QS\Database\Seeders;

final class StaffSeeder
{
    /**
     * @return array<int, array<string, string>>
     */
    public function defaults(): array
    {
        return [
            ['nombre' => 'Camila', 'apellido' => 'Verdejo', 'especialidad' => 'mua'],
            ['nombre' => 'Moureen', 'apellido' => 'Marchant', 'especialidad' => 'mua'],
            ['nombre' => 'Paz', 'apellido' => 'Estilista', 'especialidad' => 'estilista'],
        ];
    }
}
