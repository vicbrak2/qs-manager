<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Bitacora;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Bitacora\TravelItinerary;
use QSManager\Domain\Bitacora\TravelPlanCalculator;

final class TravelPlanCalculatorTest extends TestCase
{
    private TravelPlanCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TravelPlanCalculator();
    }

    public function testLlegadaObjetivoEsQuinceMinutosAntesDelInicio(): void
    {
        // Ejemplo real de la operacion: servicio 16:00 -> llegada 15:45.
        self::assertSame('15:45', $this->calculator->llegadaObjetivo('16:00'));
        self::assertSame('08:45', $this->calculator->llegadaObjetivo('9:00'));
        self::assertNull($this->calculator->llegadaObjetivo(null));
        self::assertNull($this->calculator->llegadaObjetivo('no-es-hora'));
    }

    public function testSalidaSugeridaSumaTramosMasHolguraDiluida(): void
    {
        // Servicio 16:00, tramo de 10 min: llegada objetivo 15:45, mas
        // 10 de viaje y 15 de holgura -> salida 15:20 (como la bitacora
        // de ejemplo de la operacion).
        $itinerario = TravelItinerary::fromArray([
            ['nombre' => 'Metro Macul -> Macul', 'minutos' => 10],
        ]);

        self::assertSame('15:20', $this->calculator->salidaSugerida('16:00', $itinerario));
    }

    public function testSalidaSugeridaConVariosTramos(): void
    {
        $itinerario = TravelItinerary::fromArray([
            ['nombre' => 'Estudio -> Metro Macul', 'minutos' => 20],
            ['nombre' => 'Metro Macul -> domicilio', 'minutos' => 25],
        ]);

        // 16:00 - 15 (llegada) - 45 (tramos) - 15 (holgura) = 14:45.
        self::assertSame('14:45', $this->calculator->salidaSugerida('16:00', $itinerario));
        self::assertSame(45, $itinerario->totalMinutes());
    }

    public function testHorasDeRecogidaDiluyenLaHolguraEnElTrayecto(): void
    {
        // Salida 06:50 desde el punto habitual, recogida de Paz al terminar
        // el primer tramo y llegada 07:45 al servicio de las 08:00.
        $itinerario = TravelItinerary::fromArray([
            ['nombre' => 'Metro Macul → Providencia', 'minutos' => 15, 'recoge' => 'Paz', 'comuna' => 'Providencia'],
            ['nombre' => 'Providencia → La Florida', 'minutos' => 25],
        ]);

        self::assertSame('06:50', $this->calculator->salidaSugerida('08:00', $itinerario));

        $recogidas = $this->calculator->pickupSchedule('08:00', $itinerario);
        self::assertCount(1, $recogidas);
        // 15 min de tramo escalados por (40+15)/40 = 20.6 -> 21 min desde 06:50.
        self::assertSame('07:11', $recogidas[0]['hora']);
        self::assertSame('Paz (Providencia)', $recogidas[0]['label']);
        // La comuna se publica, la direccion exacta nunca entra al value object.
        self::assertSame('Providencia', $recogidas[0]['comuna']);
    }

    public function testTramosSinRecogidaNoAparecenEnElItinerarioDePersonas(): void
    {
        $itinerario = TravelItinerary::fromArray([
            ['nombre' => 'Metro Macul → La Florida', 'minutos' => 40],
        ]);

        self::assertSame([], $this->calculator->pickupSchedule('08:00', $itinerario));
    }

    public function testSalidaSugeridaRequiereHoraDeInicioYTramos(): void
    {
        self::assertNull($this->calculator->salidaSugerida(null, TravelItinerary::fromArray([
            ['nombre' => 'x', 'minutos' => 10],
        ])));
        self::assertNull($this->calculator->salidaSugerida('16:00', new TravelItinerary()));
    }
}
