<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Infrastructure\Persistence;

use QS\Modules\Finance\Domain\Repository\ServiceCostRepository;

final class WpServiceCostRepository implements ServiceCostRepository
{
    public function findAll(): array
    {
        if (! function_exists('get_posts')) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'qs_service',
            'post_status' => ['publish', 'private', 'draft'],
            'numberposts' => -1,
        ]);

        if (! is_array($posts)) {
            return [];
        }

        $costs = [];

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $cost = get_post_meta($post->ID, 'costo_staff_clp', true);

            if (! is_numeric($cost)) {
                continue;
            }

            $costs[$this->normalizeServiceName($post->post_title)] = (int) $cost;
        }

        return $costs;
    }

    private function normalizeServiceName(string $serviceName): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $serviceName) ?? $serviceName));
    }
}
