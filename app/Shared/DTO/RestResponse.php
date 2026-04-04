<?php

declare(strict_types=1);

namespace QS\Shared\DTO;

final class RestResponse
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private readonly string $status,
        private readonly array $data = [],
        private readonly array $meta = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'data' => $this->data,
            'meta' => $this->meta,
        ];
    }
}
