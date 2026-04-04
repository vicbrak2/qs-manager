<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Domain\Repository;

use QS\Modules\Finance\Domain\Entity\Payment;

interface PaymentRepository
{
    /**
     * @return array<int, Payment>
     */
    public function findAll(): array;

    /**
     * @return array<int, Payment>
     */
    public function findByMonth(string $month): array;

    public function save(Payment $payment): Payment;
}
