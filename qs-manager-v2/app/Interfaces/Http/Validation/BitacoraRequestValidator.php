<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http\Validation;

use QSManager\Domain\Booking\BookingRepository;
use QSManager\Domain\Team\StaffRepository;

final class BitacoraRequestValidator
{
    public function __construct(
        private readonly StaffRepository $staff,
        private readonly BookingRepository $bookings,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function validate(array $body): array
    {
        $errors = [];

        $fechaServicio = $this->stringField($body, 'fecha_servicio', true, $errors);
        $this->maxLength($fechaServicio, 'fecha_servicio', 40, $errors);

        $tipoServicio = $this->stringField($body, 'tipo_servicio', true, $errors);
        $this->maxLength($tipoServicio, 'tipo_servicio', 120, $errors);

        $muaId = $this->optionalPositiveInt($body, 'mua_id', 'Mua id', $errors);
        if ($muaId !== null && !$this->staff->exists($muaId)) {
            $errors['mua_id'][] = 'Selected staff member does not exist.';
        }

        $estilistaId = $this->optionalPositiveInt($body, 'estilista_id', 'Estilista id', $errors);
        if ($estilistaId !== null && !$this->staff->exists($estilistaId)) {
            $errors['estilista_id'][] = 'Selected staff member does not exist.';
        }

        $bookingId = $this->optionalPositiveInt($body, 'booking_id', 'Booking id', $errors);
        if ($bookingId !== null && $this->bookings->findById($bookingId) === null) {
            $errors['booking_id'][] = 'Selected booking does not exist.';
        }

        $clientaNombre = $this->stringField($body, 'clienta_nombre', true, $errors);
        $this->maxLength($clientaNombre, 'clienta_nombre', 160, $errors);

        $direccionServicio = $this->stringField($body, 'direccion_servicio', true, $errors);
        $this->maxLength($direccionServicio, 'direccion_servicio', 240, $errors);

        // Opcional: si no viene, SaveBitacora usa el punto de salida habitual.
        $puntoSalida = $this->stringField($body, 'punto_salida', false, $errors);
        $this->maxLength($puntoSalida, 'punto_salida', 240, $errors);

        $ordenRecogida = $this->stringField($body, 'orden_recogida', false, $errors);

        $tiempoTrasladoMin = $this->optionalNonNegativeInt($body, 'tiempo_traslado_min', 'Tiempo traslado min', $errors);

        $horaLlegada = $this->stringField($body, 'hora_llegada', false, $errors);
        $this->maxLength($horaLlegada, 'hora_llegada', 20, $errors);

        $horaInicioServicio = $this->timeField($body, 'hora_inicio_servicio', $errors);
        $horaFinServicio = $this->timeField($body, 'hora_fin_servicio', $errors);
        $tramos = $this->tramosField($body, $errors);

        $objetivo = $this->stringField($body, 'objetivo', false, $errors);
        $consideraciones = $this->stringField($body, 'consideraciones', false, $errors);

        $notasLogisticas = $this->stringField($body, 'notas_logisticas', false, $errors);

        $costoStaffClp = $this->optionalNonNegativeInt($body, 'costo_staff_clp', 'Costo staff clp', $errors);
        $precioClienteClp = $this->optionalNonNegativeInt($body, 'precio_cliente_clp', 'Precio cliente clp', $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'fecha_servicio' => $fechaServicio,
            'tipo_servicio' => $tipoServicio,
            'mua_id' => $muaId,
            'estilista_id' => $estilistaId,
            'booking_id' => $bookingId,
            'clienta_nombre' => $clientaNombre,
            'direccion_servicio' => $direccionServicio,
            'punto_salida' => $puntoSalida,
            'orden_recogida' => $ordenRecogida,
            'tiempo_traslado_min' => $tiempoTrasladoMin ?? 0,
            'hora_llegada' => $horaLlegada,
            'hora_inicio_servicio' => $horaInicioServicio,
            'hora_fin_servicio' => $horaFinServicio,
            'tramos' => $tramos,
            'objetivo' => $objetivo,
            'consideraciones' => $consideraciones,
            'notas_logisticas' => $notasLogisticas,
            'costo_staff_clp' => $costoStaffClp ?? 0,
            'precio_cliente_clp' => $precioClienteClp ?? 0,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, list<string>> $errors
     */
    private function timeField(array $body, string $field, array &$errors): ?string
    {
        $value = $this->stringField($body, $field, false, $errors);
        if ($value !== null && preg_match('/^\d{1,2}:\d{2}$/', $value) !== 1) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must use HH:MM format.';
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, list<string>> $errors
     * @return list<array{destino: string, minutos: int, recoge?: string}>
     */
    private function tramosField(array $body, array &$errors): array
    {
        if (!array_key_exists('tramos', $body) || $body['tramos'] === null) {
            return [];
        }

        if (!is_array($body['tramos'])) {
            $errors['tramos'][] = 'Tramos must be a list of {destino, minutos}.';
            return [];
        }

        $tramos = [];
        foreach (array_values($body['tramos']) as $index => $row) {
            $position = $index + 1;
            if (!is_array($row)) {
                $errors['tramos'][] = "Tramo $position must be an object with destino and minutos.";
                continue;
            }

            $destino = isset($row['destino']) && is_string($row['destino']) ? trim($row['destino']) : '';
            if ($destino === '' || mb_strlen($destino) > 160) {
                $errors['tramos'][] = "Tramo $position: destino is required (max 160 characters).";
                continue;
            }

            $minutos = $row['minutos'] ?? null;
            if (filter_var($minutos, FILTER_VALIDATE_INT) === false || (int) $minutos < 0) {
                $errors['tramos'][] = "Tramo $position: minutos must be a non-negative integer.";
                continue;
            }

            $tramo = ['destino' => $destino, 'minutos' => (int) $minutos];

            // Un tramo puede terminar en una recogida de profesional.
            if (isset($row['recoge']) && is_string($row['recoge']) && trim($row['recoge']) !== '') {
                $tramo['recoge'] = trim($row['recoge']);
            }

            $tramos[] = $tramo;
        }

        return $tramos;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, list<string>> $errors
     */
    private function stringField(array $body, string $field, bool $required, array &$errors): ?string
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            if ($required) {
                $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
            return null;
        }

        if (!is_string($body[$field])) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must be a string.';
            return null;
        }

        $value = trim($body[$field]);
        if ($required && $value === '') {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            return null;
        }

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, list<string>> $errors
     */
    private function optionalPositiveInt(array $body, string $field, string $label, array &$errors): ?int
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (filter_var($body[$field], FILTER_VALIDATE_INT) === false) {
            $errors[$field][] = $label . ' must be a positive integer.';
            return null;
        }

        $value = (int) $body[$field];
        if ($value <= 0) {
            $errors[$field][] = $label . ' must be a positive integer.';
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, list<string>> $errors
     */
    private function optionalNonNegativeInt(array $body, string $field, string $label, array &$errors): ?int
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (filter_var($body[$field], FILTER_VALIDATE_INT) === false) {
            $errors[$field][] = $label . ' must be a non-negative integer.';
            return null;
        }

        $value = (int) $body[$field];
        if ($value < 0) {
            $errors[$field][] = $label . ' must be a non-negative integer.';
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private function maxLength(?string $value, string $field, int $max, array &$errors): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' cannot exceed ' . $max . ' characters.';
        }
    }
}
