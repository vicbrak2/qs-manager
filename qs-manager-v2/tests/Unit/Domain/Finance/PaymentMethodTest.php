<?php

declare(strict_types=1);

namespace QSManager\Tests\Unit\Domain\Finance;

use PHPUnit\Framework\TestCase;
use QSManager\Domain\Finance\PaymentMethod;

final class PaymentMethodTest extends TestCase
{
    public function testRecognizesTransferencia(): void
    {
        $this->assertSame(PaymentMethod::Transferencia, PaymentMethod::fromNullable('transferencia'));
    }

    public function testRecognizesEfectivo(): void
    {
        $this->assertSame(PaymentMethod::Efectivo, PaymentMethod::fromNullable('efectivo'));
    }

    public function testUnknownValueFallsBackToOtro(): void
    {
        $this->assertSame(PaymentMethod::Otro, PaymentMethod::fromNullable('cheque'));
    }

    public function testNullFallsBackToOtro(): void
    {
        $this->assertSame(PaymentMethod::Otro, PaymentMethod::fromNullable(null));
    }

    public function testEmptyStringFallsBackToOtro(): void
    {
        $this->assertSame(PaymentMethod::Otro, PaymentMethod::fromNullable(''));
    }

    /**
     * A diferencia de V1: los datos vienen de una columna de planilla
     * ("forma de pago") con casing y espacios inconsistentes, asi que la
     * normalizacion tiene que tolerar eso.
     */
    public function testIsCaseInsensitiveAndTrimsWhitespace(): void
    {
        $this->assertSame(PaymentMethod::Transferencia, PaymentMethod::fromNullable('  Transferencia  '));
        $this->assertSame(PaymentMethod::Efectivo, PaymentMethod::fromNullable('EFECTIVO'));
        $this->assertSame(PaymentMethod::Transferencia, PaymentMethod::fromNullable('TRANSFERENCIA'));
    }

    public function testValueMatchesUnderlyingString(): void
    {
        $this->assertSame('transferencia', PaymentMethod::Transferencia->value);
        $this->assertSame('efectivo', PaymentMethod::Efectivo->value);
        $this->assertSame('otro', PaymentMethod::Otro->value);
    }
}
