<?php

declare(strict_types=1);

namespace QS\Core\Security;

final class NonceManager
{
    public function create(string $action): string
    {
        if (! function_exists('wp_create_nonce')) {
            return hash('sha256', $action);
        }

        return (string) wp_create_nonce($action);
    }

    public function verify(string $nonce, string $action): bool
    {
        if (! function_exists('wp_verify_nonce')) {
            return hash_equals(hash('sha256', $action), $nonce);
        }

        return wp_verify_nonce($nonce, $action) !== false;
    }
}
