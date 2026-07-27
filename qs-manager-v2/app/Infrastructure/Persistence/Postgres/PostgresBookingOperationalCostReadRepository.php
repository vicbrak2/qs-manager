<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Persistence\Postgres;

use PDO;

final class PostgresBookingOperationalCostReadRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @param list<array<string, mixed>> $bookings
     * @return array<int, array<string, mixed>>
     */
    public function forBookings(array $bookings): array
    {
        $result = [];
        foreach ($bookings as $booking) {
            $id = (int) ($booking['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $bitacora = $this->findBitacora($booking);
            $financeCost = $this->financeDirectCost($booking);
            $professionalCost = $financeCost > 0 ? $financeCost : (int) ($bitacora['costo_staff_clp'] ?? 0);
            $professionals = $this->professionalsForBitacora($bitacora);
            $professionalPayments = $this->splitProfessionalCost($professionals, $professionalCost);
            $transfer = (int) round((float) ($booking['transfer_value'] ?? 0));
            $received = (int) round((float) ($booking['deposit_amount'] ?? 0));
            $totalService = (int) round((float) ($booking['total_service'] ?? 0));
            $cash = max(0, $received - $professionalCost - $transfer);
            $finalCash = max(0, $totalService - $professionalCost - $transfer);

            $result[$id] = [
                'received_amount' => $received,
                'total_service_amount' => $totalService,
                'professional_total' => $professionalCost,
                'professional_payments' => $professionalPayments,
                'transfer_amount' => $transfer,
                'cash_amount' => $cash,
                'final_cash_amount' => $finalCash,
                'pending_amount' => (int) round((float) ($booking['balance_due'] ?? 0)),
                'source' => $financeCost > 0 ? 'finanzas' : ($bitacora ? 'bitacora' : 'reserva'),
                'bitacora_id' => $bitacora ? (int) $bitacora['id'] : null,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $booking
     * @return array<string, mixed>|null
     */
    private function findBitacora(array $booking): ?array
    {
        $date = $this->dateOnly((string) ($booking['scheduled_for'] ?? '')) ?? '';
        $statement = $this->connection->prepare("
            SELECT id, mua_id, estilista_id, professional_ids, costo_staff_clp
            FROM qs_bitacoras
            WHERE booking_id = :booking_id
               OR (booking_external_id IS NOT NULL AND booking_external_id IN (:sheet_external_id, :calendar_event_id))
               OR (
                   :service_date <> ''
                   AND fecha_servicio = :service_date
                   AND lower(trim(clienta_nombre)) = lower(trim(:customer_name))
               )
            ORDER BY
                CASE
                    WHEN booking_id = :booking_id THEN 1
                    WHEN booking_external_id IS NOT NULL AND booking_external_id IN (:sheet_external_id, :calendar_event_id) THEN 2
                    ELSE 3
                END,
                updated_at DESC,
                id DESC
            LIMIT 1
        ");
        $statement->execute([
            'booking_id' => (int) ($booking['id'] ?? 0),
            'sheet_external_id' => (string) ($booking['sheet_external_id'] ?? ''),
            'calendar_event_id' => (string) ($booking['calendar_event_id'] ?? ''),
            'service_date' => $date,
            'customer_name' => (string) ($booking['customer_name'] ?? ''),
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function financeDirectCost(array $booking): int
    {
        $ids = array_values(array_filter(array_unique(array_map(
            static fn (mixed $value): string => trim((string) $value),
            [
                $booking['sheet_external_id'] ?? null,
                $booking['calendar_event_id'] ?? null,
                $booking['id'] ?? null,
            ]
        ))));

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->connection->prepare("
            SELECT COALESCE(SUM(amount), 0)::bigint
            FROM qs_finance_entries
            WHERE entry_type = 'direct_cost'
              AND regexp_replace(external_id, '-cost$', '') IN ($placeholders)
        ");
        $statement->execute($ids);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed>|null $bitacora
     * @return list<array{id: int, name: string}>
     */
    private function professionalsForBitacora(?array $bitacora): array
    {
        if (!$bitacora) {
            return [];
        }

        $ids = [];
        foreach (['mua_id', 'estilista_id'] as $field) {
            if (!empty($bitacora[$field])) {
                $ids[] = (int) $bitacora[$field];
            }
        }

        $decoded = json_decode((string) ($bitacora['professional_ids'] ?? '[]'), true);
        if (is_array($decoded)) {
            foreach ($decoded as $value) {
                if (is_numeric($value)) {
                    $ids[] = (int) $value;
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->connection->prepare("
            SELECT id, display_name
            FROM qs_staff
            WHERE id IN ($placeholders)
            ORDER BY array_position(ARRAY[$placeholders]::bigint[], id)
        ");
        $statement->execute([...$ids, ...$ids]);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['display_name'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @param list<array{id: int, name: string}> $professionals
     * @return list<array{id: int|null, name: string, amount: int}>
     */
    private function splitProfessionalCost(array $professionals, int $amount): array
    {
        if ($amount <= 0) {
            return array_map(
                static fn (array $professional): array => [...$professional, 'amount' => 0],
                $professionals
            );
        }

        if ($professionals === []) {
            return [[
                'id' => null,
                'name' => 'Profesionales por definir',
                'amount' => $amount,
            ]];
        }

        $base = intdiv($amount, count($professionals));
        $remainder = $amount - ($base * count($professionals));

        return array_map(
            static function (array $professional, int $index) use ($base, $remainder): array {
                return [
                    ...$professional,
                    'amount' => $base + ($index === 0 ? $remainder : 0),
                ];
            },
            $professionals,
            array_keys($professionals)
        );
    }

    private function dateOnly(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('America/Santiago'))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
