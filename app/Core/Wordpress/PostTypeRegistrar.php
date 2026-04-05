<?php

declare(strict_types=1);

namespace QS\Core\Wordpress;

use QS\Core\Contracts\HookableInterface;

final class PostTypeRegistrar implements HookableInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const POST_TYPES = [
        'qs_bitacora' => [
            'label' => 'Bitacoras QS',
            'singular_label' => 'Bitacora QS',
            'menu_icon' => 'dashicons-location-alt',
        ],
        'qs_payment' => [
            'label' => 'Pagos QS',
            'singular_label' => 'Pago QS',
            'menu_icon' => 'dashicons-money-alt',
        ],
        'qs_expense' => [
            'label' => 'Gastos QS',
            'singular_label' => 'Gasto QS',
            'menu_icon' => 'dashicons-chart-bar',
        ],
        'qs_lead' => [
            'label' => 'Leads QS',
            'singular_label' => 'Lead QS',
            'menu_icon' => 'dashicons-groups',
        ],
    ];

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('init', [$this, 'registerPostTypes']);
        }
    }

    public function registerPostTypes(): void
    {
        if (! function_exists('register_post_type')) {
            return;
        }

        foreach (self::POST_TYPES as $slug => $definition) {
            register_post_type(
                $slug,
                [
                    'label' => $definition['label'],
                    'labels' => [
                        'name' => $definition['label'],
                        'singular_name' => $definition['singular_label'],
                    ],
                    'public' => false,
                    'show_ui' => true,
                    'show_in_rest' => false,
                    'supports' => ['title', 'editor', 'custom-fields'],
                    'capability_type' => 'post',
                    'map_meta_cap' => true,
                    'menu_icon' => $definition['menu_icon'],
                ]
            );
        }
    }
}
