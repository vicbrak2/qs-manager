<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Http;

interface HttpClient
{
    /**
     * @param array<string, mixed> $options
     * @return array{statusCode: int, contentType: string, contents: string|false, error?: string}
     */
    public function get(string $url, array $options = []): array;
}
