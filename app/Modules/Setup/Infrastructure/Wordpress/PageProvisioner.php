<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Infrastructure\Wordpress;

use QS\Core\Logging\Logger;

final class PageProvisioner
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    /**
     * @param array<int, array{slug: string, title: string, content: string, status: string}> $pages
     * @return array{pages: array<string, array{id: int, slug: string, title: string, state: string}>}
     */
    public function provision(array $pages, bool $force = false): array
    {
        $result = [];

        foreach ($pages as $page) {
            $slug = sanitize_title((string) ($page['slug'] ?? ''));
            $title = trim((string) ($page['title'] ?? ''));
            $content = (string) ($page['content'] ?? '');
            $status = (string) ($page['status'] ?? 'publish');

            if ($slug === '' || $title === '') {
                continue;
            }

            $existing = get_page_by_path($slug, OBJECT, 'page');

            if ($existing instanceof \WP_Post) {
                $pageId = (int) $existing->ID;
                $state = 'existing';

                if ($force) {
                    $updatedId = wp_update_post([
                        'ID' => $pageId,
                        'post_title' => $title,
                        'post_name' => $slug,
                        'post_content' => $content,
                        'post_status' => $status,
                        'post_type' => 'page',
                    ], true);

                    if (is_wp_error($updatedId)) {
                        throw new \RuntimeException(sprintf('No se pudo actualizar la pagina "%s": %s', $slug, $updatedId->get_error_message()));
                    }

                    $pageId = (int) $updatedId;
                    $state = 'updated';
                }
            } else {
                $insertedId = wp_insert_post([
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => $content,
                    'post_status' => $status,
                    'post_type' => 'page',
                ], true);

                if (is_wp_error($insertedId)) {
                    throw new \RuntimeException(sprintf('No se pudo crear la pagina "%s": %s', $slug, $insertedId->get_error_message()));
                }

                $pageId = (int) $insertedId;
                $state = 'created';
            }

            $result[$slug] = [
                'id' => $pageId,
                'slug' => $slug,
                'title' => $title,
                'state' => $state,
            ];
        }

        $this->logger->info('QS page provisioning completed.');

        return ['pages' => $result];
    }
}
