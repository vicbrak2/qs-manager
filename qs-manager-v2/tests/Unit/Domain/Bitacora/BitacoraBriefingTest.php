<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Bitacora;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Bitacora\BitacoraBriefing;

final class BitacoraBriefingTest extends TestCase
{
    private BitacoraBriefing $briefing;

    protected function setUp(): void
    {
        $this->briefing = new BitacoraBriefing();
    }

    public function testObjetivoDependeDelTipoDeServicio(): void
    {
        self::assertStringContainsString('prueba con calma', $this->briefing->objetivo('Prueba Novia (Maquillaje + Peinado)'));
        self::assertStringContainsString('taller', $this->briefing->objetivo('Taller Automaquillaje grupal'));
        self::assertStringContainsString('bar', $this->briefing->objetivo('Glitter Bar - Paquete 3'));
        self::assertStringContainsString('anticipacion', $this->briefing->objetivo('Novia Civil Maquillaje Peinado'));
    }

    public function testConsideracionesReaccionanAlContexto(): void
    {
        $temprano = $this->briefing->consideraciones('06:50', 40, 'Av. Siempre Viva 123');
        self::assertStringContainsString('Salida temprana', $temprano);

        $rutaLarga = $this->briefing->consideraciones('09:00', 50, 'Av. Siempre Viva 123');
        self::assertStringContainsString('Trayecto largo', $rutaLarga);

        $edificio = $this->briefing->consideraciones('09:00', 20, 'Gerónimo de Alderete 208, depto 2004');
        self::assertStringContainsString('acceso al edificio', $edificio);

        $conRecogidas = $this->briefing->consideraciones('09:00', 20, 'Calle 1', [
            ['recoge' => 'Paz', 'comuna' => 'Providencia', 'label' => 'Paz (Providencia)', 'hora' => '08:10'],
        ]);
        self::assertStringContainsString('lista en su punto', $conRecogidas);
    }

    public function testSinContextoRelevanteCaeEnLaConsideracionBase(): void
    {
        $texto = $this->briefing->consideraciones('09:00', 20, 'Calle 1 casa 5');

        self::assertSame('Confirmar acceso al domicilio y espacio de trabajo.', $texto);
    }

    public function testNuncaMencionaAppsDeNavegacion(): void
    {
        // Regla del estudio: el equipo no debe leer "Maps"/"Waze" en la bitacora.
        $textos = [
            $this->briefing->objetivo('Novia Fiesta'),
            $this->briefing->objetivo('Taller'),
            $this->briefing->consideraciones('06:00', 90, 'Torre 5, depto 101', [
                ['recoge' => 'Paz', 'comuna' => 'Macul', 'label' => 'Paz (Macul)', 'hora' => '06:30'],
            ]),
        ];

        foreach ($textos as $texto) {
            foreach (['maps', 'waze', 'google', 'gps', 'navegador'] as $prohibido) {
                self::assertStringNotContainsStringIgnoringCase($prohibido, $texto);
            }
        }
    }
}
