<?php

declare(strict_types=1);

namespace {
    if (! class_exists('WP_Error')) {
        final class WP_Error
        {
            /**
             * @param array<string, mixed>|string $data
             */
            public function __construct(
                private readonly string $code = '',
                private readonly string $message = '',
                private readonly mixed $data = []
            ) {
            }

            public function get_error_code(): string
            {
                return $this->code;
            }

            public function get_error_message(): string
            {
                return $this->message;
            }

            /**
             * @return array<string, mixed>|string
             */
            public function get_error_data(): mixed
            {
                return $this->data;
            }
        }
    }
}

namespace QS\Modules\Agents\Infrastructure\N8n {
    use QS\Tests\Unit\Modules\Agents\ChatbotGatewayWordpressStubs;

    function get_transient(string $key): mixed
    {
        return ChatbotGatewayWordpressStubs::getTransient($key);
    }

    function set_transient(string $key, string $value, int $ttl): bool
    {
        ChatbotGatewayWordpressStubs::setTransient($key, $value, $ttl);

        return true;
    }

    function delete_transient(string $key): bool
    {
        ChatbotGatewayWordpressStubs::clearTransient($key);

        return true;
    }

    /**
     * @param array<string, mixed> $value
     */
    function wp_json_encode(array $value): string|false
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>|\WP_Error
     */
    function wp_remote_post(string $url, array $args): array|\WP_Error
    {
        return ChatbotGatewayWordpressStubs::remotePost($url, $args);
    }

    function is_wp_error(mixed $value): bool
    {
        return $value instanceof \WP_Error;
    }

    /**
     * @param array<string, mixed> $response
     */
    function wp_remote_retrieve_response_code(array $response): int
    {
        return (int) (($response['response']['code'] ?? 0));
    }

    /**
     * @param array<string, mixed> $response
     */
    function wp_remote_retrieve_body(array $response): string
    {
        return (string) ($response['body'] ?? '');
    }

    function get_option(string $name, mixed $default = ''): mixed
    {
        return ChatbotGatewayWordpressStubs::getOption($name, $default);
    }
}

namespace QS\Tests\Unit\Modules\Agents {

    use QS\Core\Config\PluginConfig;
    use QS\Core\Logging\Logger;
    use QS\Modules\Agents\Infrastructure\Chatbot\QuickReplyMatcher;
    use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
    use QS\Modules\Agents\Infrastructure\N8n\WhatsAppGateway;
    use QS\Shared\Testing\TestCase;

    final class ChatbotGatewayWordpressStubs
    {
        /** @var array<string, string> */
        private static array $transients = [];

        /** @var list<array{url: string, args: array<string, mixed>}> */
        private static array $remotePosts = [];

        /** @var list<array<string, mixed>|\WP_Error> */
        private static array $remoteResponses = [];

        /** @var array<string, mixed> */
        private static array $options = [];

        public static function reset(): void
        {
            self::$transients = [];
            self::$remotePosts = [];
            self::$remoteResponses = [];
            self::$options = [];
        }

        public static function getTransient(string $key): ?string
        {
            return self::$transients[$key] ?? null;
        }

        public static function setTransient(string $key, string $value, int $ttl): void
        {
            self::$transients[$key] = $value;
        }

        public static function clearTransient(string $key): void
        {
            unset(self::$transients[$key]);
        }

        /**
         * @param array<string, mixed> $response
         */
        public static function queueRemoteResponse(array|\WP_Error $response): void
        {
            self::$remoteResponses[] = $response;
        }

        /**
         * @param array<string, mixed> $args
         * @return array<string, mixed>|\WP_Error
         */
        public static function remotePost(string $url, array $args): array|\WP_Error
        {
            self::$remotePosts[] = [
                'url' => $url,
                'args' => $args,
            ];

            if (self::$remoteResponses === []) {
                return [
                    'response' => ['code' => 200],
                    'body' => '{"output":"respuesta stub"}',
                ];
            }

            return array_shift(self::$remoteResponses);
        }

        /**
         * @return list<array{url: string, args: array<string, mixed>}>
         */
        public static function remotePosts(): array
        {
            return self::$remotePosts;
        }

        public static function setOption(string $name, mixed $value): void
        {
            self::$options[$name] = $value;
        }

        public static function getOption(string $name, mixed $default = ''): mixed
        {
            return self::$options[$name] ?? $default;
        }
    }

