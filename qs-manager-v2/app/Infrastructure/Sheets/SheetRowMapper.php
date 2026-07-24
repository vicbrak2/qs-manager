<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Sheets;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Helpers puros de parseo/normalizacion de filas de planilla -- extraidos de
 * PostgresSheetReplicaImporter (Fase 4 del plan de migracion). Sin PDO, sin
 * efectos de lado, mismo comportamiento exacto que el codigo original.
 */
final class SheetRowMapper
{
    /**
     * @param list<list<mixed>> $rows
     * @param list<string> $required
     * @return array{0: int, 1: list<string>}
     */
    public function findHeader(array $rows, array $required): array
    {
        foreach ($rows as $index => $row) {
            $headers = array_map(fn (string $value): string => $this->normalizeKey($value), $row);
            $found = array_filter($required, static fn (string $field): bool => in_array($field, $headers, true));
            if (count($found) === count($required)) {
                return [$index, $headers];
            }
        }

        throw new \RuntimeException('Could not find expected header row: ' . implode(', ', $required));
    }

    /**
     * @param list<string> $headers
     * @param list<mixed> $values
     * @return array<string, mixed>
     */
    public function combine(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }
            $row[$header] = $values[$index] ?? '';
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function string(array $row, string $key): ?string
    {
        $value = trim((string) ($row[$this->normalizeKey($key)] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function bool(array $row, string $key): ?bool
    {
        $value = strtolower((string) ($row[$this->normalizeKey($key)] ?? ''));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['1', 'true', 'si', 'sí', 'yes', 'activo', 'x'], true);
    }

    public function dbBool(?bool $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return $value ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function money(array $row, string $key): ?float
    {
        $value = $this->string($row, $key);
        if ($value === null) {
            return null;
        }

        $clean = str_replace(['$', ' ', "\u{00A0}", '.'], '', $value);
        $clean = str_replace(',', '.', $clean);

        $result = is_numeric($clean) ? (float) $clean : null;

        if ($result !== null && $result > 0 && $result < 1000) {
            $result *= 1000;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function catalogMoney(array $row, string $key): ?float
    {
        return $this->money($row, $key);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function positiveInt(array $row, string $key): ?int
    {
        $value = $this->string($row, $key);
        if ($value === null || !ctype_digit($value)) {
            return null;
        }

        $number = (int) $value;

        return $number > 0 ? $number : null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function percent(array $row, string $key): ?float
    {
        $value = $this->string($row, $key);
        if ($value === null) {
            return null;
        }

        $clean = str_replace(['%', ' '], '', $value);
        $clean = str_replace(',', '.', $clean);

        if (!is_numeric($clean)) {
            return null;
        }

        $number = (float) $clean;

        return $number > 1 ? $number / 100 : $number;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function date(array $row, string $key): ?string
    {
        $value = $this->string($row, $key);
        if ($value === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function time(array $row, string $key): ?string
    {
        $value = $this->string($row, $key);
        if ($value === null) {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'G:i'] as $format) {
            $time = DateTimeImmutable::createFromFormat($format, $value);
            if ($time !== false) {
                return $time->format('H:i:s');
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function scheduledFor(array $row): ?string
    {
        $date = $this->date($row, 'fecha');
        if ($date === null) {
            return null;
        }

        $time = $this->time($row, 'hora') ?? '00:00:00';

        return $this->localTimestamp($date, $time);
    }

    /**
     * Chile alterna entre -04 (invierno) y -03 (verano); un offset fijo
     * desplaza una hora las reservas de la temporada contraria.
     */
    public function localTimestamp(string $date, string $time): string
    {
        $local = new DateTimeImmutable($date . ' ' . $time, new DateTimeZone('America/Santiago'));

        return $local->format('Y-m-d H:i:sP');
    }

    public function bookingStatus(?string $sheetStatus): string
    {
        $status = strtolower($sheetStatus ?? '');

        return match (true) {
            str_contains($status, 'cancel') => 'cancelled',
            str_contains($status, 'complet'), str_contains($status, 'realiz'), str_contains($status, 'termin'), str_contains($status, 'ejecut') => 'completed',
            str_contains($status, 'agend'), str_contains($status, 'confirm') => 'confirmed',
            default => 'draft',
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    public function isCashTrackingFooterRow(array $row): bool
    {
        return $this->normalizeKey((string) ($row[0] ?? '')) === 'ganancia por servicios cerrados';
    }

    /**
     * @param array<string, mixed> $row
     */
    public function shouldProjectBitacoraBooking(array $row): bool
    {
        $agendaReference = $this->string($row, 'referencia agenda');

        if ($agendaReference === null) {
            return true;
        }

        // A row range represents an accounting aggregate, not an additional booking.
        return preg_match('/![0-9]+\s*-\s*[0-9]+$/', $agendaReference) !== 1;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{service_value: ?float, total_service: ?float, balance_due: ?float, payment_status: ?string}
     */
    public function agendaFinancials(array $row): array
    {
        $serviceValue = $this->money($row, 'valor servicio');
        $transferValue = $this->money($row, 'traslado');
        $depositAmount = $this->money($row, 'abono');
        $totalService = $this->money($row, 'total servicio');

        if ($totalService === null && ($serviceValue !== null || $transferValue !== null)) {
            $totalService = ($serviceValue ?? 0.0) + ($transferValue ?? 0.0);
        }

        $balanceDue = $this->money($row, 'total por pagar');
        if ($balanceDue === null && $totalService !== null) {
            $balanceDue = max(0.0, $totalService - ($depositAmount ?? 0.0));
        }

        $paymentStatus = $this->string($row, 'estado pago');
        $isWorkshop = $this->normalizeKey($this->string($row, 'dia') ?? '') === 'taller';
        if (($paymentStatus === null || $isWorkshop) && $totalService !== null) {
            $paymentStatus = ($depositAmount ?? 0.0) >= $totalService
                ? 'Pagado'
                : (($depositAmount ?? 0.0) > 0.0 ? 'Parcial' : 'Pendiente');
        }

        return [
            'service_value' => $serviceValue,
            'total_service' => $totalService,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<mixed> $rawValues
     */
    public function workshopNotes(array $row, array $rawValues): ?string
    {
        $notes = [];
        $rawPayment = $this->string($row, 'pago');
        $rawPaymentDate = $this->string($row, 'fecha pago');

        if ($rawPayment !== null && $this->money($row, 'pago') === null) {
            $notes[] = 'Pago: ' . $rawPayment;
        }

        if ($rawPaymentDate !== null && $this->date($row, 'fecha pago') === null) {
            $notes[] = 'Fecha/Pago: ' . $rawPaymentDate;
        }

        foreach (array_slice($rawValues, 5) as $index => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $notes[] = 'Extra ' . ($index + 6) . ': ' . $value;
            }
        }

        return $notes === [] ? null : implode(' | ', $notes);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<mixed> $rawValues
     */
    public function workshopPaymentAmount(array $row, array $rawValues): ?float
    {
        $direct = $this->money($row, 'pago');
        if ($direct !== null) {
            return $direct;
        }

        $candidates = [$this->string($row, 'fecha pago')];
        foreach (array_slice($rawValues, 5) as $value) {
            $candidates[] = trim((string) $value);
        }

        foreach ($candidates as $candidate) {
            $amount = $this->looseMoneyFromText($candidate);
            if ($amount !== null) {
                return $amount;
            }
        }

        return null;
    }

    public function looseMoneyFromText(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mil\b/u', $normalized, $matches) === 1) {
            return (float) str_replace(',', '.', $matches[1]) * 1000;
        }

        if (preg_match('/pagad[oa]?\s+(\d{1,3})(?![\d.,])/u', $normalized, $matches) === 1) {
            return (float) $matches[1] * 1000;
        }

        return null;
    }

    public function normalizeKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }
}
