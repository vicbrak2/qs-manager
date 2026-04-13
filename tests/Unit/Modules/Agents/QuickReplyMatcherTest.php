<?php

declare(strict_types=1);

namespace QS\Tests\Unit\Modules\Agents;

use InvalidArgumentException;
use QS\Modules\Agents\Infrastructure\Chatbot\QuickReplyMatcher;
use QS\Shared\Testing\TestCase;

final class QuickReplyMatcherTest extends TestCase
{
    public function testMatchesBuiltInServicesRule(): void
    {
        $matcher = new QuickReplyMatcher();

        $reply = $matcher->match('que servicios tienen?');

        self::assertNotNull($reply);
        self::assertStringContainsString('maquillaje social', $reply);
    }

    public function testMatchesReservationRuleUsingFuzzySimilarity(): void
    {
        $matcher = new QuickReplyMatcher();

        $reply = $matcher->match('puedo reservar una hora?');

        self::assertNotNull($reply);
        self::assertStringContainsString('datos paso a paso', $reply);
    }

    public function testMatchesBridalRuleForLongQuestion(): void
    {
        $matcher = new QuickReplyMatcher();

        $reply = $matcher->match('Cual es la diferencia entre novia civil y novia fiesta si quiero maquillarme con ustedes en Santiago?');

        self::assertNotNull($reply);
        self::assertStringContainsString('novia civil como novia fiesta', $reply);
        self::assertStringContainsString('Deseas reservar?', $reply);
    }

    public function testConfiguredRulesCanOverrideDefaults(): void
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
