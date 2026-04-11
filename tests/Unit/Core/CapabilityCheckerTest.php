<?php

declare(strict_types=1);

namespace QS\Core\Security {
    use QS\Tests\Unit\Core\CapabilityCheckerWordpressStubs;

    function current_user_can(string $capability): bool
    {
        return CapabilityCheckerWordpressStubs::currentUserCan($capability);
    }

    function user_can(int $userId, string $capability): bool
    {
        return CapabilityCheckerWordpressStubs::userCan($userId, $capability);
    }
}

namespace QS\Tests\Unit\Core {

    use QS\Core\Config\PluginConfig;
    use QS\Core\Security\CapabilityChecker;
    use QS\Shared\Testing\TestCase;

    final class CapabilityCheckerWordpressStubs
    {
        /** @var array<string, bool> */
        private static array $currentUserCapabilities = [];

        /** @var array<int, array<string, bool>> */
        private static array $userCapabilities = [];

        public static function reset(): void
        {
            self::$currentUserCapabilities = [];
            self::$userCapabilities = [];
        }

        public static function allowCurrentUser(string $capability, bool $allowed): void
        {
            self::$currentUserCapabilities[$capability] = $allowed;
        }

        public static function allowUser(int $userId, string $capability, bool $allowed): void
        {
            self::$userCapabilities[$userId][$capability] = $allowed;
        }

        public static function currentUserCan(string $capability): bool
        {
            return self::$currentUserCapabilities[$capability] ?? false;
        }

        public static function userCan(int $userId, string $capability): bool
        {
            return self::$userCapabilities[$userId][$capability] ?? false;
        }
    }

    final class CapabilityCheckerTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            CapabilityCheckerWordpressStubs::reset();
        }

        public function testCurrentUserCanUsesConfiguredAdminOverridePrefix(): void
        {
            $checker = new CapabilityChecker(new PluginConfig([
                'capabilities' => [
                    'admin_override_capability' => 'manage_options',
                    'admin_override_prefixes' => ['qs_'],
                ],
            ]));

            CapabilityCheckerWordpressStubs::allowCurrentUser('manage_options', true);

            self::assertTrue($checker->currentUserCan('qs_manage_bookings'));
        }

        public function testCurrentUserCanFallsBackToRequestedCapabilityWhenPrefixDoesNotMatch(): void
        {
            $checker = new CapabilityChecker(new PluginConfig([
                'capabilities' => [
                    'admin_override_capability' => 'manage_options',
                    'admin_override_prefixes' => ['corp_'],
                ],
            ]));

            CapabilityCheckerWordpressStubs::allowCurrentUser('manage_options', true);
            CapabilityCheckerWordpressStubs::allowCurrentUser('qs_manage_bookings', false);

            self::assertFalse($checker->currentUserCan('qs_manage_bookings'));
        }

        public function testUserCanUsesConfiguredAdminOverrideCapability(): void
        {
            $checker = new CapabilityChecker(new PluginConfig([
                'capabilities' => [
                    'admin_override_capability' => 'plugin_admin',
                    'admin_override_prefixes' => ['qs_'],
                ],
            ]));

            CapabilityCheckerWordpressStubs::allowUser(99, 'plugin_admin', true);

            self::assertTrue($checker->userCan(99, 'qs_manage_agents'));
        }
    }
}
