<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Core;

use QS\Core\Security\RequestSanitizer;
use QS\Shared\Testing\TestCase;

final class RequestSanitizerTest extends TestCase
{
    public function testSanitizeTextTrimsScalarValues(): void
    {
        $sanitizer = new RequestSanitizer();

        self::assertSame('hola', $sanitizer->sanitizeText(' hola '));
        self::assertNull($sanitizer->sanitizeNullableText('   '));
    }

    public function testSanitizeEmailReturnsLowercaseValidEmail(): void
    {
        $sanitizer = new RequestSanitizer();

        self::assertSame('demo@example.com', $sanitizer->sanitizeEmail('Demo@Example.com'));
        self::assertNull($sanitizer->sanitizeEmail('correo-invalido'));
    }

    public function testSanitizeIntAndBoolHandleInvalidInput(): void
    {
        $sanitizer = new RequestSanitizer();

        self::assertSame(42, $sanitizer->sanitizeInt('42'));
        self::assertSame(7, $sanitizer->sanitizeInt('not-an-int', 7));
        self::assertTrue($sanitizer->sanitizeBool('true'));
        self::assertFalse($sanitizer->sanitizeBool('nope'));
    }
}
