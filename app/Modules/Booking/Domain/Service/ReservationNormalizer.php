<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Service;

use QS\Modules\Booking\Domain\Entity\Reservation;
use QS\Modules\Booking\Domain\ValueObject\ReservationId;
use QS\Modules\Booking\Domain\ValueObject\ReservationStatus;
use QS\Modules\Booking\Domain\ValueObject\ReservationTimeRange;

final class ReservationNormalizer
{
    /**
     * @param array<string, mixed> $row
     */
    public function fromRow(array $row): Reservation
    {
        $clientName = trim(sprintf(
            '%s %s',
            (string) ($row['customer_first_name'] ?? $row['first_name'] ?? ''),
            (string) ($row['customer_last_name'] ?? $row['last_name'] ?? '')
        ));

        if ($clientName === '') {
            $clientName = 'Clienta sin nombre';
        }

        return new Reservation(
            new ReservationId((int) $row['id']),
            isset($row['order_id']) ? (int) $row['order_id'] : null,
            isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            isset($row['agent_id']) ? (int) $row['agent_id'] : null,
            isset($row['service_id']) ? (int) $row['service_id'] : null,
            (string) ($row['service_name'] ?? 'Servicio sin nombre'),
            isset($row['agent_name']) ? (string) $row['agent_name'] : null,
            $clientName,
            isset($row['email']) ? (string) $row['email'] : null,
            isset($row['phone']) ? (string) $row['phone'] : null,
            ReservationStatus::fromNullable(isset($row['status']) ? (string) $row['status'] : null),
            isset($row['price']) ? (int) $row['price'] : null,
            new ReservationTimeRange(
                (string) ($row['start_date'] ?? gmdate('Y-m-d')),
                (string) ($row['start_time'] ?? '00:00:00'),
                (string) ($row['end_time'] ?? '00:00:00')
            ),
            isset($row['payment_method']) ? (string) $row['payment_method'] : null,
            isset($row['notes']) ? (string) $row['notes'] : null
        );
    }
}
