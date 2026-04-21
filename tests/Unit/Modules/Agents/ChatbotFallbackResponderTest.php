<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Agents;

use QS\Modules\Agents\Infrastructure\Wordpress\ChatbotFallbackResponder;
use QS\Shared\Testing\TestCase;

final class ChatbotFallbackResponderTest extends TestCase
{
    public function testUnavailableResponseIncludesWhatsappUrlWhenConfigured(): void
    {
        $responder = new ChatbotFallbackResponder('https://wa.me/56912345678');

        $payload = $responder->unavailableResponse();

        self::assertTrue($payload['success']);
        self::assertTrue($payload['fallback']);
        self::assertSame('whatsapp', $payload['fallback_channel']);
        self::assertSame('https://wa.me/56912345678', $payload['whatsapp_url']);
        self::assertSame('service_unavailable', $payload['fallback_reason']);
        self::assertStringContainsString('WhatsApp', $payload['response']);
        self::assertStringContainsString('https://wa.me/56912345678', $payload['response']);
    }

    public function testUnavailableResponseUsesDefaultProfileWhatsappUrlWhenMissing(): void
    {
        $responder = new ChatbotFallbackResponder();

        $payload = $responder->unavailableResponse();

        self::assertTrue($payload['success']);
        self::assertTrue($payload['fallback']);
        // Profile is the lowest-priority fallback in resolveWhatsappUrl(); falls through
        // here because no constructor value, WP option, env var, or constant is set.
        self::assertSame('https://wa.me/56950172974', $payload['whatsapp_url']);
        self::assertSame('service_unavailable', $payload['fallback_reason']);
        self::assertStringContainsString('WhatsApp', $payload['response']);
    }
}
