<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Gas;

use DateTimeImmutable;
use DateTimeZone;
use QSManager\Domain\Booking\Booking;

final class GasBookingPayloadMapper
{
    public function toPayload(Booking $booking): array
    {
        $data = $booking->toArray();
        $scheduledFor = $data['scheduled_for'] !== null
            ? (new DateTimeImmutable($data['scheduled_for']))->setTimezone(new DateTimeZone('America/Santiago'))
            : null;
        $externalId = $data['sheet_external_id'] ?: $data['id'];

        return [
            'source' => 'qs-manager-v2',
            'id' => $externalId,
            'service_id' => $data['service_id'],
            'service_name' => $data['service_name'],
            'staff_id' => $data['staff_id'],
            'staff_name' => $data['staff_name'],
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'fecha' => $scheduledFor?->format('Y-m-d'),
            'hora' => $scheduledFor?->format('H:i'),
            'status' => $data['status'],
            'tipo' => 'Servicio',
            'encargada' => $data['staff_name'],
            'servicio' => $data['service_name'],
            'tipo_servicio' => null,
            'clienta' => $data['customer_name'],
            'telefono' => $data['customer_phone'],
            'direccion' => $data['address'],
            'comuna' => $data['comuna'],
            'traslado' => $data['transfer_value'],
            'abono' => $data['deposit_amount'],
            'valor_servicio' => $data['service_value'],
            'total_servicio' => $data['total_service'],
            'saldo' => $data['balance_due'],
            'estado_pago' => $data['payment_status'],
            'estado_servicio' => $data['service_status'],
            'id_contrato' => $data['contract_id'],
            'hito' => $data['milestone'],
            'grupo_caja' => $data['cash_group'],
            'referencia_agenda' => $data['agenda_reference'],
            'id_calendar' => $data['calendar_event_id'],
        ];
    }
}
