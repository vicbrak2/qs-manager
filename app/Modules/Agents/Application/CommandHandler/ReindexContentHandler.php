<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Application\CommandHandler;

use QS\Modules\Agents\Infrastructure\N8n\IngestGateway;

final class ReindexContentHandler
{
    public function __construct(
        private readonly IngestGateway $gateway
    ) {
    }

    /**
     * Itera todos los posts publicados y los envía al pipeline RAG.
     *
     * @param array<int,string> $postTypes  Tipos de post a indexar (default: post y page)
     * @return array{indexed: int, failed: int}
     */
    public function handle(array $postTypes = ['post', 'page']): array
    {
        $indexed = 0;
        $failed  = 0;

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

            $ok = $this->gateway->ingest(
                $postId,
                $post->post_title,
                (string) get_permalink($postId),
                $content
            );

            $ok ? $indexed++ : $failed++;
        }

        return compact('indexed', 'failed');
    }
}
