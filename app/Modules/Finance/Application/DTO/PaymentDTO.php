<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\DTO;

use QS\Modules\Finance\Domain\Entity\Payment;

final class PaymentDTO
{
    public function __construct(private readonly Payment $payment)
    {
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->payment->id(),
            'reservation_id' => $this->payment->reservationId(),
            'concept' => $this->payment->concept(),
            'amount_clp' => $this->payment->amount()->amount(),
            'method' => $this->payment->method()->value,
            'status' => $this->payment->status(),
            'paid_at' => $this->payment->paidAt()->format(DATE_ATOM),
            'closing_month' => $this->payment->closingMonth(),
        ];
    }
}
