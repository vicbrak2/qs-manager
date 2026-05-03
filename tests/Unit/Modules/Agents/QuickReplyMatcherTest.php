<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Agents;

use InvalidArgumentException;
use QS\Modules\Agents\Infrastructure\Chatbot\QuickReplyMatcher;
use QS\Shared\Testing\TestCase;

final class QuickReplyMatcherTest extends TestCase
{
    public function testReturnsNullWithoutConfiguredRules(): void
    {
        // defaultRules() fue eliminado — sin reglas configuradas el matcher no intercepta nada.
        // Las respuestas informativas van al LLM vía n8n.
        $matcher = new QuickReplyMatcher();

        self::assertNull($matcher->match('que servicios tienen?'));
        self::assertNull($matcher->match('puedo reservar una hora?'));
        self::assertNull($matcher->match('novia civil'));
    }

    public function testConfiguredRulesMatchCorrectly(): void
    {
        $json = (string) json_encode([
            [
                'id' => 'prices_custom',
                'response' => 'Respuesta local custom para precios.',
                'examples' => ['precios'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $matcher = new QuickReplyMatcher($json, '0.80');

        self::assertSame('Respuesta local custom para precios.', $matcher->match('precios?'));
    }

    public function testConfiguredRulesMatchBridalQuestion(): void
    {
        $json = (string) json_encode([
            [
                'id' => 'novia',
                'response' => 'Tenemos servicios para novia civil y novia fiesta.',
                'examples' => ['novia civil', 'novia fiesta', 'diferencia novia civil fiesta'],
                'min_score' => 0.75,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $matcher = new QuickReplyMatcher($json, '0.75');

        $reply = $matcher->match('novia civil');

        self::assertNotNull($reply);
        self::assertStringContainsString('novia civil', $reply);
    }

    public function testCustomThresholdCanBlockLooseMatches(): void
    {
        $json = (string) json_encode([
            [
                'id' => 'whatsapp',
                'response' => 'Te comparto WhatsApp.',
                'examples' => ['hablar por whatsapp'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $defaultMatcher = new QuickReplyMatcher($json, '0.80');
        $strictMatcher = new QuickReplyMatcher($json, '0.95');

        self::assertSame('Te comparto WhatsApp.', $defaultMatcher->match('quiero hablar por whatsapp ahora'));
        self::assertNull($strictMatcher->match('quiero hablar por whatsapp ahora'));
    }

    public function testSanitizeRulesJsonRejectsInvalidRules(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QuickReplyMatcher::sanitizeRulesJson('{"foo":"bar"}');
    }
}
