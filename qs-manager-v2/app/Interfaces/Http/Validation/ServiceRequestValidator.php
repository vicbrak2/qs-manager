<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http\Validation;

final class ServiceRequestValidator
{
    /**
     * @return array<string, mixed>
     */
    public function validate(array $body): array
    {
        $errors = [];

        $name = $this->stringField($body, 'name', true, $errors);
        if ($name !== null) {
            $length = mb_strlen($name);
            if ($length < 3) {
                $errors['name'][] = 'Name must be at least 3 characters.';
            }
            if ($length > 160) {
                $errors['name'][] = 'Name cannot exceed 160 characters.';
            }
        }

        $category = $this->stringField($body, 'category', false, $errors);
        if ($category !== null && mb_strlen($category) > 80) {
            $errors['category'][] = 'Category cannot exceed 80 characters.';
        }

        $duration = $this->optionalPositiveInt($body, 'duration_minutes', 'Duration minutes', $errors);
        $quantity = $this->optionalPositiveInt($body, 'quantity', 'Quantity', $errors) ?? 1;
        $salePrice = $this->optionalNonNegativeInt($body, 'sale_price', 'Sale price', $errors) ?? 0;
        $totalCost = $this->optionalNonNegativeInt($body, 'total_cost', 'Total cost', $errors) ?? 0;

        if ($salePrice !== null && $totalCost !== null && $totalCost > $salePrice) {
            $errors['total_cost'][] = 'Total cost cannot exceed sale price.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name ?? '',
            'category' => $category,
            'duration_minutes' => $duration,
            'quantity' => $quantity,
            'sale_price' => $salePrice ?? 0,
            'total_cost' => $totalCost ?? 0,
        ];
    }

    private function optionalNonNegativeInt(array $body, string $field, string $label, array &$errors): ?int
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (filter_var($body[$field], FILTER_VALIDATE_INT) === false || (int) $body[$field] < 0) {
            $errors[$field][] = $label . ' must be a non-negative integer.';
            return null;
        }

        return (int) $body[$field];
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

    private function optionalPositiveInt(array $body, string $field, string $label, array &$errors): ?int
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (filter_var($body[$field], FILTER_VALIDATE_INT) === false) {
            $errors[$field][] = $label . ' must be a positive integer.';
            return null;
        }

        $value = (int) $body[$field];
        if ($value <= 0) {
            $errors[$field][] = $label . ' must be a positive integer.';
            return null;
        }

        return $value;
    }
}
