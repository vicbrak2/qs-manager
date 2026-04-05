<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use QS\Modules\Finance\Application\Command\RegisterPayment;
use QS\Modules\Finance\Application\CommandHandler\RegisterPaymentHandler;
use QS\Modules\Finance\Domain\Entity\Payment;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;
use QS\Modules\Finance\Domain\ValueObject\PaymentMethod;
use QS\Shared\Testing\TestCase;

final class RegisterPaymentHandlerTest extends TestCase
{
    /** @var PaymentRepository&MockObject */
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
    }

    public function testRegistersPaymentWithReservationId(): void
    {
        $this->paymentRepository->expects(self::once())
            ->method('save')
            ->willReturnArgument(0);

        $result = $this->handler()->handle(new RegisterPayment(
            5,
            null,
            90000,
            PaymentMethod::Transferencia,
            'registered',
            '2026-04-03T10:15:00+00:00',
            '2026-04'
        ))->toArray();

        self::assertIsString($result['paid_at']);
        self::assertSame(5, $result['reservation_id']);
        self::assertSame(90000, $result['amount_clp']);
        self::assertSame('transferencia', $result['method']);
        self::assertSame('registered', $result['status']);
        self::assertStringStartsWith('2026-04-03T10:15:00', $result['paid_at']);
    }

    public function testRegistersPaymentWithTrimmedConceptWhenNoReservationId(): void
    {
        $this->paymentRepository->method('save')->willReturnArgument(0);

        $result = $this->handler()->handle(new RegisterPayment(
            null,
            '  Google Workspace  ',
            15520,
            PaymentMethod::Transferencia,
            'registered',
            '2026-04-01T09:00:00+00:00',
            '2026-04'
        ))->toArray();

        self::assertNull($result['reservation_id']);
        self::assertSame('Google Workspace', $result['concept']);
    }

    public function testThrowsWhenAmountIsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero.');

        $this->handler()->handle(new RegisterPayment(
            1,
            null,
            0,
            PaymentMethod::Transferencia,
            'registered',
            '2026-04-01T09:00:00+00:00',
            '2026-04'
        ));
    }

    public function testThrowsWhenAmountIsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero.');

        $this->handler()->handle(new RegisterPayment(
            1,
            null,
            -1000,
            PaymentMethod::Transferencia,
            'registered',
            '2026-04-01T09:00:00+00:00',
            '2026-04'
        ));
    }

    public function testThrowsWhenReservationIdAndConceptAreMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment requires reservation_id or concept.');

        $this->handler()->handle(new RegisterPayment(
            null,
            null,
            50000,
            PaymentMethod::Transferencia,
            'registered',
            '2026-04-01T09:00:00+00:00',
            '2026-04'
        ));
    }

    public function testThrowsWhenConceptIsBlankAndReservationIdMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment requires reservation_id or concept.');

        $this->handler()->handle(new RegisterPayment(
            null,
            '   ',
            50000,
            PaymentMethod::Transferencia,
            'registered',
            '2026-04-01T09:00:00+00:00',
            '2026-04'
        ));
    }

    public function testDefaultsBlankStatusToRegistered(): void
    {
        $savedPayment = null;

        $this->paymentRepository->method('save')
            ->willReturnCallback(static function (Payment $payment) use (&$savedPayment): Payment {
                $savedPayment = $payment;

                return $payment;
            });

        $result = $this->handler()->handle(new RegisterPayment(
            3,
            null,
            70000,
            PaymentMethod::Efectivo,
            '   ',
            '2026-04-03T10:15:00+00:00',
            '2026-04'
        ))->toArray();

        self::assertInstanceOf(Payment::class, $savedPayment);
        self::assertSame('registered', $savedPayment->status());
        self::assertSame('registered', $result['status']);
    }

    public function testTrimsNonBlankStatusBeforeSaving(): void
    {
        $savedPayment = null;

        $this->paymentRepository->method('save')
            ->willReturnCallback(static function (Payment $payment) use (&$savedPayment): Payment {
                $savedPayment = $payment;

                return $payment;
            });

        $result = $this->handler()->handle(new RegisterPayment(
            3,
            null,
            70000,
            PaymentMethod::Efectivo,
            '  paid  ',
            '2026-04-03T10:15:00+00:00',
            '2026-04'
        ))->toArray();

        self::assertInstanceOf(Payment::class, $savedPayment);
        self::assertSame('paid', $savedPayment->status());
        self::assertSame('paid', $result['status']);
    }

    private function handler(): RegisterPaymentHandler
    {
        return new RegisterPaymentHandler($this->paymentRepository);
    }
}
