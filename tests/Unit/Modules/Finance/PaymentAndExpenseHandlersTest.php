<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use QS\Modules\Finance\Application\Query\GetExpenses;
use QS\Modules\Finance\Application\Query\GetPayments;
use QS\Modules\Finance\Application\QueryHandler\GetExpensesHandler;
use QS\Modules\Finance\Application\QueryHandler\GetPaymentsHandler;
use QS\Modules\Finance\Domain\Entity\Expense;
use QS\Modules\Finance\Domain\Entity\Payment;
use QS\Modules\Finance\Domain\Repository\ExpenseRepository;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;
use QS\Modules\Finance\Domain\ValueObject\PaymentMethod;
use QS\Shared\Testing\TestCase;
use QS\Shared\ValueObjects\Money;

final class PaymentAndExpenseHandlersTest extends TestCase
{
    /** @var PaymentRepository&MockObject */
    private PaymentRepository $paymentRepository;

    /** @var ExpenseRepository&MockObject */
    private ExpenseRepository $expenseRepository;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->expenseRepository = $this->createMock(ExpenseRepository::class);
    }

    public function testGetPaymentsHandlerUsesFindAllWhenMonthIsNull(): void
    {
        $this->paymentRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([$this->payment(1, 90000, '2026-04')]);
        $this->paymentRepository->expects(self::never())
            ->method('findByMonth');

        $result = (new GetPaymentsHandler($this->paymentRepository))->handle(new GetPayments());

        self::assertCount(1, $result);
        self::assertSame(90000, $result[0]->toArray()['amount_clp']);
    }

    public function testGetPaymentsHandlerUsesFindByMonthWhenMonthIsProvided(): void
    {
        $this->paymentRepository->expects(self::once())
            ->method('findByMonth')
            ->with('2026-04')
            ->willReturn([$this->payment(1, 90000, '2026-04')]);
        $this->paymentRepository->expects(self::never())
            ->method('findAll');

        $result = (new GetPaymentsHandler($this->paymentRepository))->handle(new GetPayments('2026-04'));

        self::assertCount(1, $result);
        self::assertSame('transferencia', $result[0]->toArray()['method']);
    }

    public function testGetExpensesHandlerUsesFindAllWhenMonthIsNull(): void
    {
        $this->expenseRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([$this->expense(1, 30000, '2026-04')]);
        $this->expenseRepository->expects(self::never())
            ->method('findByMonth');

        $result = (new GetExpensesHandler($this->expenseRepository))->handle(new GetExpenses());

        self::assertCount(1, $result);
        self::assertSame(30000, $result[0]->toArray()['amount_clp']);
    }

    public function testGetExpensesHandlerUsesFindByMonthWhenMonthIsProvided(): void
    {
        $this->expenseRepository->expects(self::once())
            ->method('findByMonth')
            ->with('2026-04')
            ->willReturn([$this->expense(1, 30000, '2026-04')]);
        $this->expenseRepository->expects(self::never())
            ->method('findAll');

        $result = (new GetExpensesHandler($this->expenseRepository))->handle(new GetExpenses('2026-04'));

        self::assertCount(1, $result);
        self::assertSame('variable', $result[0]->toArray()['category']);
    }

    private function payment(int $id, int $amountClp, string $closingMonth): Payment
    {
        return new Payment(
            $id,
            $id,
            sprintf('Pago %d', $id),
            new Money($amountClp),
            PaymentMethod::Transferencia,
            'registered',
            new DateTimeImmutable('2026-04-03T10:00:00+00:00'),
            $closingMonth
        );
    }

    private function expense(int $id, int $amountClp, string $month): Expense
    {
        return new Expense(
            $id,
            sprintf('Gasto %d', $id),
            new Money($amountClp),
            'variable',
            $month,
            null,
            new DateTimeImmutable('2026-04-01T09:00:00+00:00')
        );
    }
}
