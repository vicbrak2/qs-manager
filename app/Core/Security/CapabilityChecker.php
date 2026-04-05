<?php

declare(strict_types=1);

namespace QS\Core\Security;

final class CapabilityChecker
{
    public function currentUserCan(string $capability): bool
    {
        if (! function_exists('current_user_can')) {
            return false;
        }

        if (str_starts_with($capability, 'qs_') && (bool) current_user_can('manage_options')) {
            return true;
        }

        return (bool) current_user_can($capability);
    }

    public function userCan(int $userId, string $capability): bool
    {
        if (! function_exists('user_can')) {
            return false;
        }

        if (str_starts_with($capability, 'qs_') && (bool) user_can($userId, 'manage_options')) {
            return true;
        }

        return (bool) user_can($userId, $capability);
    }
}
