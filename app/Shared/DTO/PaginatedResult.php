<?php

declare(strict_types=1);

namespace QS\Shared\DTO;

final class PaginatedResult
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $page,
        private readonly int $perPage,
        private readonly int $total
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $this->total,
        ];
    }
}
