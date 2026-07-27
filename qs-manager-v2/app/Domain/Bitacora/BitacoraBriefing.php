<?php

declare(strict_types=1);

namespace QSManager\Domain\Bitacora;

/**
 * Redacta el objetivo y las consideraciones de la bitacora a partir del tipo
 * de servicio y del contexto del traslado (salida temprana, ruta larga,
 * edificio, recogidas en ruta), para que el usuario no tenga que escribirlos
 * cada vez.
 *
 * Regla del estudio: el texto que ve el equipo **nunca menciona Maps ni
 * apps de navegacion**; los tiempos de ruta se presentan como dato propio.
 */
final class BitacoraBriefing
{
    private const SALIDA_TEMPRANA = '07:00';
    private const RUTA_LARGA_MINUTOS = 45;

    public function objetivo(string $tipoServicio): string
    {
        $tipo = mb_strtolower($tipoServicio);

        if (str_contains($tipo, 'prueba')) {
            return 'Realizar la prueba con calma y dedicacion, dejando definido el look para el dia del evento.';
        }

        if (str_contains($tipo, 'taller')) {
            return 'Preparar el espacio y los materiales para que cada participante pueda seguir el taller sin apuro.';
        }

        if (str_contains($tipo, 'glitter') || str_contains($tipo, 'bar')) {
            return 'Montar el bar a tiempo y sostener el flujo de atencion durante todo el evento.';
        }

        if (str_contains($tipo, 'novia')) {
            return 'Llegar con anticipacion para preparar materiales y realizar el servicio con calma.';
        }

        return 'Llegar con anticipacion para preparar materiales y realizar el servicio con calma.';
    }

    /**
     * @param list<array{recoge: string, comuna: ?string, label: string, hora: string}> $recogidas
     */
    public function consideraciones(
        ?string $horaSalida,
        int $minutosRuta,
        string $direccionServicio,
        array $recogidas = [],
    ): string {
        $notas = [];

        if ($horaSalida !== null && $horaSalida < self::SALIDA_TEMPRANA) {
            $notas[] = 'Salida temprana: dejar bolsos y materiales listos la noche anterior.';
        }

        if ($minutosRuta >= self::RUTA_LARGA_MINUTOS) {
            $notas[] = 'Trayecto largo: respetar la hora de salida para no comprimir la preparacion.';
        }

        if ($this->esEdificio($direccionServicio)) {
            $notas[] = 'Confirmar acceso al edificio y estacionamiento.';
        }

        if ($recogidas !== []) {
            $notas[] = 'Cada profesional debe estar lista en su punto a la hora indicada.';
        }

        if ($notas === []) {
            $notas[] = 'Confirmar acceso al domicilio y espacio de trabajo.';
        }

        return implode(' ', $notas);
    }

    private function esEdificio(string $direccion): bool
    {
        $direccion = mb_strtolower($direccion);

        foreach (['depto', 'departamento', 'piso', 'edificio', 'torre', 'oficina'] as $marca) {
            if (str_contains($direccion, $marca)) {
                return true;
            }
        }

        return false;
    }
}
