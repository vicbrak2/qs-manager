<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\Qdrant;

use QS\Core\Logging\Logger;

final class QdrantGateway
{
    private const QDRANT_URL_OPTION = 'qs_qdrant_url';
    private const QDRANT_API_KEY_OPTION = 'qs_qdrant_api_key';
    private const COLLECTION_NAME = 'wordpress_context';

    public function __construct(
        private readonly Logger $logger
    ) {
    }

    /**
     * @param array<int, int|string> $postIds
     * @return array{ok: bool, deleted_points: int, error: string|null, status_code: int|null, response_body: string}
     */
    public function deleteByPostIds(array $postIds): array
    {
        $normalizedIds = [];

        foreach ($postIds as $postId) {
            $value = trim((string) $postId);

            if ($value === '') {
                continue;
            }

            $normalizedIds[] = $value;
        }

        $normalizedIds = array_values(array_unique($normalizedIds));

        if ($normalizedIds === []) {
            return [
                'ok' => true,
                'deleted_points' => 0,
                'error' => null,
                'status_code' => null,
                'response_body' => '',
            ];
        }

        $indexResult = $this->ensurePostIdIndex();

        if (! $indexResult['ok']) {
            return [
                'ok' => false,
                'deleted_points' => 0,
                'error' => $indexResult['error'],
                'status_code' => $indexResult['status_code'],
                'response_body' => $indexResult['response_body'],
            ];
        }

        $filter = [
            'should' => array_map(
                static fn (string $postId): array => [
                    'key' => 'metadata.post_id',
                    'match' => [
                        'value' => $postId,
                    ],
                ],
                $normalizedIds
            ),
        ];

        $countResult = $this->countByFilter($filter);
        $deleteResult = $this->request(
            'POST',
            '/collections/' . self::COLLECTION_NAME . '/points/delete?wait=true',
            ['filter' => $filter]
        );

        return [
            'ok' => $deleteResult['ok'],
            'deleted_points' => $countResult['count'],
            'error' => $deleteResult['error'],
            'status_code' => $deleteResult['status_code'],
            'response_body' => $deleteResult['response_body'],
        ];
    }

    /**
     * @return array{ok: bool, deleted_points: int, error: string|null, status_code: int|null, response_body: string}
     */
    public function purgeCollection(): array
    {
        $pointIds = [];
        $offset = null;

        do {
            $body = [
                'limit' => 1000,
                'with_payload' => false,
                'with_vector' => false,
            ];

            if ($offset !== null) {
                $body['offset'] = $offset;
            }

            $result = $this->request(
                'POST',
                '/collections/' . self::COLLECTION_NAME . '/points/scroll',
                $body
            );

            if (! $result['ok']) {
                return [
                    'ok' => false,
                    'deleted_points' => 0,
                    'error' => $result['error'],
                    'status_code' => $result['status_code'],
                    'response_body' => $result['response_body'],
                ];
            }

            $decoded = $this->decodeResponse($result['response_body']);
            $points = isset($decoded['result']['points']) && is_array($decoded['result']['points'])
                ? $decoded['result']['points']
                : [];

            foreach ($points as $point) {
                if (! is_array($point) || ! isset($point['id'])) {
                    continue;
                }

                if (is_string($point['id']) || is_int($point['id'])) {
                    $pointIds[] = $point['id'];
                }
            }

            $offset = null;

            if (
                isset($decoded['result']['next_page_offset']) &&
                (is_string($decoded['result']['next_page_offset']) || is_int($decoded['result']['next_page_offset']))
            ) {
                $offset = $decoded['result']['next_page_offset'];
            }
        } while ($offset !== null);

        if ($pointIds === []) {
            return [
                'ok' => true,
                'deleted_points' => 0,
                'error' => null,
                'status_code' => null,
                'response_body' => '',
            ];
        }

        $deleteResult = $this->request(
            'POST',
            '/collections/' . self::COLLECTION_NAME . '/points/delete?wait=true',
            ['points' => array_values($pointIds)]
        );

        return [
            'ok' => $deleteResult['ok'],
            'deleted_points' => count($pointIds),
            'error' => $deleteResult['error'],
            'status_code' => $deleteResult['status_code'],
            'response_body' => $deleteResult['response_body'],
        ];
    }

