<?php

declare(strict_types=1);

namespace QS\Core\Wordpress {
    use QS\Tests\Unit\Core\PostTypeRegistrarWordpressStubs;

    /**
     * @param array<string, mixed> $args
     */
    function register_post_type(string $slug, array $args): void
    {
        PostTypeRegistrarWordpressStubs::registerPostType($slug, $args);
    }
}

namespace QS\Tests\Unit\Core {

    use QS\Core\Wordpress\PostTypeRegistrar;
    use QS\Shared\Testing\TestCase;

    final class PostTypeRegistrarWordpressStubs
    {
        /** @var array<string, array<string, mixed>> */
        private static array $registeredPostTypes = [];

        public static function reset(): void
        {
            self::$registeredPostTypes = [];
        }

        /**
         * @param array<string, mixed> $args
         */
        public static function registerPostType(string $slug, array $args): void
        {
            self::$registeredPostTypes[$slug] = $args;
        }

        /**
         * @return array<string, array<string, mixed>>
         */
        public static function registeredPostTypes(): array
        {
            return self::$registeredPostTypes;
        }
    }

    final class PostTypeRegistrarTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            PostTypeRegistrarWordpressStubs::reset();
        }

        public function testRegistersConfiguredPostTypes(): void
        {
            $registrar = new PostTypeRegistrar([
                'demo_item' => [
                    'label' => 'Demo Items',
                    'singular_label' => 'Demo Item',
                    'menu_icon' => 'dashicons-admin-generic',
                ],
            ]);

            $registrar->registerPostTypes();

            $registered = PostTypeRegistrarWordpressStubs::registeredPostTypes();

            self::assertArrayHasKey('demo_item', $registered);
            self::assertSame('Demo Items', $registered['demo_item']['label']);
            self::assertSame('Demo Item', $registered['demo_item']['labels']['singular_name']);
            self::assertSame('dashicons-admin-generic', $registered['demo_item']['menu_icon']);
        }
    }
}