    final class ChatbotGatewayTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            ChatbotGatewayWordpressStubs::reset();
        }

        public function testCacheIsScopedPerSessionId(): void
        {
            $gateway = new ChatbotGateway(new QuickReplyMatcher());

            ChatbotGatewayWordpressStubs::queueRemoteResponse([
                'response' => ['code' => 200],
                'body' => '{"output":"respuesta sesion a"}',
            ]);
            ChatbotGatewayWordpressStubs::queueRemoteResponse([
                'response' => ['code' => 200],
                'body' => '{"output":"respuesta sesion b"}',
            ]);

            // Same message, different sessions: each must reach n8n independently.
            $first = $gateway->ask('Necesito ayuda con un caso especial', 'session-a');
            $secondSession = $gateway->ask('Necesito ayuda con un caso especial', 'session-b');

            self::assertSame('respuesta sesion a', $first);
            self::assertSame('respuesta sesion b', $secondSession);
            self::assertCount(2, ChatbotGatewayWordpressStubs::remotePosts());
        }

        public function testCacheHitWhenSameSessionAndSameConversationState(): void
        {
            $gateway = new ChatbotGateway(new QuickReplyMatcher());

            ChatbotGatewayWordpressStubs::queueRemoteResponse([
                'response' => ['code' => 200],
                'body' => '{"output":"respuesta unica"}',
            ]);

            // Two concurrent requests with the same session and no accumulated history
            // (history is only stored after the first call completes, so a concurrent
            // duplicate at session start is the scenario where cache helps most).
            $first  = $gateway->ask('consulta unica sin historial previo', 'session-fresh');

            // Manually reset history so the second call sees the same conversation state.
            ChatbotGatewayWordpressStubs::clearTransient('qs_chat_hist_' . md5('session-fresh'));

            $cached = $gateway->ask('consulta unica sin historial previo', 'session-fresh');

            self::assertSame('respuesta unica', $first);
            self::assertSame('respuesta unica', $cached);
            self::assertCount(1, ChatbotGatewayWordpressStubs::remotePosts());
        }

        public function testAskTruncatesLongInputBeforeSendingToN8n(): void
        {
            $gateway = new ChatbotGateway(new QuickReplyMatcher());

            ChatbotGatewayWordpressStubs::queueRemoteResponse([
                'response' => ['code' => 200],
                'body' => '{"output":"ok"}',
            ]);

            $gateway->ask(str_repeat('a', 900), 'session-long');

            $posts = ChatbotGatewayWordpressStubs::remotePosts();

            self::assertCount(1, $posts);
            self::assertIsString($posts[0]['args']['body'] ?? null);

            $payload = json_decode((string) $posts[0]['args']['body'], true, 512, JSON_THROW_ON_ERROR);

            self::assertIsArray($payload);
            self::assertSame(800, strlen((string) ($payload['message'] ?? '')));
            self::assertSame('session-long', $payload['session_id'] ?? null);
        }

        public function testBookingFlowAsksForOneFieldAtATimeAfterExplicitReservationIntent(): void
        {
            $gateway = new ChatbotGateway(new QuickReplyMatcher());

            $first = $gateway->ask('quiero reservar una hora', 'session-booking');
            $second = $gateway->ask('Maquillaje social', 'session-booking');
            $third = $gateway->ask('Providencia', 'session-booking');
            $fourth = $gateway->ask('Los Leones 123', 'session-booking');
            $fifth = $gateway->ask('+56912345678', 'session-booking');
            $sixth = $gateway->ask('20 de abril', 'session-booking');

            self::assertStringContainsString('Primero dime que servicio necesitas', $first);
            self::assertStringContainsString('comuna', $second);
            self::assertStringContainsString('direccion', $third);
            self::assertStringContainsString('telefono', $fourth);
            self::assertStringContainsString('fecha', $fifth);
            self::assertStringContainsString('ya tengo los datos base', $sixth);
            self::assertCount(0, ChatbotGatewayWordpressStubs::remotePosts());
        }

        public function testAffirmativeReplyStartsBookingFlowOnlyAfterBotAskedReservationIntent(): void
        {
            $gateway = new ChatbotGateway(new QuickReplyMatcher());

            $reply = $gateway->ask('precios', 'session-price');
            $confirmation = $gateway->ask('si', 'session-price');

            self::assertStringContainsString('Deseas reservar?', $reply);
            self::assertStringContainsString('Primero dime que servicio necesitas', $confirmation);
            self::assertCount(0, ChatbotGatewayWordpressStubs::remotePosts());
        }
    }

    final class WhatsAppGatewayTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            ChatbotGatewayWordpressStubs::reset();
            putenv('QS_N8N_WHATSAPP_ACTIONS_ENABLED');
            putenv('QS_N8N_WHATSAPP_ALLOWED_PHONES');
        }

        protected function tearDown(): void
        {
            putenv('QS_N8N_WHATSAPP_ACTIONS_ENABLED');
            putenv('QS_N8N_WHATSAPP_ALLOWED_PHONES');
            parent::tearDown();
        }

        public function testSendIsBlockedWhenWhatsappActionsAreDisabled(): void
        {
            $gateway = $this->whatsAppGateway();

            $result = $gateway->send('56912345678', 'hola');

            self::assertFalse($result['ok']);
            self::assertSame('Las acciones de WhatsApp estan desactivadas.', $result['error']);
            self::assertCount(0, ChatbotGatewayWordpressStubs::remotePosts());
        }

        public function testSendIsBlockedWhenNoAllowedPhonesAreConfigured(): void
        {
            ChatbotGatewayWordpressStubs::setOption('qs_n8n_whatsapp_actions_enabled', '1');

            $gateway = $this->whatsAppGateway();

            $result = $gateway->send('56912345678', 'hola');

            self::assertFalse($result['ok']);
            self::assertSame('El numero destino no esta permitido para acciones de WhatsApp.', $result['error']);
            self::assertCount(0, ChatbotGatewayWordpressStubs::remotePosts());
        }

        public function testSendPostsToN8nWhenDestinationIsAllowed(): void
        {
            ChatbotGatewayWordpressStubs::setOption('qs_n8n_whatsapp_actions_enabled', '1');
            ChatbotGatewayWordpressStubs::setOption('qs_n8n_whatsapp_allowed_phones', "56912345678\n56987654321");

            $gateway = $this->whatsAppGateway();

            $result = $gateway->send('+56 9 1234 5678', 'hola');

            self::assertTrue($result['ok']);

            $posts = ChatbotGatewayWordpressStubs::remotePosts();
            self::assertCount(1, $posts);

            $payload = json_decode((string) $posts[0]['args']['body'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('+56912345678', $payload['phone']);
            self::assertSame('hola', $payload['text']);
        }

        private function whatsAppGateway(): WhatsAppGateway
        {
            return new WhatsAppGateway(new Logger(QS_CORE_ROOT_DIR, new PluginConfig([
                'paths' => ['logs' => 'var/tmp'],
                'logging' => ['file' => 'test.log'],
            ])));
        }
    }
}
