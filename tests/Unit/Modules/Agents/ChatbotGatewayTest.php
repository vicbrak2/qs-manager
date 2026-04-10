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
        return $default;
    }
}

namespace QS\Tests\Unit\Modules\Agents {

    use QS\Modules\Agents\Infrastructure\Chatbot\QuickReplyMatcher;
    use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
    use QS\Shared\Testing\TestCase;

    final class ChatbotGatewayWordpressStubs
    {
        /** @var array<string, string> */
        private static array $transients = [];

        /** @var list<array{url: string, args: array<string, mixed>}> */
        private static array $remotePosts = [];

        /** @var list<array<string, mixed>|\WP_Error> */
        private static array $remoteResponses = [];

        public static function reset(): void
        {
            self::$transients = [];
            self::$remotePosts = [];
            self::$remoteResponses = [];
        }

        public static function getTransient(string $key): ?string
        {
            return self::$transients[$key] ?? null;
        }

        public static function setTransient(string $key, string $value, int $ttl): void
        {
            self::$transients[$key] = $value;
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

            $first = $gateway->ask('Necesito ayuda con un caso especial', 'session-a');
            $cached = $gateway->ask('Necesito ayuda con un caso especial', 'session-a');
            $secondSession = $gateway->ask('Necesito ayuda con un caso especial', 'session-b');

            self::assertSame('respuesta sesion a', $first);
            self::assertSame('respuesta sesion a', $cached);
            self::assertSame('respuesta sesion b', $secondSession);
            self::assertCount(2, ChatbotGatewayWordpressStubs::remotePosts());
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
    }
}
