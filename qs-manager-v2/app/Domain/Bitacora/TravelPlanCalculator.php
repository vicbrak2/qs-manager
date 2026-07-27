<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

use DateTimeImmutable;

/**
 * Regla operativa del estudio para planificar traslados:
 * - el equipo debe llegar ARRIVAL_BUFFER_MINUTES antes del inicio, y
 * - a la suma de tramos se agrega SLACK_MINUTES de holgura por trafico,
 *   "diluida" en el viaje (no se muestra por tramo, solo adelanta la salida).
 */
final class TravelPlanCalculator
{
    public const ARRIVAL_BUFFER_MINUTES = 15;
    public const SLACK_MINUTES = 15;

    public function llegadaObjetivo(?string $horaInicioServicio): ?string
    {
        $inicio = $this->parse($horaInicioServicio);
        if ($inicio === null) {
            return null;
        }

        return $inicio->modify(sprintf('-%d minutes', self::ARRIVAL_BUFFER_MINUTES))->format('H:i');
    }

    public function salidaSugerida(?string $horaInicioServicio, TravelItinerary $itinerario): ?string
    {
        $inicio = $this->parse($horaInicioServicio);
        if ($inicio === null || $itinerario->isEmpty()) {
            return null;
        }

        $minutosAntes = self::ARRIVAL_BUFFER_MINUTES + $itinerario->totalMinutes() + self::SLACK_MINUTES;

        return $inicio->modify(sprintf('-%d minutes', $minutosAntes))->format('H:i');
    }

    /**
     * A que hora pasa el vehiculo por cada punto de recogida. Se calcula
     * hacia adelante desde la salida, pero repartiendo la holgura de forma
     * proporcional a lo recorrido: asi la profesional no espera 15 minutos
     * de mas en el primer punto, y la llegada al servicio sigue cayendo
     * exactamente en la hora objetivo.
     *
     * @return list<array{recoge: string, comuna: string, hora: string}>
     */
    public function pickupSchedule(?string $horaInicioServicio, TravelItinerary $itinerario): array
    {
        $salida = $this->salidaSugerida($horaInicioServicio, $itinerario);
        if ($salida === null) {
            return [];
        }

        $total = $itinerario->totalMinutes();
        if ($total === 0) {
            return [];
        }

        $factor = ($total + self::SLACK_MINUTES) / $total;
        $salidaAt = DateTimeImmutable::createFromFormat('!H:i', $salida);
        if ($salidaAt === false) {
            return [];
        }

        $schedule = [];
        $elapsed = 0;
        foreach ($itinerario->legs() as $leg) {
            $elapsed += $leg->minutos();
            if (!$leg->isPickup()) {
                continue;
            }

            $schedule[] = [
                'recoge' => (string) $leg->recoge(),
                'comuna' => $leg->destino(),
                'hora' => $salidaAt->modify(sprintf('+%d minutes', (int) round($elapsed * $factor)))->format('H:i'),
            ];
        }

        return $schedule;
    }

    private function parse(?string $hora): ?DateTimeImmutable
    {
        if ($hora === null || preg_match('/^\d{1,2}:\d{2}/', trim($hora)) !== 1) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!H:i', substr(trim($hora), 0, 5));

        return $parsed === false ? null : $parsed;
    }
}
