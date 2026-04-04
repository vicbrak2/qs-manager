<?php

declare(strict_types=1);

namespace QS\Core\Security;

final class RequestSanitizer
{
    public function sanitizeText(mixed $value): string
    {
        $normalized = is_scalar($value) ? trim((string) $value) : '';

        if (function_exists('sanitize_text_field')) {
            return (string) sanitize_text_field($normalized);
        }

        return trim((string) filter_var($normalized, FILTER_UNSAFE_RAW));
    }

    public function sanitizeNullableText(mixed $value): ?string
    {
        $sanitized = $this->sanitizeText($value);

        return $sanitized === '' ? null : $sanitized;
    }

    public function sanitizeEmail(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $email = trim((string) $value);
        $sanitized = filter_var($email, FILTER_VALIDATE_EMAIL);

        return is_string($sanitized) ? strtolower($sanitized) : null;
    }

    public function sanitizeInt(mixed $value, ?int $default = null): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_scalar($value)) {
            return $default;
        }

        $filtered = filter_var((string) $value, FILTER_VALIDATE_INT);

        return $filtered === false ? $default : (int) $filtered;
    }

    public function sanitizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $filtered ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
