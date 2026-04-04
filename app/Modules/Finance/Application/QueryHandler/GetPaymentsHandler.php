<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\QueryHandler;

use QS\Modules\Finance\Application\DTO\PaymentDTO;
use QS\Modules\Finance\Application\Query\GetPayments;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;

final class GetPaymentsHandler
{
    public function __construct(private readonly PaymentRepository $paymentRepository)
    {
    }

    /**
     * @return array<int, PaymentDTO>
     */
    public function handle(GetPayments $query): array
    {
        $payments = $query->month !== null
            ? $this->paymentRepository->findByMonth($query->month)
            : $this->paymentRepository->findAll();

        return array_map(
            static fn ($payment): PaymentDTO => new PaymentDTO($payment),
            $payments
        );
    }
}
