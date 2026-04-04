<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Application\CommandHandler;

use DateTimeImmutable;
use InvalidArgumentException;
use QS\Modules\Finance\Application\Command\RegisterPayment;
use QS\Modules\Finance\Application\DTO\PaymentDTO;
use QS\Modules\Finance\Domain\Entity\Payment;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;
use QS\Shared\ValueObjects\Money;

final class RegisterPaymentHandler
{
    public function __construct(private readonly PaymentRepository $paymentRepository)
    {
    }

    public function handle(RegisterPayment $command): PaymentDTO
    {
        if ($command->amountClp <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if ($command->reservationId === null && ($command->concept === null || trim($command->concept) === '')) {
            throw new InvalidArgumentException('Payment requires reservation_id or concept.');
        }

        $payment = new Payment(
            null,
            $command->reservationId,
            $command->concept !== null ? trim($command->concept) : null,
            new Money($command->amountClp),
            $command->method,
            trim($command->status) !== '' ? trim($command->status) : 'registered',
            new DateTimeImmutable($command->paidAt),
            $command->closingMonth
        );

        return new PaymentDTO($this->paymentRepository->save($payment));
    }
}
