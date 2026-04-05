<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Finance;

use QS\Modules\Finance\Domain\ValueObject\PaymentMethod;
use QS\Shared\Testing\TestCase;

final class PaymentMethodTest extends TestCase
{
    public function testReturnsTransferenciaWhenMatched(): void
    {
        self::assertSame(PaymentMethod::Transferencia, PaymentMethod::fromNullable('transferencia'));
    }

    public function testReturnsEfectivoWhenMatched(): void
    {
        self::assertSame(PaymentMethod::Efectivo, PaymentMethod::fromNullable('efectivo'));
    }

    public function testReturnsOtroForUnknownValues(): void
    {
        self::assertSame(PaymentMethod::Otro, PaymentMethod::fromNullable('cheque'));
    }

    public function testReturnsOtroForNull(): void
    {
        self::assertSame(PaymentMethod::Otro, PaymentMethod::fromNullable(null));
    }
}