    public function hasApiKeyConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * @return array{ok: bool, status_code: int|null, error: string|null, response_body: string}
     */
    private function ensurePostIdIndex(): array
    {
        $collectionResult = $this->request('GET', '/collections/' . self::COLLECTION_NAME);

        if (! $collectionResult['ok']) {
            return $collectionResult;
        }

        $decoded = $this->decodeResponse($collectionResult['response_body']);
        $schema = isset($decoded['result']['payload_schema']) && is_array($decoded['result']['payload_schema'])
            ? $decoded['result']['payload_schema']
            : [];

        if (array_key_exists('metadata.post_id', $schema)) {
            return [
                'ok' => true,
                'status_code' => $collectionResult['status_code'],
                'error' => null,
                'response_body' => $collectionResult['response_body'],
            ];
        }

        return $this->request(
            'PUT',
            '/collections/' . self::COLLECTION_NAME . '/index?wait=true',
            [
                'field_name' => 'metadata.post_id',
                'field_schema' => 'keyword',
            ]
        );
    }

    /**
     * @param array<string, mixed> $filter
     * @return array{ok: bool, count: int, error: string|null, status_code: int|null, response_body: string}
     */
    private function countByFilter(array $filter): array
    {
        $result = $this->request(
            'POST',
            '/collections/' . self::COLLECTION_NAME . '/points/count',
            [
                'filter' => $filter,
                'exact' => true,
            ]
        );

        if (! $result['ok']) {
            return [
                'ok' => false,
                'count' => 0,
                'error' => $result['error'],
                'status_code' => $result['status_code'],
                'response_body' => $result['response_body'],
            ];
        }

        $decoded = $this->decodeResponse($result['response_body']);
        $count = isset($decoded['result']['count']) ? (int) $decoded['result']['count'] : 0;

        return [
            'ok' => true,
            'count' => $count,
            'error' => null,
            'status_code' => $result['status_code'],
            'response_body' => $result['response_body'],
        ];
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array{ok: bool, status_code: int|null, error: string|null, response_body: string}
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $baseUrl = $this->baseUrl();
        $apiKey = $this->apiKey();

        if ($baseUrl === '') {
            return [
                'ok' => false,
                'status_code' => null,
                'error' => 'Qdrant URL no configurada.',
                'response_body' => '',
            ];
        }

        if ($apiKey === '') {
            return [
                'ok' => false,
                'status_code' => null,
                'error' => 'Qdrant API key no configurada.',
                'response_body' => '',
            ];
        }

        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'api-key' => $apiKey,
                'Accept' => 'application/json',
            ],
        ];

        if ($body !== null) {
            $encoded = wp_json_encode($body);

            if (! is_string($encoded)) {
                return [
                    'ok' => false,
                    'status_code' => null,
                    'error' => 'No se pudo serializar el body para Qdrant.',
                    'response_body' => '',
                ];
            }

            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = $encoded;
        }

        $url = rtrim($baseUrl, '/') . $path;
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $this->logger->warning('Qdrant request failed: ' . $error);

            return [
                'ok' => false,
                'status_code' => null,
                'error' => $error,
                'response_body' => '',
            ];
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $responseBody = (string) wp_remote_retrieve_body($response);

        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'error' => $statusCode >= 200 && $statusCode < 300 ? null : 'HTTP ' . $statusCode,
            'response_body' => $responseBody,
        ];
    }

    private function baseUrl(): string
    {
        if (defined('QDRANT_URL') && is_string(QDRANT_URL) && trim(QDRANT_URL) !== '') {
            return trim(QDRANT_URL);
        }

        $envUrl = getenv('QDRANT_URL');

        if (is_string($envUrl) && trim($envUrl) !== '') {
            return trim($envUrl);
        }

        $clusterEndpoint = getenv('QDRANT_CLUSTER_ENDPOINT');

        if (is_string($clusterEndpoint) && trim($clusterEndpoint) !== '') {
            return trim($clusterEndpoint);
        }

        $optionValue = get_option(self::QDRANT_URL_OPTION, '');

        return is_string($optionValue) ? trim($optionValue) : '';
    }

    private function apiKey(): string
    {
        if (defined('QDRANT_API_KEY') && is_string(QDRANT_API_KEY) && trim(QDRANT_API_KEY) !== '') {
            return trim(QDRANT_API_KEY);
        }

        if (defined('QDRANT_KEY') && is_string(QDRANT_KEY) && trim(QDRANT_KEY) !== '') {
            return trim(QDRANT_KEY);
        }

        if (defined('QGRANT_KEY') && is_string(QGRANT_KEY) && trim(QGRANT_KEY) !== '') {
            return trim(QGRANT_KEY);
        }

        foreach (['QDRANT_API_KEY', 'QDRANT_KEY', 'QGRANT_KEY'] as $envKey) {
            $value = getenv($envKey);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $optionValue = get_option(self::QDRANT_API_KEY_OPTION, '');

        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string $body): array
    {
        if (trim($body) === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
