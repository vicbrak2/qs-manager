<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

use QS\Core\Logging\Logger;
use QS\Modules\Agents\Infrastructure\Qdrant\QdrantGateway;

final class IngestGateway
{
    private const OPTION_NAME = 'qs_n8n_ingest_url';
    private const MAX_ATTEMPTS = 5;

    private string $webhookUrl;

    public function __construct(
        private readonly Logger $logger,
        private readonly QdrantGateway $qdrantGateway
    ) {
        $this->webhookUrl = $this->resolveWebhookUrl();
    }

    /**
     * Envía un post de WordPress al pipeline de ingestión RAG.
     * Retorna true si n8n respondió 200, false si hubo error.
     */
    public function ingest(int $postId, string $title, string $url, string $content): bool
    {
        return $this->dispatch($postId, $title, $url, $content)['ok'];
    }

    /**
     * @return array{ok: bool, status_code: int|null, error: string|null, response_body: string, webhook_url: string}
     */
    public function ingestWithDiagnostics(int $postId, string $title, string $url, string $content): array
    {
        return $this->dispatch($postId, $title, $url, $content);
    }

    public function webhookUrl(): string
    {
        return $this->webhookUrl;
    }

    /**
     * @return array{ok: bool, status_code: int|null, error: string|null, response_body: string, webhook_url: string}
     */
    private function dispatch(int $postId, string $title, string $url, string $content): array
    {
        $deleteResult = $this->qdrantGateway->deleteByPostIds([$postId]);

        if (! $deleteResult['ok']) {
            $this->logger->warning(
                sprintf(
                    'QS vector delete failed before ingest for post %d: %s',
                    $postId,
                    $deleteResult['error'] ?? 'sin detalle'
                )
            );
        }

        $body = wp_json_encode([
            'post_id' => $postId,
            'title'   => $title,
            'url'     => $url,
            'content' => $content,
        ]);

        if (! is_string($body)) {
            return [
                'ok' => false,
                'status_code' => null,
                'error' => 'No se pudo serializar la carga de ingesta.',
                'response_body' => '',
                'webhook_url' => $this->webhookUrl,
            ];
        }

        $lastResult = [
            'ok' => false,
            'status_code' => null,
            'error' => 'No se pudo ejecutar la ingesta.',
            'response_body' => '',
            'webhook_url' => $this->webhookUrl,
        ];

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = wp_remote_post($this->webhookUrl, [
                'body'    => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                $error = $response->get_error_message();
                $lastResult = [
                    'ok' => false,
                    'status_code' => null,
                    'error' => $error,
                    'response_body' => '',
                    'webhook_url' => $this->webhookUrl,
                ];

                $this->logger->warning(sprintf('QS ingest failed for post %d: %s', $postId, $error));

                if ($attempt < self::MAX_ATTEMPTS) {
                    $this->pauseBeforeRetry($attempt);
                    continue;
                }

                return $lastResult;
            }

            $statusCode = (int) wp_remote_retrieve_response_code($response);
            $responseBody = (string) wp_remote_retrieve_body($response);
            $ok = $statusCode === 200;

            $lastResult = [
                'ok' => $ok,
                'status_code' => $statusCode,
                'error' => $ok ? null : 'HTTP ' . $statusCode,
                'response_body' => $responseBody,
                'webhook_url' => $this->webhookUrl,
            ];

            if ($ok) {
                return $lastResult;
            }

            $this->logger->warning(
                sprintf('QS ingest failed for post %d with HTTP %d. Body: %s', $postId, $statusCode, $responseBody)
            );

            if ($attempt < self::MAX_ATTEMPTS && $this->shouldRetry($statusCode, $responseBody)) {
                $this->pauseBeforeRetry($attempt);
                continue;
            }

            return $lastResult;
        }

        return $lastResult;
    }

    private function resolveWebhookUrl(): string
    {
        if (defined('QS_N8N_INGEST_URL') && is_string(QS_N8N_INGEST_URL) && QS_N8N_INGEST_URL !== '') {
            return QS_N8N_INGEST_URL;
        }

        $envValue = getenv('QS_N8N_INGEST_URL');

        if (is_string($envValue) && trim($envValue) !== '') {
            return trim($envValue);
        }

        $optionValue = get_option(self::OPTION_NAME, '');

        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }

        return 'http://localhost:5678/webhook/wp-ingest-rag';
    }

    private function shouldRetry(int $statusCode, string $responseBody): bool
    {
        if ($statusCode === 408 || $statusCode === 409 || $statusCode === 425 || $statusCode === 429) {
            return true;
        }

        if ($statusCode >= 500) {
            return true;
        }

        if ($statusCode === 422 && str_contains($responseBody, 'ERR_NGROK_3803')) {
            return true;
        }

        return false;
    }

    private function pauseBeforeRetry(int $attempt): void
    {
        usleep($attempt * 300000);
    }
}
