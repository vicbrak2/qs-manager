<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Infrastructure\Persistence;

use DateTimeImmutable;
use QS\Modules\Finance\Domain\Entity\Expense;
use QS\Modules\Finance\Domain\Repository\ExpenseRepository;
use QS\Shared\ValueObjects\Money;

final class ExpenseCptRepository implements ExpenseRepository
{
    public function findAll(): array
    {
        if (! function_exists('get_posts')) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'qs_expense',
            'post_status' => ['publish', 'private', 'draft'],
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (! is_array($posts)) {
            return [];
        }

        return array_map(
            fn (\WP_Post $post): Expense => $this->hydrate($post),
            array_values(array_filter($posts, static fn ($post): bool => $post instanceof \WP_Post))
        );
    }

    public function findByMonth(string $month): array
    {
        return array_values(array_filter(
            $this->findAll(),
            static fn (Expense $expense): bool => $expense->month() === $month
        ));
    }

    private function hydrate(\WP_Post $post): Expense
    {
        $concept = $this->stringMeta($post->ID, 'concepto');
        $month = $this->stringMeta($post->ID, 'mes_anio');

        return new Expense(
            (int) $post->ID,
            $concept !== '' ? $concept : $post->post_title,
            new Money($this->intMeta($post->ID, 'monto_clp') ?? 0),
            $this->stringMeta($post->ID, 'categoria') ?: 'fijo',
            $month !== '' ? $month : (new DateTimeImmutable($post->post_date))->format('Y-m'),
            $this->nullableStringMeta($post->ID, 'comprobante_url'),
            new DateTimeImmutable($post->post_date)
        );
    }

    private function stringMeta(int $postId, string $key): string
    {
        $value = get_post_meta($postId, $key, true);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function nullableStringMeta(int $postId, string $key): ?string
    {
        $value = $this->stringMeta($postId, $key);

        return $value === '' ? null : $value;
    }

    private function intMeta(int $postId, string $key): ?int
    {
        $value = get_post_meta($postId, $key, true);

        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
