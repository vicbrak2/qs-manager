<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http\Validation;

final class TeamRequestValidator
{
    private const ROLES = ['admin', 'coordinadora', 'staff'];

    /**
     * @return array{display_name: string, role: string}
     */
    public function validate(array $body): array
    {
        $errors = [];

        $displayName = $this->stringField($body, 'display_name', true, $errors);
        if ($displayName !== null) {
            $length = mb_strlen($displayName);
            if ($length < 3) {
                $errors['display_name'][] = 'Display name must be at least 3 characters.';
            }
            if ($length > 160) {
                $errors['display_name'][] = 'Display name cannot exceed 160 characters.';
            }
        }

        $role = $this->stringField($body, 'role', true, $errors);
        if ($role !== null && !in_array($role, self::ROLES, true)) {
            $errors['role'][] = 'Role must be one of: admin, coordinadora, staff.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'display_name' => $displayName ?? '',
            'role' => $role ?? '',
        ];
    }

    private function stringField(array $body, string $field, bool $required, array &$errors): ?string
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            if ($required) {
                $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
            return null;
        }

        if (!is_string($body[$field])) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must be a string.';
            return null;
        }

        $value = trim($body[$field]);
        if ($required && $value === '') {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            return null;
        }

        return $value === '' ? null : $value;
    }
}

