<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Domain\Entity;

use QS\Modules\Booking\Domain\ValueObject\ReservationId;
use QS\Modules\Booking\Domain\ValueObject\ReservationStatus;
use QS\Modules\Booking\Domain\ValueObject\ReservationTimeRange;

final class Reservation
{
    public function __construct(
        private readonly ReservationId $id,
        private readonly ?int $orderId,
        private readonly ?int $customerId,
        private readonly ?int $agentId,
        private readonly ?int $serviceId,
        private readonly string $serviceName,
        private readonly ?string $agentName,
        private readonly string $clientName,
        private readonly ?string $clientEmail,
        private readonly ?string $clientPhone,
        private readonly ReservationStatus $status,
        private readonly ?int $priceClp,
        private readonly ReservationTimeRange $timeRange,
        private readonly ?string $paymentMethod,
        private readonly ?string $notes
    ) {
    }

    public function id(): ReservationId
    {
        return $this->id;
    }

    public function orderId(): ?int
    {
        return $this->orderId;
    }

    public function customerId(): ?int
    {
        return $this->customerId;
    }

    public function agentId(): ?int
    {
        return $this->agentId;
    }

    public function serviceId(): ?int
    {
        return $this->serviceId;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    public function agentName(): ?string
    {
        return $this->agentName;
    }

    public function clientName(): string
    {
        return $this->clientName;
    }

    public function clientEmail(): ?string
    {
        return $this->clientEmail;
    }

    public function clientPhone(): ?string
    {
        return $this->clientPhone;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function priceClp(): ?int
    {
        return $this->priceClp;
    }

    public function timeRange(): ReservationTimeRange
    {
        return $this->timeRange;
    }

    public function paymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }
}
