<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Infrastructure\N8n;

final class IngestGateway
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = defined('QS_N8N_INGEST_URL')
            ? QS_N8N_INGEST_URL
            : 'http://localhost:5678/webhook/wp-ingest-rag';
    }

    /**
     * Envía un post de WordPress al pipeline de ingestión RAG.
     * Retorna true si n8n respondió 200, false si hubo error.
     */
    public function ingest(int $postId, string $title, string $url, string $content): bool
    {
        $response = wp_remote_post($this->webhookUrl, [
            'body'    => json_encode([
                'post_id' => $postId,
                'title'   => $title,
                'url'     => $url,
                'content' => $content,
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_response_code($response) === 200;
    }
}
