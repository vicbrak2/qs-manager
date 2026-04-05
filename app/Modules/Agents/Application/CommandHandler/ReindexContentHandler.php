<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Application\CommandHandler;

use QS\Modules\Agents\Infrastructure\N8n\IngestGateway;

final class ReindexContentHandler
{
    private const CONTEXT_DOCUMENTS_OPTION = 'qs_chatbot_context_documents';

    public function __construct(
        private readonly IngestGateway $gateway
    ) {
    }

    /**
     * Itera todos los posts publicados y los envía al pipeline RAG.
     *
     * @param array<int,string> $postTypes  Tipos de post a indexar (default: post y page)
     * @return array{indexed: int, failed: int, failures: array<int, array<string, mixed>>}
     */
    public function handle(array $postTypes = ['post', 'page']): array
    {
        $indexed = 0;
        $failed  = 0;
        $failures = [];

        $query = new \WP_Query([
            'post_type'      => $postTypes,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($query->posts as $postReference) {
            $postId = $postReference instanceof \WP_Post
                ? (int) $postReference->ID
                : $postReference;
            $post = get_post($postId);

            if (! $post instanceof \WP_Post) {
                continue;
            }

            $content = wp_strip_all_tags($post->post_content);

            if (empty(trim($content))) {
                continue;
            }

            $result = $this->gateway->ingestWithDiagnostics(
                $postId,
                $post->post_title,
                (string) get_permalink($postId),
                $content
            );

            if ($result['ok']) {
                $indexed++;
                continue;
            }

            $failed++;
            $failures[] = [
                'post_id' => $postId,
                'title' => $post->post_title,
                'error' => $result['error'],
                'status_code' => $result['status_code'],
                'webhook_url' => $result['webhook_url'],
                'response_body' => $result['response_body'],
            ];
        }

        foreach ($this->contextDocuments() as $document) {
            $result = $this->gateway->ingestWithDiagnostics(
                $this->syntheticPostId($document['id']),
                $document['title'],
                $document['url'],
                $document['content']
            );

            if ($result['ok']) {
                $indexed++;
                continue;
            }

            $failed++;
            $failures[] = [
                'post_id' => $this->syntheticPostId($document['id']),
                'title' => $document['title'],
                'error' => $result['error'],
                'status_code' => $result['status_code'],
                'webhook_url' => $result['webhook_url'],
                'response_body' => $result['response_body'],
            ];
        }

        return compact('indexed', 'failed', 'failures');
    }

    /**
     * @return array<int, array{id: string, title: string, url: string, content: string}>
     */
    private function contextDocuments(): array
    {
        $documents = get_option(self::CONTEXT_DOCUMENTS_OPTION, []);

        if (! is_array($documents)) {
            return [];
        }

        $normalized = [];

        foreach ($documents as $document) {
            if (! is_array($document)) {
                continue;
            }

            $id = isset($document['id']) && is_string($document['id']) ? trim($document['id']) : '';
            $title = isset($document['title']) && is_string($document['title']) ? trim($document['title']) : '';
            $url = isset($document['url']) && is_string($document['url']) ? trim($document['url']) : '';
            $content = isset($document['content']) && is_string($document['content']) ? $document['content'] : '';

            if ($id === '' || $title === '' || trim($content) === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'title' => $title,
                'url' => $url !== '' ? $url : 'context://manual/' . $id,
                'content' => $content,
            ];
        }

        return $normalized;
    }

    private function syntheticPostId(string $seed): int
    {
        return (int) sprintf('%u', crc32($seed));
    }
}
