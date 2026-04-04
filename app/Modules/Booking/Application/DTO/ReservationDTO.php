<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Application\DTO;

use QS\Modules\Booking\Domain\Entity\Reservation;

final class ReservationDTO
{
    public function __construct(private readonly Reservation $reservation)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->reservation->id()->value(),
            'order_id' => $this->reservation->orderId(),
            'customer_id' => $this->reservation->customerId(),
            'agent_id' => $this->reservation->agentId(),
            'service_id' => $this->reservation->serviceId(),
            'service_name' => $this->reservation->serviceName(),
            'agent_name' => $this->reservation->agentName(),
            'client_name' => $this->reservation->clientName(),
            'client_email' => $this->reservation->clientEmail(),
            'client_phone' => $this->reservation->clientPhone(),
            'status' => $this->reservation->status()->value,
            'price_clp' => $this->reservation->priceClp(),
            'time_range' => $this->reservation->timeRange()->toArray(),
            'payment_method' => $this->reservation->paymentMethod(),
            'notes' => $this->reservation->notes(),
        ];
    }
}
