<?php

declare(strict_types=1);

namespace QS\Core\Wordpress;

use QS\Core\Contracts\HookableInterface;

final class PostTypeRegistrar implements HookableInterface
{
    /**
     * @param array<string, array<string, mixed>> $postTypes
     */
    public function __construct(private readonly array $postTypes)
    {
    }

    public function register(): void
    {
        if ($this->wordpressFunctionExists('add_action')) {
            add_action('init', [$this, 'registerPostTypes']);
        }
    }

    public function registerPostTypes(): void
    {
        if (! $this->wordpressFunctionExists('register_post_type')) {
            return;
        }

        foreach ($this->postTypes as $slug => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            register_post_type(
                $slug,
                [
                    'label' => $definition['label'] ?? $slug,
                    'labels' => [
                        'name' => $definition['label'] ?? $slug,
                        'singular_name' => $definition['singular_label'] ?? $slug,
                    ],
                    'public' => false,
                    'show_ui' => true,
                    'show_in_rest' => false,
                    'supports' => ['title', 'editor', 'custom-fields'],
                    'capability_type' => 'post',
                    'map_meta_cap' => true,
                    'menu_icon' => $definition['menu_icon'] ?? 'dashicons-admin-post',
                ]
            );
        }
    }

    private function wordpressFunctionExists(string $function): bool
    {
        return function_exists(__NAMESPACE__ . '\\' . $function) || function_exists($function);
    }
}
