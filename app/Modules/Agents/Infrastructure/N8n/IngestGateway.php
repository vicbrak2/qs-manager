<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

use QS\Core\Logging\Logger;

final class IngestGateway
{
    private string $webhookUrl;

    public function __construct(
        private readonly Logger $logger
    )
    {
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

    /**
     * @return array{ok: bool, status_code: int|null, error: string|null, response_body: string, webhook_url: string}
     */
    private function dispatch(int $postId, string $title, string $url, string $content): array
    {
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
            $this->logger->warning(sprintf('QS ingest failed for post %d: %s', $postId, $error));

            return [
                'ok' => false,
                'status_code' => null,
                'error' => $error,
                'response_body' => '',
                'webhook_url' => $this->webhookUrl,
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = (string) wp_remote_retrieve_body($response);
        $ok = $statusCode === 200;

        if (! $ok) {
            $this->logger->warning(
                sprintf('QS ingest failed for post %d with HTTP %d. Body: %s', $postId, $statusCode, $responseBody)
            );
        }

        return [
            'ok' => $ok,
            'status_code' => $statusCode,
            'error' => $ok ? null : 'HTTP ' . $statusCode,
            'response_body' => $responseBody,
            'webhook_url' => $this->webhookUrl,
        ];
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

        return 'http://localhost:5678/webhook/wp-ingest-rag';
    }
}
