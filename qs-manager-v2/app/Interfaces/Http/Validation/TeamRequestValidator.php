<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http\Validation;

final class TeamRequestValidator
{
    private const ROLES = ['admin', 'coordinadora', 'staff'];

    /**
     * @return array{display_name: string, role: string, phone: ?string, comuna_base: ?string, aliases: list<string>, active: bool}
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

        $phone = $this->stringField($body, 'phone', false, $errors);
        if ($phone !== null && !preg_match('/^\+?[0-9\s\-]+$/', $phone)) {
            $errors['phone'][] = 'Phone must match /^\\+?[0-9\\s\\-]+$/.';
        }
        if ($phone !== null && mb_strlen($phone) > 40) {
            $errors['phone'][] = 'Phone cannot exceed 40 characters.';
        }

        $comunaBase = $this->stringField($body, 'comuna_base', false, $errors);
        if ($comunaBase !== null && mb_strlen($comunaBase) > 120) {
            $errors['comuna_base'][] = 'Comuna base cannot exceed 120 characters.';
        }

        // Los alias llegan como lista o como texto separado por comas.
        $aliases = [];
        $rawAliases = $body['aliases'] ?? null;
        if (is_string($rawAliases)) {
            $rawAliases = explode(',', $rawAliases);
        }
        if (is_array($rawAliases)) {
            foreach ($rawAliases as $alias) {
                if (!is_string($alias)) {
                    $errors['aliases'][] = 'Aliases must be a list of names.';
                    break;
                }
                $alias = trim($alias);
                if ($alias !== '') {
                    $aliases[] = $alias;
                }
            }
        } elseif ($rawAliases !== null) {
            $errors['aliases'][] = 'Aliases must be a list of names.';
        }

        $active = true;
        if (array_key_exists('active', $body) && $body['active'] !== null && $body['active'] !== '') {
            $parsed = filter_var($body['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed === null) {
                $errors['active'][] = 'Active must be true or false.';
            } else {
                $active = $parsed;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'display_name' => $displayName ?? '',
            'role' => $role ?? '',
            'phone' => $phone,
            'comuna_base' => $comunaBase,
            'aliases' => $aliases,
            'active' => $active,
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

